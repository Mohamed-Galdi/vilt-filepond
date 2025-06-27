<?php

namespace MohamedGaldi\ViltFilepond\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use MohamedGaldi\ViltFilepond\Models\File;

trait HasFiles
{
   protected static function bootHasFiles()
    {
        static::deleting(function ($model) {
            // Get all files before deletion and delete them
            $files = $model->files()->get();
            
            foreach ($files as $file) {
                $file->delete();
            }
        });
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable')->orderBy('order');
    }

    public function getFilesByCollection(string $collection = 'default')
    {
        return $this->files()->where('collection', $collection)->get();
    }

    public function getFirstFile(string $collection = 'default'): ?File
    {
        return $this->files()->where('collection', $collection)->first();
    }

    public function hasFiles(string $collection = 'default'): bool
    {
        return $this->files()->where('collection', $collection)->exists();
    }

    public function deleteFiles(string $collection = 'default'): void
    {
        $this->files()->where('collection', $collection)->each(function ($file) {
            $file->delete();
        });
    }

    public function addFile(string $path, array $attributes = []): File
    {
        return $this->files()->create(array_merge([
            'path' => $path,
        ], $attributes));
    }
}
