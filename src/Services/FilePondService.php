<?php

namespace MohamedGaldi\ViltFilepond\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use MohamedGaldi\ViltFilepond\Models\File;

class FilePondService
{
    public function storeTempFile(UploadedFile $file): TempFile
    {
        $folder = uniqid();
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = config('vilt-filepond.temp_path') . '/' . $folder . '/' . $filename;

        Storage::disk(config('vilt-filepond.storage_disk'))->putFileAs(
            config('vilt-filepond.temp_path') . '/' . $folder,
            $file,
            $filename
        );

        return TempFile::create([
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'folder' => $folder,
        ]);
    }

    public function moveTempFileToModel($model, string $folder, string $collection = 'default', int $order = 0): ?File
    {
        $tempFile = TempFile::where('folder', $folder)->first();

        if (!$tempFile) {
            return null;
        }

        // Generate new filename with model info
        $modelName = strtolower(class_basename($model));
        $filename = $modelName . '_' . $model->id . '_' . Str::uuid() . '.' . pathinfo($tempFile->filename, PATHINFO_EXTENSION);
        $newPath = config('vilt-filepond.files_path') . '/' . $modelName . '/' . $model->id . '/' . $collection . '/' . $filename;

        // Move file to permanent location
        Storage::disk(config('vilt-filepond.storage_disk'))->move($tempFile->path, $newPath);

        // Create file record
        $file = $model->files()->create([
            'original_name' => $tempFile->original_name,
            'filename' => $filename,
            'path' => 'storage/' . $newPath,
            'mime_type' => $tempFile->mime_type,
            'size' => $tempFile->size,
            'collection' => $collection,
            'order' => $order,
        ]);

        // Delete temp file record and folder
        Storage::disk(config('vilt-filepond.storage_disk'))->deleteDirectory(
            config('vilt-filepond.temp_path') . '/' . $tempFile->folder
        );
        $tempFile->delete();

        return $file;
    }

    public function deleteTempFile(string $folder): bool
    {
        $tempFile = TempFile::where('folder', $folder)->first();

        if ($tempFile) {
            Storage::disk(config('vilt-filepond.storage_disk'))->deleteDirectory(
                config('vilt-filepond.temp_path') . '/' . $tempFile->folder
            );
            $tempFile->delete();
            return true;
        }

        return false;
    }

    public function handleFileUploads($model, array $tempFolders, string $collection = 'default'): void
    {
        foreach ($tempFolders as $index => $folder) {
            $this->moveTempFileToModel($model, $folder, $collection, $index);
        }
    }

    public function handleFileUpdates($model, array $tempFolders, array $removedFiles = [], string $collection = 'default'): void
    {
        // Remove deleted files
        if (!empty($removedFiles)) {
            File::whereIn('id', $removedFiles)->each(function ($file) {
                $file->delete();
            });
        }

        // Add new files
        $existingFilesCount = $model->files()->where('collection', $collection)->count();
        foreach ($tempFolders as $index => $folder) {
            $this->moveTempFileToModel($model, $folder, $collection, $existingFilesCount + $index);
        }
    }
}
