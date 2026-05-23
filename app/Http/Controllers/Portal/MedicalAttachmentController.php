<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MedicalAttachment;
use App\Models\MedicalEntry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only attachment stream for customers. The Gate `view` call enforces
 * that only the appointment owner (or staff) gets the file — receptionists
 * are explicitly excluded by the same policy used for the medical record.
 */
class MedicalAttachmentController extends Controller
{
    public function file(MedicalEntry $entry, MedicalAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $entry);
        abort_unless($attachment->medical_entry_id === $entry->id, 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->original_filename);
    }
}
