<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Part;
use App\Models\Supplier;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Private attachments: stored outside the web root and streamed only after authorisation. */
class FileController extends Controller
{
    protected const TYPES = [
        'customer' => [Customer::class, 'customers'],
        'supplier' => [Supplier::class, 'suppliers'],
        'employee' => [Employee::class, 'employees'],
        'part' => [Part::class, 'parts'],
    ];

    public function storeAttachment(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'id' => ['required', 'integer'],
            'category' => ['nullable', 'string', 'max:50'],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => Uploads::documentRule(true),
        ]);
        [$class, $module] = self::TYPES[$data['type']];
        abort_unless($request->user()->can("$module.edit"), 403);
        $model = $class::findOrFail($data['id']);

        foreach ($request->file('files') as $file) {
            $model->attachments()->create([
                'category' => $data['category'] ?? null,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'path' => Uploads::private($file, "attachments/{$data['type']}/{$model->id}"),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'File(s) uploaded.');
    }

    public function attachment(Request $request, Attachment $attachment)
    {
        $this->authorizeAttachment($request, $attachment, 'view');
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, Attachment $attachment)
    {
        $this->authorizeAttachment($request, $attachment, 'edit');
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }

    protected function authorizeAttachment(Request $request, Attachment $attachment, string $action): void
    {
        $module = collect(self::TYPES)->first(fn ($t) => $t[0] === $attachment->attachable_type)[1] ?? null;
        abort_unless($module && $request->user()->can("$module.$action"), 403);
    }
}
