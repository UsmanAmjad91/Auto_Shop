<?php

namespace App\Models\Concerns;

use App\Models\Attachment;

trait HasAttachments
{
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }
}
