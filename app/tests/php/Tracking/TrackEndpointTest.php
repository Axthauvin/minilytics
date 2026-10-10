<?php

declare(strict_types=1);

namespace Minilytics\Tests\Tracking;

use Minilytics\Database\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Sends real HTTP requests to track.php through PHP's built-in server, so
 * these tests keep passing however the endpoint is organised internally.
 */
final class TrackEndpointTest extends TestCase
{
    private const ORIGIN = 'https://example.com';
    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /** @var resource|null */
    private static $server = null;
    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        $command = [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', dirname(__DIR__, 3)];
        $env = getenv() + ['MINILYTICS_DATA_DIR' => getenv('MINILYTICS_DATA_DIR')];
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$server = proc_open($command, [['file', $null, 'r'], ['file', $null, 'w'], ['file', $null, 'w']], $pipes, null, $env) ?: null;
        self::$baseUrl = "http://127.0.0.1:{$port}";
        for ($i = 0; $i < 50; $i++) {
            if ($socket = @fsockopen('127.0.0.1', $port)) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('The PHP built-in server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    protected function setUp(): void
    {
        $db = Database::getConnection('test_site');
        $db->exec('DELETE FROM user_activity');
        $db->exec('DELETE FROM bot_activity');
        $db->exec('DELETE FROM rate_limits');
    }

    public function testOnlyPostIsAccepted(): void
    {
        $this->assertSame(405, $this->request('GET')['status']);
    }

    public function testRejectsAPayloadWithoutNameAndData(): void
    {
        $this->assertSame(400, $this->track(['site_id' => 'test_site', 'site_key' => 'test-key'])['status']);
    }

    public function testRejectsAnUnknownWebsite(): void
    {
        $this->assertSame(404, $this->track($this->event(['site_id' => 'nope']))['status']);
    }

    public function testRejectsAnOriginOutsideTheAllowedDomains(): void
    {
        $this->assertSame(403, $this->track($this->event(), ['Origin: https://evil.example'])['status']);
        $this->assertSame([], $this->storedEvents());
    }

    public function testRejectsAWrongTrackingKey(): void
    {
        $this->assertSame(403, $this->track($this->event(['site_key' => 'wrong']))['status']);
        $this->assertSame([], $this->storedEvents());
    }

    public function testStoresAPageviewWithoutQueryStringOrSensitiveFields(): void
    {
        $response = $this->track($this->event(['data' => [
            'path' => 'pricing',
            'url' => 'https://example.com/pricing?email=a@b.c',
            'search' => '?email=a@b.c',
            'referrer' => 'https://www.google.com/search?q=minilytics',
            'email' => 'visitor@example.com',
            'plan' => 'pro',
        ]]));

        $this->assertSame(200, $response['status']);
        $events = $this->storedEvents();
        $this->assertCount(1, $events);
        $data = $events[0]['action']['data'];
        $this->assertSame('pageview', $events[0]['action']['name']);
        $this->assertSame('/pricing', $data['path']);
        $this->assertSame('www.google.com', $data['referrer']);
        $this->assertSame('pro', $data['plan']);
        $this->assertSame('Chrome', $data['browser']);
        $this->assertSame('strict', $data['_ml_tracking_mode']);
        foreach (['url', 'search', 'email'] as $removed) {
            $this->assertArrayNotHasKey($removed, $data);
        }
    }

    public function testUsesTheCountryFromTheProxyHeader(): void
    {
        $this->track($this->event(), ['CF-IPCountry: FR']);

        $this->assertSame('FR', $this->storedEvents()[0]['action']['data']['country_code']);
    }

    public function testStrictModeGroupsEventsFromTheSameVisitorWithoutClientIds(): void
    {
        $this->track($this->event(['session_id' => 'client-session', 'visitor_id' => 'client-visitor']));
        $this->track($this->event(['name' => 'signup']));

        [$first, $second] = $this->storedEvents();
        $this->assertSame($first['session_id'], $second['session_id']);
        $this->assertSame($first['visitor_id'], $second['visitor_id']);
        $this->assertNotSame('client-session', $first['session_id']);
        $this->assertNotSame('client-visitor', $first['visitor_id']);
    }

    public function testEnrichedModeKeepsTheClientIds(): void
    {
        $this->track($this->event(['session_id' => 'session-1', 'visitor_id' => 'visitor-1', 'data' => ['path' => '/', 'tracking_mode' => 'enriched']]));

        $event = $this->storedEvents()[0];
        $this->assertSame('session-1', $event['session_id']);
        $this->assertSame('visitor-1', $event['visitor_id']);
    }

    public function testBotsAreLoggedSeparately(): void
    {
        $response = $this->track($this->event(), ['User-Agent: Googlebot/2.1 (+http://www.google.com/bot.html)']);

        $this->assertSame(['ignored' => 'bot'], $response['body']);
        $this->assertSame([], $this->storedEvents());
        $this->assertSame(1, (int) Database::getConnection('test_site')->querySingle('SELECT COUNT(*) FROM bot_activity'));
    }

    public function testInternalTrafficIsIgnored(): void
    {
        $response = $this->track($this->event(), ['CF-Connecting-IP: 10.0.0.99']);

        $this->assertSame(['ignored' => 'internal'], $response['body']);
        $this->assertSame([], $this->storedEvents());
    }

    /** @param array<string, mixed> $overrides */
    private function event(array $overrides = []): array
    {
        return $overrides + ['site_id' => 'test_site', 'site_key' => 'test-key', 'name' => 'pageview', 'data' => ['path' => '/']];
    }

    /**
     * @param list<string> $headers replace the defaults sharing their name
     * @return array{status: int, body: mixed}
     */
    private function track(array $payload, array $headers = []): array
    {
        $defaults = ['Origin' => 'Origin: ' . self::ORIGIN, 'User-Agent' => 'User-Agent: ' . self::BROWSER, 'CF-Connecting-IP' => 'CF-Connecting-IP: 192.168.1.20'];
        foreach ($headers as $header) {
            $defaults[strstr($header, ':', true)] = $header;
        }
        return $this->request('POST', (string) json_encode($payload), array_values($defaults));
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: mixed}
     */
    private function request(string $method, string $body = '', array $headers = []): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", ['Content-Type: application/json', ...$headers]),
            'content' => $body,
            'ignore_errors' => true,
        ]]);
        $response = file_get_contents(self::$baseUrl . '/track.php', false, $context);
        preg_match('/^HTTP\/\S+ (\d{3})/', $http_response_header[0] ?? '', $match);
        return ['status' => (int) ($match[1] ?? 0), 'body' => json_decode((string) $response, true)];
    }

    /** @return list<array{session_id: string, visitor_id: string, action: array<string, mixed>}> */
    private function storedEvents(): array
    {
        $result = Database::getConnection('test_site')->query('SELECT session_id, visitor_id, action FROM user_activity ORDER BY id');
        $events = [];
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $events[] = ['session_id' => $row['session_id'], 'visitor_id' => $row['visitor_id'], 'action' => json_decode($row['action'], true)];
        }
        return $events;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        return $port;
    }
}
