<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        return $this->make($invoice)->output();
    }

    public function make(Invoice $invoice)
    {
        $invoice->loadMissing(['items', 'customer', 'vehicle', 'financing', 'allocations.payment']);

        return Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'logo' => self::logoDataUri(),
        ])->setPaper('a4');
    }

    /** Embed the logo as a data URI so DomPDF never needs remote access. */
    public static function logoDataUri(): ?string
    {
        $logo = settings('general.logo');
        if (! $logo || ! \Storage::disk('public')->exists($logo)) {
            return null;
        }
        $mime = \Storage::disk('public')->mimeType($logo) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(\Storage::disk('public')->get($logo));
    }
}
