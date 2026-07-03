<?php

namespace App\Jobs;

use App\Models\PatientFile;
use App\Services\VirusScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanUploadedFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(private int $fileId) {}

    public function handle(VirusScanService $scanner): void
    {
        $file = PatientFile::find($this->fileId);

        if (! $file || $file->status === 'processed') {
            return;
        }

        $file->update(['status' => 'processing']);

        $isClean = $scanner->scan($file->path, $file->disk);

        if ($isClean) {
            $file->update(['status' => 'processed']);
            return;
        }

        // Infected — delete from disk and mark failed
        Storage::disk($file->disk)->delete($file->path);
        $file->update(['status' => 'failed', 'notes' => 'Rejected by virus scanner.']);

        Log::warning("ScanUploadedFileJob: infected file removed — PatientFile#{$file->id} ({$file->original_name})");
    }
}
