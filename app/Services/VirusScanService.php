<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VirusScanService
{
    /**
     * Scan a file stored on the given disk.
     * Returns true if clean, false if infected or scan failed with a positive result.
     * Throws nothing — scan unavailability degrades gracefully (logged + treated as clean).
     */
    public function scan(string $storagePath, string $disk = 'local'): bool
    {
        $absolutePath = Storage::disk($disk)->path($storagePath);

        if (! file_exists($absolutePath)) {
            Log::warning("VirusScan: file not found at {$absolutePath}");
            return true;
        }

        $clamscan = $this->findClamscan();

        if ($clamscan === null) {
            Log::info('VirusScan: clamscan not available — skipping scan for ' . basename($storagePath));
            return true;
        }

        $command    = escapeshellcmd($clamscan) . ' --no-summary --stdout ' . escapeshellarg($absolutePath);
        $output     = [];
        $exitCode   = 0;

        exec($command . ' 2>&1', $output, $exitCode);

        // clamscan exit codes: 0 = clean, 1 = infected found, 2 = error
        if ($exitCode === 1) {
            Log::warning('VirusScan: INFECTED file detected — ' . basename($storagePath) . ' — ' . implode(' ', $output));
            return false;
        }

        if ($exitCode === 2) {
            Log::error('VirusScan: scan error for ' . basename($storagePath) . ' — ' . implode(' ', $output));
            // Treat scan errors as clean to avoid blocking legitimate uploads due to scanner issues.
            // In a stricter posture, return false here.
            return true;
        }

        return true;
    }

    private function findClamscan(): ?string
    {
        foreach (['clamscan', '/usr/bin/clamscan', '/usr/local/bin/clamscan', 'C:\\ClamAV\\clamscan.exe'] as $candidate) {
            $test = shell_exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null')
                 ?? shell_exec('where ' . escapeshellarg($candidate) . ' 2>NUL');

            if ($test && trim($test) !== '') {
                return $candidate;
            }

            // On Windows "where" check
            if (PHP_OS_FAMILY === 'Windows' && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
