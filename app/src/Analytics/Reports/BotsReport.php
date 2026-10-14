<?php

declare(strict_types=1);

namespace Minilytics\Analytics\Reports;

use Minilytics\Analytics\ReportQuery;

/** Requests rejected as bot traffic. */
final class BotsReport
{
    public function __construct(private readonly ReportQuery $query) {}

    /**
     * Overview of the rejected requests: totals, why they were rejected, who
     * sent them, when, and the most recent ones.
     *
     * @return array{
     *     total: int,
     *     sources: int,
     *     reasons: list<array{reason: string, count: int}>,
     *     agents: list<array{user_agent: string, count: int}>,
     *     origins: list<array{origin: string, count: int}>,
     *     recent: list<array<string, mixed>>
     * }
     */
    public function summary(): array
    {
        $totals = $this->rows('SELECT COUNT(*) AS total, COUNT(DISTINCT ip_hash) AS sources FROM bot_activity WHERE timestamp BETWEEN :start AND :end')[0] ?? [];
        return [
            'total' => (int) ($totals['total'] ?? 0),
            'sources' => (int) ($totals['sources'] ?? 0),
            'reasons' => $this->top('reason'),
            'agents' => $this->top('user_agent'),
            'origins' => $this->top('origin'),
            'recent' => $this->recent(),
        ];
    }

    /** The 50 most recent requests rejected as bot traffic. */
    public function recent(): array
    {
        return $this->rows('SELECT reason, user_agent, origin, timestamp FROM bot_activity WHERE timestamp BETWEEN :start AND :end ORDER BY id DESC LIMIT 50');
    }

    /** @return list<array<string, mixed>> the 8 most frequent values of a column, with their counts */
    private function top(string $column): array
    {
        $rows = $this->rows("SELECT {$column} AS value, COUNT(*) AS count FROM bot_activity WHERE timestamp BETWEEN :start AND :end AND {$column} IS NOT NULL AND {$column} <> '' GROUP BY {$column} ORDER BY count DESC LIMIT 8");
        return array_map(static fn(array $row): array => [$column => (string) $row['value'], 'count' => (int) $row['count']], $rows);
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql): array
    {
        return $this->query->rows($sql, [':start' => $this->query->period->startText(), ':end' => $this->query->period->endText()]);
    }
}
