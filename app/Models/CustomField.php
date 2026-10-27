<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    use Auditable;

    public const TYPES = ['text' => 'Text', 'number' => 'Number', 'date' => 'Date', 'select' => 'Dropdown', 'checkbox' => 'Checkbox', 'textarea' => 'Long text'];

    protected $fillable = ['module', 'key', 'label', 'type', 'options', 'is_required', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function forModule(string $module)
    {
        return static::where('module', $module)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** Validation rules for these fields under "custom_fields.<key>". */
    public static function rulesFor(string $module): array
    {
        $rules = [];
        foreach (static::forModule($module) as $f) {
            $r = [$f->is_required ? 'required' : 'nullable'];
            $r[] = match ($f->type) {
                'number' => 'numeric',
                'date' => 'date',
                'checkbox' => 'boolean',
                'select' => 'in:'.implode(',', array_map(fn ($o) => str_replace(',', '', $o), $f->options ?? [])),
                'textarea' => 'string|max:5000',
                default => 'string|max:255',
            };
            $rules['custom_fields.'.$f->key] = implode('|', $r);
        }

        return $rules;
    }
}
