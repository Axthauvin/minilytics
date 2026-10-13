<?php

declare(strict_types=1);

namespace Minilytics\Analytics\Reports;

use Minilytics\Analytics\ReportQuery;
use Throwable;

/** Funnels, goals and journeys: the saved ones and how far visits went through them. */
final class FunnelsReport
{
    public function __construct(private readonly ReportQuery $query) {}

    /**
     * How many visits reached each step of a funnel, in order. A step is a page
     * (`pageview`) or a custom event; a visit counts for a step once it went
     * through every previous step first.
     *
     * @param list<array{type: string, value: string}> $steps
     * @return list<int>
     */
    public function progress(array $steps): array
    {
        $result = $this->query->db->prepare('SELECT session_id, action FROM user_activity WHERE timestamp >= :start AND timestamp <= :end' . $this->query->condition() . ' ORDER BY session_id, timestamp, id');
        $result->bindValue(':start', $this->query->period->startText(), SQLITE3_TEXT);
        $result->bindValue(':end', $this->query->period->endText(), SQLITE3_TEXT);
        $rows = $result->execute();
        // Number of consecutive steps each session has reached so far.
        $reached = [];
        while ($rows && ($row = $rows->fetchArray(SQLITE3_ASSOC))) {
            $session = $row['session_id'];
            $at = $reached[$session] ?? 0;
            if ($at >= count($steps)) {
                continue;
            }
            $action = json_decode($row['action'], true) ?: [];
            $name = (string) ($action['name'] ?? '');
            $path = (string) ($action['data']['path'] ?? '');
            $wanted = $steps[$at];
            $matches = $wanted['type'] === 'pageview' ? ($name === 'pageview' && $path === $wanted['value']) : ($name === $wanted['value']);
            if ($matches) {
                $reached[$session] = $at + 1;
            }
        }
        $progress = array_fill(0, count($steps), 0);
        foreach ($reached as $at) {
            for ($i = 0; $i < $at; $i++) {
                $progress[$i]++;
            }
        }
        return $progress;
    }

    /**
     * Funnels, goals and journeys saved in the dashboard.
     *
     * @return list<array{id: int, name: string, kind: string, steps: list<array{type: string, value: string, label?: string}>}>
     */
    public function saved(): array
    {
        // The table only exists once someone opened the funnels page: SQLite then
        // warns and returns false, PDO (MySQL) throws.
        try {
            $result = @$this->query->db->query('SELECT id, name, kind, steps FROM funnels ORDER BY created_at DESC, id DESC');
        } catch (Throwable) {
            return [];
        }
        $funnels = [];
        while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $funnels[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'kind' => (string) ($row['kind'] ?: 'funnel'), 'steps' => json_decode((string) $row['steps'], true) ?: []];
        }
        return $funnels;
    }
}
