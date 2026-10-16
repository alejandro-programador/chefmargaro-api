<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * URLs for files on the public disk (combos, products, payments, etc.).
 *
 * Production (webapi.chefmargaro.com): /storage/... via php artisan storage:link
 * XAMPP subdirectory: set APP_STORAGE_XAMPP_PATH=true → /storage/app/public/...
 */
class PublicStorageUrl
{
    /**
     * Base URL where Laravel serves public storage (the API host in production).
     */
    public static function publicBaseUrl(): string
    {
        $base = config('app.url');
        $base = rtrim((string) $base, '/');

        if (static::isLocalHostUrl($base) && ! app()->runningInConsole()) {
            $request = request();
            if ($request) {
                $base = $request->getSchemeAndHttpHost();
            }
        }

        if (str_ends_with($base, '/api')) {
            $base = substr($base, 0, -4);
        }

        return $base;
    }

    protected static function isLocalHostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function usesXamppPath(): bool
    {
        return (bool) config('app.storage_xampp_path', false);
    }

    public static function relativeUrl(string $relativePathOnPublicDisk): string
    {
        $path = ltrim($relativePathOnPublicDisk, '/');

        if (static::usesXamppPath()) {
            return '/storage/app/public/'.$path;
        }

        // Build URL from config only — do not resolve Storage::disk('public') here.
        // On shared hosting, booting the local disk may try to mkdir storage/app/public
        // and fail with UnableToCreateDirectory when serializing API JSON.
        $baseUrl = (string) config('filesystems.disks.public.url', '');

        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        return '/storage/'.$path;
    }

    public static function absoluteUrl(string $relativePathOnPublicDisk): string
    {
        $relative = static::relativeUrl($relativePathOnPublicDisk);

        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return $relative;
        }

        return static::publicBaseUrl().(str_starts_with($relative, '/') ? $relative : '/'.$relative);
    }

    public static function diskPathFromStored(?string $imageUrl): ?string
    {
        if ($imageUrl === null || trim($imageUrl) === '') {
            return null;
        }

        $value = trim($imageUrl);

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = is_string($path) ? $path : $value;
        }

        $value = '/'.ltrim($value, '/');
        $value = str_replace('/api/storage/app/public/', '/storage/app/public/', $value);
        $value = str_replace('/api/storage/', '/storage/', $value);

        foreach (['/storage/app/public/', '/storage/'] as $prefix) {
            if (Str::startsWith($value, $prefix)) {
                $path = Str::after($value, $prefix);

                return $path !== '' ? $path : null;
            }
        }

        if (! str_contains($value, '/')) {
            return $value;
        }

        return null;
    }

    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);
        $trimmed = str_replace('/storage/app/public/app/public/', '/storage/app/public/', $trimmed);

        $diskPath = static::diskPathFromStored($trimmed);
        if ($diskPath !== null) {
            return static::absoluteUrl($diskPath);
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return static::publicBaseUrl().'/'.ltrim($trimmed, '/');
    }
}
