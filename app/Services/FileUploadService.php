<?php

namespace App\Services;

use App\Jobs\ScanUploadedFileJob;
use App\Models\PatientFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileUploadService
{
    const ALLOWED_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];
    const MAX_SIZE_KB   = 10240; // 10 MB

    public function store(
        UploadedFile $file,
        string       $type,
        ?int         $caseId    = null,
        ?int         $patientId = null,
        ?int         $partnerId = null,
        ?string      $notes     = null,
    ): PatientFile {
        $this->validate($file);

        $disk      = config('filesystems.default', 'local');
        $uuid      = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $safeName  = $uuid . '.' . $extension;
        $folder    = 'patient-files/' . now()->format('Y/m');

        $path = $file->storeAs($folder, $safeName, $disk);

        $record = PatientFile::create([
            'uuid'          => $uuid,
            'case_id'       => $caseId,
            'patient_id'    => $patientId,
            'partner_id'    => $partnerId,
            'name'          => $safeName,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'disk'          => $disk,
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'type'          => $type,
            'status'        => 'uploaded',
            'notes'         => $notes,
        ]);

        ScanUploadedFileJob::dispatch($record->id)->onQueue('default');

        return $record;
    }

    private function validate(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => 'Only PDF, JPG, and PNG files are allowed.',
            ]);
        }

        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            throw ValidationException::withMessages([
                'file' => 'File size must not exceed 10 MB.',
            ]);
        }
    }

    public function delete(PatientFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }
}
