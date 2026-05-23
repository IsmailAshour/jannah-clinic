<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only attachment stream for the appointment's customer. The Gate
 * `view` enforces ownership — staff routes use the admin controller.
 */
class MedicalAttachmentController extends Controller
{
    public function file(Appointment $appointment, MedicalAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', [MedicalAttachment::class, $appointment]);
        abort_unless($attachment->appointment_id === $appointment->id, 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->original_filename);
    }
}
