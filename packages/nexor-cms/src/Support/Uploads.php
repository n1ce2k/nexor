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

    public static function disk(): string
    {
        return config('nexor.storage.disk', self::DISK);
    }

    /**
     * @return string|null The path to persist, or null when the file was removed.
     */
    public static function handle(Request $request, string $field, ?string $current, string $directory): ?string
    {
        if ($request->hasFile($field)) {
            self::delete($current);

            return $request->file($field)->store($directory, self::disk());
        }

        if ($request->boolean($field.'_remove')) {
            self::delete($current);

            return null;
        }

        return $current;
    }

    /**
     * Public URL of a stored file, or null when there is nothing stored.
     */
    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk(self::disk())->url($path) : null;
    }

    public static function delete(?string $path): void
    {
        if ($path && Storage::disk(self::disk())->exists($path)) {
            Storage::disk(self::disk())->delete($path);
        }
    }
}
