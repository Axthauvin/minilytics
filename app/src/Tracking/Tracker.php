<?php

declare(strict_types=1);

namespace Minilytics\Tracking;

use Minilytics\Database\Database;
use Throwable;

/**
 * Public ingestion for track.php: registered sites, per-site keys, allowed
 * origins only.
 */
final class Tracker
{
    /** @param array<string, mixed> $server the request's $_SERVER */
    public static function handle(array $server, string $body): TrackingResponse
    {
        try {
            $payload = TrackingPayload::fromJson($body);
            $origin = (string) ($server['HTTP_ORIGIN'] ?? $server['HTTP_REFERER'] ?? '');
            $site = TrackingAccess::authorize($payload, $origin);
            return new TrackingResponse(200, self::track($site, $payload, $server, TrackingAccess::host($origin)));
        } catch (TrackingException $e) {
            return new TrackingResponse($e->status, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Records the event, unless it comes from a bot or from the website's own team.
     *
     * @param array<string, mixed> $site
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private static function track(array $site, TrackingPayload $payload, array $server, string $originHost): array
    {
        $db = Database::getConnection($site['id']);
        $store = new ActivityStore($db);
        $userAgent = new UserAgent((string) ($server['HTTP_USER_AGENT'] ?? ''));
        $ip = self::clientIp($server);

        if (!(new RateLimiter($db))->allow($ip)) {
            throw new TrackingException('Rate limit exceeded.', 429);
        }
        if (($reason = $userAgent->botReason()) !== null) {
            $store->recordIgnored($reason, $userAgent->value, $originHost, $ip);
            return ['ignored' => 'bot'];
        }
        if (in_array($ip, (array) ($site['internal_ips'] ?? []), true)) {
            $store->recordIgnored('internal_traffic', $userAgent->value, $originHost, $ip);
            return ['ignored' => 'internal'];
        }

        $name = $payload->name();
        $data = EventData::sanitize($payload->data, $userAgent);
        try {
            $data = array_merge($data, VisitorLocation::resolve($server, $ip));
        } catch (Throwable $error) {
            error_log('[Minilytics] GeoIP enrichment failed: ' . $error->getMessage());
            $data = array_merge($data, ['country' => 'Unknown', 'country_code' => 'UN', 'region' => '', 'city' => '']);
        }

        if ($data['_ml_tracking_mode'] === 'strict') {
            // No identifier from the browser: a server-side key that rotates monthly.
            $visitor = VisitorIdentity::visitorId((string) $site['id'], $ip, $userAgent->value);
            $session = $store->activeSessionId($visitor) ?? VisitorIdentity::sessionId();
        } else {
            $session = $payload->sessionId ?: VisitorIdentity::sessionId();
            $visitor = $payload->visitorId ?: $session;
        }
        $store->record($session, $visitor, ['site_id' => $site['id'], 'name' => $name, 'data' => $data]);
        return ['success' => true];
    }

    /** @param array<string, mixed> $server */
    private static function clientIp(array $server): string
    {
        return trim(explode(',', (string) ($server['HTTP_CF_CONNECTING_IP'] ?? $server['REMOTE_ADDR'] ?? ''))[0]);
    }
}
