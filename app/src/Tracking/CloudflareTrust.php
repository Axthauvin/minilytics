<?php

declare(strict_types=1);

namespace Minilytics\Tracking;

use Minilytics\Database\Database;

/**
 * Whether this server sits behind Cloudflare, so the visitor's IP address is
 * read from the CF-Connecting-IP header. Anyone can send that header, so it
 * is off unless an administrator turns it on in Settings → Tracking, or the
 * MINILYTICS_TRUST_CLOUDFLARE environment variable enables it.
 */
final class CloudflareTrust
{
    public static function isEnabled(): bool
    {
        return self::isForcedByEnvironment() || !empty(self::settings()['trust_cloudflare']);
    }

    /** The environment variable overrides the dashboard setting. */
    public static function isForcedByEnvironment(): bool
    {
        return filter_var(getenv('MINILYTICS_TRUST_CLOUDFLARE'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function save(bool $enabled): void
    {
        $settings = ['trust_cloudflare' => $enabled] + self::settings();
        file_put_contents(self::path(), json_encode($settings, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function settings(): array
    {
        $settings = is_file(self::path()) ? json_decode((string) file_get_contents(self::path()), true) : [];
        return is_array($settings) ? $settings : [];
    }

    private static function path(): string
    {
        return Database::getDataDir() . '/tracking.json';
    }
}
