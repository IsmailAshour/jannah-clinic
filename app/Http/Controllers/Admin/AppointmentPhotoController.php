<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentPhotoKind;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentPhotoController extends Controller
{
    /**
     * Upload a before/after photo for an appointment. Stored on the local
     * disk (not public) — same privacy posture as payment receipts, since
     * these are medical images that shouldn't be guessable by URL.
     */
    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_column(AppointmentPhotoKind::cases(), 'value'))],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('photo');
        $path = $file->store('appointment-photos/'.$appointment->id, 'local');

        AppointmentPhoto::create([
            'appointment_id' => $appointment->id,
            'kind' => $data['kind'],
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $file->getSize() ?: null,
            'caption' => $data['caption'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم رفع الصورة.');
    }

    /**
     * Stream a photo file. Authorized via the route group (admin/staff only).
     */
    public function file(Appointment $appointment, AppointmentPhoto $photo): StreamedResponse
    {
        abort_unless($photo->appointment_id === $appointment->id, 404);

        return Storage::disk('local')->response($photo->file_path);
    }

    public function destroy(Appointment $appointment, AppointmentPhoto $photo): RedirectResponse
    {
        abort_unless($photo->appointment_id === $appointment->id, 404);

        Storage::disk('local')->delete($photo->file_path);
        $photo->delete();

        return back()->with('success', 'تم حذف الصورة.');
    }
}
