<?php

declare(strict_types=1);

namespace Minilytics\Tracking;

/** Coarse browser, OS and device read from a User-Agent header. The raw header is never stored. */
final class UserAgent
{
    public function __construct(public readonly string $value) {}

    /** Why the request looks automated, or null for a regular browser. */
    public function botReason(): ?string
    {
        if ($this->value === '') {
            return 'missing_user_agent';
        }
        return preg_match('/bot|crawler|spider|slurp|facebookexternalhit|preview|headless|lighthouse|pingdom|uptimerobot|curl|wget/i', $this->value) ? 'known_bot_user_agent' : null;
    }

    public function browser(): string
    {
        $ua = $this->value;
        if (preg_match('/edg(?:e|a|ios)?\//i', $ua)) {
            return 'Microsoft Edge';
        }
        if (preg_match('/opr\//i', $ua) || stripos($ua, 'opera') !== false) {
            return 'Opera';
        }
        if (stripos($ua, 'samsungbrowser') !== false) {
            return 'Samsung Internet';
        }
        if (stripos($ua, 'firefox') !== false || stripos($ua, 'fxios') !== false) {
            return 'Firefox';
        }
        if (stripos($ua, 'crios') !== false) {
            return 'Chrome';
        }
        if (stripos($ua, 'chrome') !== false || stripos($ua, 'chromium') !== false) {
            return 'Chrome';
        }
        if (stripos($ua, 'safari') !== false) {
            return 'Safari';
        }
        return 'Other';
    }

    public function os(): string
    {
        $ua = $this->value;
        if (stripos($ua, 'cros') !== false) {
            return 'Chrome OS';
        }
        if (stripos($ua, 'windows') !== false) {
            return 'Windows';
        }
        if (stripos($ua, 'android') !== false) {
            return 'Android';
        }
        if (preg_match('/iphone|ipad|ipod/i', $ua)) {
            return 'iOS';
        }
        if (stripos($ua, 'mac os') !== false || stripos($ua, 'macintosh') !== false) {
            return 'macOS';
        }
        if (stripos($ua, 'linux') !== false) {
            return 'Linux';
        }
        return 'Other';
    }

    public function device(): string
    {
        $ua = $this->value;
        if (preg_match('/ipad|tablet|kindle|silk\//i', $ua) || (stripos($ua, 'android') !== false && !preg_match('/mobile/i', $ua))) {
            return 'Tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android/i', $ua)) {
            return 'Mobile';
        }
        return 'Desktop';
    }
}
