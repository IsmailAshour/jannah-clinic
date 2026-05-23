<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Medical attachments tied to an Appointment — lab results, imaging,
 * old prescriptions. Decoupled from MedicalEntry so files can be uploaded
 * before the structured entry is written.
 *
 * Storage posture: PRIVATE local disk (never storage/app/public/).
 */
class MedicalAttachmentController extends Controller
{
    private const MAX_FILE_MB = 10;

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('upload', [MedicalAttachment::class, $appointment]);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.(self::MAX_FILE_MB * 1024)],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $path = $file->store('medical-attachments/'.$appointment->id, 'local');

        MedicalAttachment::create([
            'appointment_id' => $appointment->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $file->getSize() ?: 0,
            'title' => $data['title'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم رفع الملف.');
    }

    public function file(Appointment $appointment, MedicalAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', [MedicalAttachment::class, $appointment]);
        abort_unless($attachment->appointment_id === $appointment->id, 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->original_filename);
    }

    public function destroy(Appointment $appointment, MedicalAttachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', [MedicalAttachment::class, $appointment]);
        abort_unless($attachment->appointment_id === $appointment->id, 404);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'تم حذف الملف.');
    }
}
