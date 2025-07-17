<?php

namespace MohamedGaldi\ViltFilepond\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MohamedGaldi\ViltFilepond\Services\FilePondService;
use MohamedGaldi\ViltFilepond\Services\ChunkUploadService;

class FilePondController extends Controller
{
    protected FilePondService $filePondService;
    protected ChunkUploadService $chunkUploadService;

    public function __construct(FilePondService $filePondService, ChunkUploadService $chunkUploadService)
    {
        $this->filePondService = $filePondService;
        $this->chunkUploadService = $chunkUploadService;
    }

    public function upload(Request $request)
    {
        // Check if this is a chunk upload initialization
        if ($request->hasHeader('Upload-Length') && !$request->hasFile('filepond')) {
            return $this->chunkUploadService->initializeChunkUpload($request);
        }

        // Regular file upload
        $request->validate(['filepond' => ['required', 'file']]);

        try {
            $tempFile = $this->filePondService->storeTempFile($request->file('filepond'));
            // Return plain text response instead of JSON
            return response($tempFile->folder, 200, [
                'Content-Type' => 'text/plain'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'File upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function patch(Request $request)
    {
        try {
            return $this->chunkUploadService->handleChunkUpload($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Chunk upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function restore(Request $request)
    {
        try {
            $transferId = $request->query('restore');
            $chunkPath = "{$this->chunkUploadService->getTempPath()}/chunks/{$transferId}";

            // Get current upload offset
            $currentSize = $this->chunkUploadService->getTotalChunksSize($chunkPath);

            // For HEAD requests, we only need to return the offset in headers
            return response('', 200, [
                'Upload-Offset' => $currentSize
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Restore failed: ' . $e->getMessage()], 500);
        }
    }

    public function revert(Request $request, string $folder)
    {
        try {
            // Try to delete as regular temp file first
            if ($this->filePondService->deleteTempFile($folder)) {
                return response()->json(['message' => 'File deleted successfully']);
            }

            // If not found, try to delete as chunk upload
            $this->chunkUploadService->deleteChunkUpload($folder);
            return response()->json(['message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'File deletion failed: ' . $e->getMessage()], 500);
        }
    }
}
