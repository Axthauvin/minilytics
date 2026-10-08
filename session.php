<?php

declare(strict_types=1);

/** Server-side, cookie-free visitor identity helpers. */
function minilyticsTrackingSecret(): string
{
    $configured = getenv('MINILYTICS_TRACKING_SECRET');
    if (is_string($configured) && strlen($configured) >= 32) {
        return $configured;
    }

    $file = __DIR__ . '/data/.tracking-secret';
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

function generateVisitorId(string $websiteId, string $ip, string $userAgent): string
{
    // Rotate monthly: there is no durable, cross-period identifier.
    $material = implode("\n", [$websiteId, gmdate('Y-m'), $ip, $userAgent]);
    return substr(hash_hmac('sha256', $material, minilyticsTrackingSecret()), 0, 32);
}

function generateSessionId(): string
{
    return bin2hex(random_bytes(16));
}
