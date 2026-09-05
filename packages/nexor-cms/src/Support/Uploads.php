<?php

namespace Nexor\Cms\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles the "upload a new file / keep the old one / remove it" triad that every
 * admin form with a file field needs.
 */
class Uploads
{
    public const DISK = 'public';

    /**
     * @return string|null The path to persist, or null when the file was removed.
     */
    public static function handle(Request $request, string $field, ?string $current, string $directory): ?string
    {
        if ($request->hasFile($field)) {
            self::delete($current);

            return $request->file($field)->store($directory, self::DISK);
        }

        if ($request->boolean($field.'_remove')) {
            self::delete($current);

            return null;
        }

        return $current;
    }

    public static function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
