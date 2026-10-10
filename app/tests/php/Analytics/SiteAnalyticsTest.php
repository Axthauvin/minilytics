<?php

declare(strict_types=1);

namespace Minilytics\Tests\Analytics;

use Minilytics\Analytics\Period;
use Minilytics\Analytics\SiteAnalytics;
use Minilytics\Database\Database;
use PHPUnit\Framework\TestCase;

final class SiteAnalyticsTest extends TestCase
{
    private int $now;

    protected function setUp(): void
    {
        $this->now = time();
        Database::getConnection('test_site')->exec('DELETE FROM user_activity');
    }

    public function testSummaryCountsVisitorsSessionsAndPageviews(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/'], 3600);
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing'], 3500);
        $this->record('s2', 'v1', 'pageview', ['path' => '/'], 1800);
        $this->record('s3', 'v2', 'signup', [], 600);

        $summary = $this->analytics('7d')->summary();

        $this->assertSame(2, $summary['visitors']);
        $this->assertSame(3, $summary['sessions']);
        $this->assertSame(3, $summary['pageviews']);
        $this->assertSame(4, $summary['events']);
    }

    public function testVisitsWithASingleActionCountAsBounces(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/'], 3600);
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing'], 3540);
        $this->record('s2', 'v2', 'pageview', ['path' => '/'], 600);

        $summary = $this->analytics('7d')->summary();

        $this->assertSame(50.0, $summary['bounce_rate']);
        $this->assertSame(30.0, $summary['avg_duration_seconds']);
    }

    public function testEventsOutsideThePeriodAreIgnored(): void
    {
        $this->record('old', 'v1', 'pageview', ['path' => '/'], 10 * 86400);
        $this->record('new', 'v2', 'pageview', ['path' => '/'], 3600);

        $this->assertSame(1, $this->analytics('7d')->summary()['visitors']);
    }

    public function testSummaryComparesWithThePreviousPeriod(): void
    {
        $this->record('before', 'v1', 'pageview', ['path' => '/'], 8 * 86400);
        $this->record('now1', 'v2', 'pageview', ['path' => '/'], 3600);
        $this->record('now2', 'v3', 'pageview', ['path' => '/'], 1800);

        $this->assertSame('+1', $this->analytics('7d')->summary()['deltas']['visitors']);
    }

    public function testTopPagesAreSortedByViews(): void
    {
        $this->record('s1', 'v1', 'pageview', ['path' => '/pricing', 'title' => 'Pricing'], 3600);
        $this->record('s2', 'v2', 'pageview', ['path' => '/pricing', 'title' => 'Pricing'], 3000);
        $this->record('s2', 'v2', 'pageview', ['path' => '/'], 2900);

        $pages = $this->analytics('7d')->topPages();

        $this->assertSame(['/pricing', '/'], array_column($pages, 'path'));
        $this->assertSame('Pricing', $pages[0]['title']);
        $this->assertSame(2, $pages[0]['views']);
        $this->assertSame(66.7, $pages[0]['percentage']);
    }

    private function analytics(string $range): SiteAnalytics
    {
        return SiteAnalytics::open('test_site', Period::from($range, now: $this->now));
    }

    /** Stores an event the way track.php does, `$secondsAgo` before now. */
    private function record(string $sessionId, string $visitorId, string $name, array $data, int $secondsAgo): void
    {
        $stmt = Database::getConnection('test_site')->prepare('INSERT INTO user_activity (session_id, visitor_id, action, timestamp) VALUES (:session, :visitor, :action, :timestamp)');
        $stmt->bindValue(':session', $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(':visitor', $visitorId, SQLITE3_TEXT);
        $stmt->bindValue(':action', (string) json_encode(['name' => $name, 'data' => $data]), SQLITE3_TEXT);
        $stmt->bindValue(':timestamp', gmdate('Y-m-d H:i:s', $this->now - $secondsAgo), SQLITE3_TEXT);
        $stmt->execute();
    }
}
