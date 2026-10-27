<?php

namespace App\Services;

use App\Models\Part;
use App\Models\PartStock;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The ONLY place where part quantities change. Every change:
 *  - runs inside a DB transaction with the part row locked (SELECT ... FOR UPDATE)
 *  - updates the per-location stock and the part total together
 *  - refuses to go negative unless negative stock is explicitly authorised
 *  - writes an immutable stock_movements ledger row
 */
class InventoryService
{
    /**
     * @param  array{location_id?:int|null, unit_cost?:float|null, reference?:Model|null, vehicle_id?:int|null,
     *               notes?:string|null, reserved_change?:int, allow_negative?:bool, consume_reserved?:int}  $opts
     */
    public function move(Part $part, string $type, int $quantity, array $opts = []): StockMovement
    {
        return DB::transaction(function () use ($part, $type, $quantity, $opts) {
            /** @var Part $locked */
            $locked = Part::whereKey($part->getKey())->lockForUpdate()->firstOrFail();
            $previousStatus = $locked->stock_status;

            $locationId = $opts['location_id'] ?? $locked->default_location_id ?? StockLocation::default()->id;
            $reservedChange = (int) ($opts['reserved_change'] ?? 0);
            $consumeReserved = (int) ($opts['consume_reserved'] ?? 0);
            $allowNegative = ($opts['allow_negative'] ?? false) || $locked->allow_negative_stock
                || settings()->bool('inventory.allow_negative');

            $newQty = $locked->quantity + $quantity;
            $newReserved = max(0, $locked->quantity_reserved + $reservedChange - $consumeReserved);

            if ($quantity < 0 && ! $allowNegative) {
                // Outbound stock may use the caller's own reservation but never other reservations.
                $available = $locked->quantity - $locked->quantity_reserved + $consumeReserved;
                if ($available + $quantity < 0) {
                    throw ValidationException::withMessages([
                        'quantity' => "Insufficient stock for {$locked->name}: {$available} available, ".abs($quantity).' requested.',
                    ]);
                }
            }
            if ($reservedChange > 0 && ($locked->quantity - $locked->quantity_reserved) < $reservedChange && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot reserve {$reservedChange} of {$locked->name}: only ".($locked->quantity - $locked->quantity_reserved).' available.',
                ]);
            }

            if ($quantity !== 0) {
                $stock = PartStock::where('part_id', $locked->id)->where('location_id', $locationId)->lockForUpdate()->first()
                    ?? PartStock::create(['part_id' => $locked->id, 'location_id' => $locationId, 'quantity' => 0]);
                if ($stock->quantity + $quantity < 0 && ! $allowNegative) {
                    throw ValidationException::withMessages([
                        'quantity' => "Location \"{$stock->location?->name}\" only holds {$stock->quantity} of {$locked->name}.",
                    ]);
                }
                $stock->increment('quantity', $quantity);
            }

            $locked->quantity = $newQty;
            $locked->quantity_reserved = $newReserved;
            if ($type === 'damaged') {
                $locked->quantity_damaged += abs($quantity);
            }
            $locked->stock_status = $locked->computeStockStatus();
            $locked->saveQuietly();

            $ref = $opts['reference'] ?? null;
            $movement = StockMovement::create([
                'part_id' => $locked->id,
                'location_id' => $quantity !== 0 ? $locationId : null,
                'type' => $type,
                'quantity' => $quantity,
                'reserved_change' => $reservedChange - $consumeReserved,
                'balance_after' => $newQty,
                'unit_cost' => $opts['unit_cost'] ?? $locked->purchase_cost,
                'reference_type' => $ref?->getMorphClass(),
                'reference_id' => $ref?->getKey(),
                'vehicle_id' => $opts['vehicle_id'] ?? null,
                'notes' => $opts['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $part->setRawAttributes($locked->getAttributes(), true);

            if ($previousStatus === 'available' && $locked->stock_status !== 'available') {
                DB::afterCommit(fn () => $this->notifyLowStock($locked));
            }

            return $movement;
        });
    }

    public function add(Part $part, int $qty, array $opts = []): StockMovement
    {
        $this->assertPositive($qty);

        return $this->move($part, $opts['type'] ?? 'add', $qty, $opts);
    }

    public function remove(Part $part, int $qty, array $opts = []): StockMovement
    {
        $this->assertPositive($qty);

        return $this->move($part, $opts['type'] ?? 'remove', -$qty, $opts);
    }

    /** Set on-hand stock at a location to an exact counted value. */
    public function adjustTo(Part $part, int $countedQty, int $locationId, ?string $notes = null, bool $allowNegative = false): ?StockMovement
    {
        $current = (int) PartStock::where('part_id', $part->id)->where('location_id', $locationId)->value('quantity');
        $diff = $countedQty - $current;
        if ($diff === 0) {
            return null;
        }

        return $this->move($part, 'adjust', $diff, ['location_id' => $locationId, 'notes' => $notes, 'allow_negative' => $allowNegative]);
    }

    public function reserve(Part $part, int $qty, array $opts = []): StockMovement
    {
        $this->assertPositive($qty);

        return $this->move($part, 'reserve', 0, $opts + ['reserved_change' => $qty]);
    }

    public function unreserve(Part $part, int $qty, array $opts = []): StockMovement
    {
        $this->assertPositive($qty);
        $qty = min($qty, (int) $part->fresh()->quantity_reserved);

        return $this->move($part, 'unreserve', 0, $opts + ['reserved_change' => -$qty]);
    }

    public function transfer(Part $part, int $fromLocationId, int $toLocationId, int $qty, ?string $notes = null): array
    {
        $this->assertPositive($qty);
        if ($fromLocationId === $toLocationId) {
            throw ValidationException::withMessages(['to_location_id' => 'Source and destination must differ.']);
        }

        return DB::transaction(function () use ($part, $fromLocationId, $toLocationId, $qty, $notes) {
            $out = $this->move($part, 'transfer_out', -$qty, ['location_id' => $fromLocationId, 'notes' => $notes, 'allow_negative' => false]);
            $in = $this->move($part, 'transfer_in', $qty, ['location_id' => $toLocationId, 'notes' => $notes]);

            return [$out, $in];
        });
    }

    protected function assertPositive(int $qty): void
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }
    }

    protected function notifyLowStock(Part $part): void
    {
        try {
            $users = User::permission('parts.view')->where('status', 'approved')->get()
                ->filter(fn ($u) => $u->wantsNotification('low_stock', 'database'));
            Notification::send($users, new LowStockAlert($part));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
