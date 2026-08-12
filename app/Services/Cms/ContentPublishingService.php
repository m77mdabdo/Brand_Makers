<?php

namespace App\Services\Cms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Image handling for content that stores a path on its OWN column
 * (a service's cover, a country's flag, a testimonial's avatar).
 *
 * Distinct from MediaLibraryService, which creates independent, listable
 * Media records. Keeping them apart matters: deleting a service must take
 * its cover with it, but must never touch the media library.
 */
class ContentPublishingService
{
    /**
     * Stores under storage/app/public/{directory} and returns the relative
     * disk path.
     *
     * The filename comes from Laravel's store(), which generates a random
     * one — the original is never used. An attacker-supplied filename is
     * both a path-traversal vector and a way to overwrite someone else's
     * upload by guessing a name.
     */
    public function storeImage(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    /**
     * Stores the new image and removes the one it replaces, so an admin
     * swapping a cover ten times leaves one file on disk rather than ten.
     */
    public function replaceImage(UploadedFile $file, string $directory, ?string $previousPath): string
    {
        $path = $this->storeImage($file, $directory);

        // Deleted AFTER the successful store: if the write fails, the
        // record keeps a path that still points at a real file.
        $this->deleteImage($previousPath);

        return $path;
    }

    public function deleteImage(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
