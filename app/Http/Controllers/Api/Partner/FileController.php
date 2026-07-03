<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\PatientFile;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(private FileUploadService $fileUploader) {}

    /**
     * Upload a file and return a one-time file_token to reference in case creation.
     * POST /api/partner/files
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . FileUploadService::MAX_SIZE_KB,
        ]);

        $partner = $request->attributes->get('partner');

        $fileRecord = $this->fileUploader->store(
            $request->file('file'),
            'prescription',
            partnerId: $partner->id,
        );

        return response()->json([
            'file_token'    => $fileRecord->uuid,
            'original_name' => $fileRecord->original_name,
            'size'          => $fileRecord->size,
            'mime_type'     => $fileRecord->mime_type,
        ], 201);
    }
}
