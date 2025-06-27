<?php

namespace MohamedGaldi\ViltFilepond\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    protected $fillable = [
        'original_name',
        'filename',
        'path',
        'mime_type',
        'size',
        'fileable_type',
        'fileable_id',
        'collection',
        'order'
    ];

    protected $appends = ['url'];

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(config('vilt-filepond.storage_disk'))->url($this->getCleanPath());
    }

    /**
     * Get the clean path without the 'storage/' prefix
     */
    public function getCleanPath(): string
    {
        return ltrim(preg_replace('/^storage\//', '', $this->path), '/');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($file) {
            // Delete the file from storage when the model is deleted
            $cleanPath = $file->getCleanPath();

            if (Storage::disk(config('vilt-filepond.storage_disk'))->exists($cleanPath)) {
                Storage::disk(config('vilt-filepond.storage_disk'))->delete($cleanPath);
            }
        });
    }
}
