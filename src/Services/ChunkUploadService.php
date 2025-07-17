<?php

namespace MohamedGaldi\ViltFilepond\Services;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MohamedGaldi\ViltFilepond\Models\TempFile;

class ChunkUploadService
{
    protected $disk;
    protected $tempPath;

    public function __construct()
    {
        $this->disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $this->tempPath = config('vilt-filepond.temp_path');
    }

    public function getTempPath(): string
    {
        return $this->tempPath;
    }

    public function initializeChunkUpload(Request $request)
    {
        $uploadLength = (int) $request->header('Upload-Length');
        $uploadName = $request->header('Upload-Name') ?? 'unknown_file';
        $collection = $request->input('collection', 'default');

        // Generate unique transfer ID
        $transferId = uniqid();
        $chunkPath = "{$this->tempPath}/chunks/{$transferId}";

        // Create chunk directory
        $this->disk->makeDirectory($chunkPath);

        // Create temp file record for tracking
        TempFile::create([
            'original_name' => $uploadName,
            'filename' => $uploadName,
            'path' => $chunkPath,
            'mime_type' => null,
            'size' => $uploadLength,
            'folder' => $transferId,
            'is_chunked' => true,
        ]);

        // Return the transfer ID as plain text, not JSON
        return response($transferId, 200, [
            'Content-Type' => 'text/plain'
        ]);
    }


    public function handleChunkUpload(Request $request)
    {
        $transferId = $request->query('patch');
        $uploadOffset = (int) $request->header('Upload-Offset', 0);
        $uploadLength = (int) $request->header('Upload-Length');
        $uploadName = $request->header('Upload-Name');

        $chunkPath = "{$this->tempPath}/chunks/{$transferId}";

        // Update the temp file record with the upload name if we have it now
        if ($uploadName) {
            $tempFile = TempFile::where('folder', $transferId)->first();
            if ($tempFile && ($tempFile->original_name === 'unknown_file' || !$tempFile->original_name)) {
                $tempFile->update([
                    'original_name' => $uploadName,
                    'filename' => $uploadName,
                ]);
            }
        }

        // Get chunk content
        $chunkContent = $request->getContent();
        $chunkSize = strlen($chunkContent);

        // Save chunk if not empty
        if ($chunkSize > 0) {
            $chunkFilePath = "{$chunkPath}/chunk_{$uploadOffset}";
            $this->disk->put($chunkFilePath, $chunkContent);
        }

        // Check if all chunks are received
        $currentSize = $this->getTotalChunksSize($chunkPath);

        if ($currentSize >= $uploadLength) {
            // All chunks received, assemble the file
            return $this->assembleChunks($transferId, $chunkPath, $uploadName, $uploadLength);
        }

        // Return empty response with 204 status for successful chunk (no content)
        return response('', 204);
    }

    public function getTotalChunksSize(string $chunkPath): int
    {
        $totalSize = 0;

        if ($this->disk->exists($chunkPath)) {
            $files = $this->disk->files($chunkPath);
            foreach ($files as $file) {
                if (str_contains($file, 'chunk_')) {
                    $totalSize += $this->disk->size($file);
                }
            }
        }

        return $totalSize;
    }

    public function handleRestore(Request $request)
    {
        $transferId = $request->query('restore');
        $chunkPath = "{$this->tempPath}/chunks/{$transferId}";

        // Get current upload offset
        $currentSize = $this->getTotalChunksSize($chunkPath);

        return response('', 200, [
            'Upload-Offset' => $currentSize
        ]);
    }

    public function deleteChunkUpload(string $transferId): bool
    {
        $chunkPath = "{$this->tempPath}/chunks/{$transferId}";

        // Delete chunk directory
        if ($this->disk->exists($chunkPath)) {
            $this->disk->deleteDirectory($chunkPath);
        }

        // Delete temp file record
        TempFile::where('folder', $transferId)->delete();

        return true;
    }


    private function assembleChunks(string $transferId, string $chunkPath, string $uploadName, int $expectedSize)
    {
        // Get the temp file record to get the correct upload name
        $tempFile = TempFile::where('folder', $transferId)->first();
        $finalUploadName = $uploadName ?: ($tempFile ? $tempFile->original_name : 'unknown_file');

        // Generate final filename
        $filename = Str::uuid() . '.' . pathinfo($finalUploadName, PATHINFO_EXTENSION);
        $finalPath = "{$this->tempPath}/{$transferId}/{$filename}";

        // Create final directory
        $this->disk->makeDirectory("{$this->tempPath}/{$transferId}");

        // Get all chunk files and sort by offset
        $chunks = $this->disk->files($chunkPath);
        $chunks = collect($chunks)
            ->filter(fn($file) => str_contains($file, 'chunk_'))
            ->sort(function ($a, $b) use ($chunkPath) {
                $offsetA = (int) str_replace($chunkPath . '/chunk_', '', $a);
                $offsetB = (int) str_replace($chunkPath . '/chunk_', '', $b);
                return $offsetA <=> $offsetB;
            })
            ->values()
            ->all();

        // Assemble chunks into final file
        $assembledContent = '';
        foreach ($chunks as $chunkFile) {
            $assembledContent .= $this->disk->get($chunkFile);
        }

        // Store assembled file
        $this->disk->put($finalPath, $assembledContent);

        // Verify file size
        $actualSize = $this->disk->size($finalPath);
        if ($actualSize !== $expectedSize) {
            $this->disk->delete($finalPath);
            $this->disk->deleteDirectory($chunkPath);
            throw new \Exception("File size mismatch: expected {$expectedSize}, got {$actualSize}");
        }

        // Try to detect mime type
        $mimeType = null;
        try {
            $fullPath = $this->disk->path($finalPath);
            if (file_exists($fullPath)) {
                $mimeType = mime_content_type($fullPath);
            }
        } catch (\Exception $e) {
            // Fallback to guessing from extension
            $extension = pathinfo($finalUploadName, PATHINFO_EXTENSION);
            $mimeType = $this->getMimeTypeFromExtension($extension);
        }

        // Update temp file record
        if ($tempFile) {
            $tempFile->update([
                'original_name' => $finalUploadName,
                'filename' => $filename,
                'path' => $finalPath,
                'mime_type' => $mimeType,
                'size' => $actualSize,
                'is_chunked' => false,
            ]);
        }

        $this->disk->deleteDirectory($chunkPath);

        // Return the transfer ID as plain text
        return response($transferId, 200, [
            'Content-Type' => 'text/plain'
        ]);
    }

    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}
