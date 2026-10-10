<?php

declare(strict_types=1);

namespace Minilytics\Tracking;

use Minilytics\Database\Database;

/** Accepts events only from a website's allowed domains, signed with its key. */
final class TrackingAccess
{
    /**
     * Returns the registered website the payload is for, after checking where
     * it comes from and its key.
     *
     * @return array<string, mixed>
     */
    public static function authorize(TrackingPayload $payload, string $origin): array
    {
        $site = Database::trackingSite($payload->siteId);
        if (!$site) {
            throw new TrackingException('Unknown website.', 404);
        }
        $allowed = array_values(array_filter(array_map([Database::class, 'normalizeHost'], (array) ($site['allowed_domains'] ?? []))));
        if (!$allowed) {
            throw new TrackingException('No allowed domains are configured for this website.', 403);
        }
        $originHost = self::host($origin);
        if ($originHost === '' || !in_array($originHost, $allowed, true)) {
            throw new TrackingException('Origin is not authorized for this website.', 403);
        }
        if (!hash_equals((string) ($site['write_key'] ?? ''), $payload->siteKey)) {
            throw new TrackingException('Invalid tracking key.', 403);
        }
        return $site;
    }

    /** Host name of a URL, or of a bare host. */
    public static function host(string $value): string
    {
        return Database::normalizeHost((string) (parse_url($value, PHP_URL_HOST) ?: $value));
    }
}
