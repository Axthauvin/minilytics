<?php

declare(strict_types=1);

namespace Minilytics\Analytics\Reports;

use Minilytics\Analytics\ReportQuery;

/** Requests rejected as bot traffic. */
final class BotsReport
{
    public function __construct(private readonly ReportQuery $query) {}

    /** The 100 most recent requests rejected as bot traffic. */
    public function recent(): array
    {
        return $this->query->rows(
            'SELECT reason, user_agent, origin, timestamp FROM bot_activity WHERE timestamp BETWEEN :start AND :end ORDER BY id DESC LIMIT 100',
            [':start' => $this->query->period->startText(), ':end' => $this->query->period->endText()],
        );
    }
}
