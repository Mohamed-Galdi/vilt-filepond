<?php

namespace MohamedGaldi\ViltFilepond\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class FilePondController extends Controller
{
    protected FilePondService $filePondService;

    public function __construct(FilePondService $filePondService)
    {
        $this->filePondService = $filePondService;
    }

    public function upload(Request $request)
    {
        $request->validate([
            'filepond' => [
                'required',
                'file',
                'max:' . (config('filepond.max_file_size') / 1024), // Convert to KB for validation
                function ($attribute, $value, $fail) {
                    if (!in_array($value->getMimeType(), config('filepond.allowed_types'))) {
                        $fail('The file type is not allowed.');
                    }
                },
            ],
        ]);

        try {
            $tempFile = $this->filePondService->storeTempFile($request->file('filepond'));
            return response()->json($tempFile->folder);
        } catch (\Exception $e) {
            return response()->json(['error' => 'File upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function revert(Request $request, string $folder)
    {
        try {
            $this->filePondService->deleteTempFile($folder);
            return response()->json(['message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'File deletion failed: ' . $e->getMessage()], 500);
        }
    }

    public function load(Request $request, string $fileId)
    {
        // This method is used for loading existing files in FilePond
        // You can implement this based on your needs
        return response()->json(['message' => 'Load method not implemented yet']);
    }
}
