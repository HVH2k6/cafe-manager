<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores an uploaded image on the public disk and keeps its path in `image_url`.
 * Full URLs (e.g. an image hosted elsewhere) are also accepted in `image_url`.
 */
trait HasImage
{
    public static function bootHasImage(): void
    {
        static::deleted(fn (self $model) => $model->deleteImageFile());
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image_url)) {
            return null;
        }

        return Str::startsWith($this->image_url, ['http://', 'https://'])
            ? $this->image_url
            : Storage::disk('public')->url($this->image_url);
    }

    /**
     * Apply the image part of a form: a new upload replaces the old file,
     * `$remove` clears it. Saving the model is left to the caller.
     */
    public function applyImage(?UploadedFile $upload, bool $remove, string $directory): void
    {
        if ($upload) {
            $this->deleteImageFile();
            $this->image_url = $upload->store($directory, 'public');
        } elseif ($remove) {
            $this->deleteImageFile();
            $this->image_url = null;
        }
    }

    private function deleteImageFile(): void
    {
        $path = $this->getOriginal('image_url');

        if (filled($path) && ! Str::startsWith($path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($path);
        }
    }
}
