<?php

namespace MohamedGaldi\ViltFilepond\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TempFile extends Model
{
    protected $fillable = [
        'original_name',
        'filename',
        'path',
        'mime_type',
        'size',
        'folder'
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($tempFile) {
            // Delete the file from storage when the model is deleted
            if (Storage::disk(config('vilt-filepond.storage_disk'))->exists($tempFile->path)) {
                Storage::disk(config('vilt-filepond.storage_disk'))->delete($tempFile->path);
            }
        });
    }
}
