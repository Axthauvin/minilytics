<?php

declare(strict_types=1);

namespace Minilytics\Tracking;

use Minilytics\Database\Database;
use RuntimeException;

/** Server-side, cookie-free visitor identity helpers. */
final class VisitorIdentity
{
    public static function trackingSecret(): string
    {
        $configured = getenv('MINILYTICS_TRACKING_SECRET');
        if (is_string($configured) && strlen($configured) >= 32) {
            return $configured;
        }

        $file = Database::getDataDir() . '/.tracking-secret';
        $stored = @file_get_contents($file);
        if (is_string($stored) && strlen(trim($stored)) >= 32) {
            return trim($stored);
        }

        $secret = bin2hex(random_bytes(32));
        if (@file_put_contents($file, $secret . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist the Minilytics tracking secret. Set MINILYTICS_TRACKING_SECRET or make the data directory writable.');
        }
        @chmod($file, 0600);
        return $secret;
    }

    public static function visitorId(string $websiteId, string $ip, string $userAgent): string
    {
        // Rotate monthly: there is no durable, cross-period identifier.
        $material = implode("\n", [$websiteId, gmdate('Y-m'), $ip, $userAgent]);
        return substr(hash_hmac('sha256', $material, self::trackingSecret()), 0, 32);
    }

    public static function sessionId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
