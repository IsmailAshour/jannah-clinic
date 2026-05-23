<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicalAttachment;
use App\Models\MedicalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Medical record attachments — lab results, imaging, old prescriptions.
 *
 * Storage posture: PRIVATE local disk (NOT storage/app/public/), matching
 * payment receipts and appointment photos. Files are streamed through
 * Laravel so role + ownership are enforced on every read.
 */
class MedicalAttachmentController extends Controller
{
    private const MAX_FILE_MB = 10;

    public function store(Request $request, MedicalEntry $entry): RedirectResponse
    {
        Gate::authorize('uploadAttachment', $entry);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.(self::MAX_FILE_MB * 1024)],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $path = $file->store('medical-attachments/'.$entry->id, 'local');

        MedicalAttachment::create([
            'medical_entry_id' => $entry->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $file->getSize() ?: 0,
            'title' => $data['title'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم رفع الملف.');
    }

    public function file(MedicalEntry $entry, MedicalAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $entry);
        abort_unless($attachment->medical_entry_id === $entry->id, 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->original_filename);
    }

    public function destroy(MedicalEntry $entry, MedicalAttachment $attachment): RedirectResponse
    {
        Gate::authorize('deleteAttachment', $entry);
        abort_unless($attachment->medical_entry_id === $entry->id, 404);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'تم حذف الملف.');
    }
}
