<?php

declare(strict_types=1);

namespace Minilytics\Tests\Importers;

use Minilytics\Database\Database;
use Minilytics\Importers\ImporterRegistry;
use Minilytics\Importers\UmamiImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/** Imports tests/php/fixtures/umami: two visits, three events, one with event data. */
final class UmamiImporterTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/umami';

    public function testInspectDetectsTheWebsite(): void
    {
        $inspection = (new UmamiImporter())->inspect(self::FIXTURES);

        $this->assertTrue($inspection['has_event_data']);
        $this->assertSame('blog.example.com', $inspection['detected_host']);
        $this->assertSame('blog', $inspection['suggested_site_id']);
    }

    public function testImportReportsWhatItImported(): void
    {
        $result = (new UmamiImporter())->import(self::FIXTURES, 'umami_report');

        $this->assertSame(3, $result['total_imported']);
        $this->assertSame(2, $result['pageviews']);
        $this->assertSame(1, $result['custom_events']);
        $this->assertSame(2, $result['sessions']);
        $this->assertSame('2026-01-05 10:00:00', $result['date_start']);
        $this->assertSame('2026-01-06 09:30:00', $result['date_end']);
    }

    public function testImportCreatesTheWebsiteWithTheDetectedDomain(): void
    {
        (new UmamiImporter())->import(self::FIXTURES, 'umami_site', ['name' => 'My blog']);

        $site = array_values(array_filter(Database::getAvailableSites(), static fn(array $s): bool => $s['id'] === 'umami_site'))[0] ?? null;
        $this->assertSame('My blog', $site['name'] ?? null);
        $this->assertSame('blog.example.com', $site['domain'] ?? null);
    }

    public function testPageviewsAreStoredInTheMinilyticsFormat(): void
    {
        (new UmamiImporter())->import(self::FIXTURES, 'umami_pageviews');

        [$home, $pricing] = $this->storedEvents('umami_pageviews');
        $this->assertSame('pageview', $home['name']);
        $this->assertSame('/', $home['data']['path']);
        $this->assertSame('https://google.com', $home['data']['referrer']);
        $this->assertSame('newsletter', $home['data']['utm_source']);
        $this->assertSame('Chrome', $home['data']['browser']);
        $this->assertSame('macOS', $home['data']['os']);
        $this->assertSame('France', $home['data']['country']);
        $this->assertSame('fr', $home['data']['language']);
        $this->assertSame('https://blog.example.com/pricing?plan=pro', $pricing['data']['url']);
    }

    public function testCustomEventsKeepTheirTypedEventData(): void
    {
        (new UmamiImporter())->import(self::FIXTURES, 'umami_events');

        $signup = $this->storedEvents('umami_events')[2];
        $this->assertSame('signup', $signup['name']);
        $this->assertSame('pro', $signup['data']['plan']);
        $this->assertSame(5, $signup['data']['seats']);
        $this->assertTrue($signup['data']['trial']);
        $this->assertSame('Safari', $signup['data']['browser']);
        $this->assertSame('Mobile', $signup['data']['device']);
    }

    public function testImportsAZipArchive(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not installed.');
        }
        $archive = tempnam(sys_get_temp_dir(), 'umami');
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::OVERWRITE);
        $zip->addFile(self::FIXTURES . '/website_event.csv', 'export/website_event.csv');
        $zip->addFile(self::FIXTURES . '/event_data.csv', 'export/event_data.csv');
        $zip->close();

        try {
            $this->assertSame(3, (new UmamiImporter())->import($archive, 'umami_zip')['total_imported']);
        } finally {
            @unlink($archive);
        }
    }

    public function testOnlyUmamiIsAvailable(): void
    {
        $available = array_column(array_filter(ImporterRegistry::getProvidersList(), static fn(array $p): bool => $p['is_available']), 'id');

        $this->assertSame(['umami'], array_values($available));
    }

    #[DataProvider('browsers')]
    public function testNormalizesBrowserNames(?string $raw, string $expected): void
    {
        $this->assertSame($expected, (new UmamiImporter())->normalizeBrowser($raw));
    }

    /** @return iterable<array{?string, string}> */
    public static function browsers(): iterable
    {
        yield [null, 'Unknown'];
        yield ['\N', 'Unknown'];
        yield ['ios', 'Safari'];
        yield ['crios', 'Chrome (iOS)'];
        yield ['edge-chromium', 'Edge'];
        yield ['chrome', 'Chrome'];
        yield ['samsung', 'Samsung Internet'];
        yield ['brave', 'Brave'];
    }

    /** @return list<array<string, mixed>> */
    private function storedEvents(string $siteId): array
    {
        $result = Database::getConnection($siteId)->query('SELECT action FROM user_activity ORDER BY timestamp, id');
        $events = [];
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $events[] = json_decode($row['action'], true);
        }
        return $events;
    }
}
