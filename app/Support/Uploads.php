<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores validated uploads with random names (never the client file name).
 * Images go to the public disk; documents go to the private "local" disk and are
 * streamed through authorised controllers only.
 */
class Uploads
{
    public static function image(UploadedFile $file, string $dir): string
    {
        return $file->storeAs($dir, Str::random(40).'.'.$file->extension(), 'public');
    }

    public static function private(UploadedFile $file, string $dir): string
    {
        return $file->storeAs($dir, Str::random(40).'.'.$file->extension(), 'local');
    }

    public static function imageRule(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'image', 'mimes:'.config('autoshop.uploads.image_mimes'),
            'max:'.config('autoshop.uploads.image_max_kb'), 'dimensions:max_width=8000,max_height=8000'];
    }

    public static function documentRule(bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:'.config('autoshop.uploads.document_mimes'),
            'max:'.config('autoshop.uploads.document_max_kb')];
    }
}
