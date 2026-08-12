<?php

namespace App\Services\Cms;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The standalone media library.
 *
 * Distinct from ContentPublishingService, which writes a path onto a
 * parent record's own column. These are independent, listable Media rows
 * that outlive whatever referenced them — which is why deleting a service
 * must not touch them.
 */
class MediaLibraryService
{
    /**
     * The formats accepted, stated here as well as in the Form Request.
     *
     * NO SVG. An SVG is an executable document; storage/app/public is
     * served from this origin, so opening an uploaded SVG directly would
     * run any script it carries as same-origin code. There is no SVG
     * sanitiser in this project.
     *
     * @var array<int, string>
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    /** Kilobytes. Matches the Form Request's `max` rule. */
    public const MAX_KILOBYTES = 4096;

    public function upload(UploadedFile $file, ?string $altText, ?User $uploadedBy): Media
    {
        // store() generates a random filename; the client-supplied one is
        // never used. It is both a path-traversal vector and a way to
        // overwrite someone else's upload by guessing a name.
        $path = $file->store('media', 'public');

        return Media::create([
            'path' => $path,
            'disk' => 'public',
            // getMimeType() reads the file's actual content, not the
            // Content-Type header the client claimed.
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
            'alt_text' => $altText,
            'uploaded_by' => $uploadedBy?->id,
        ]);
    }

    /**
     * Removes the disk file AND the row, so nothing is orphaned on either
     * side. The file goes first: a row pointing at a missing file renders
     * a broken image, whereas a file with no row is invisible and
     * unreclaimable.
     */
    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->path);

        $media->delete();
    }
}
