<?php

declare(strict_types=1);

namespace Minilytics\Tracking;

/** What track.php sends back to the tracker. */
final class TrackingResponse
{
    /**
     * @param array<string, mixed> $body
     * @param string|null $allowOrigin origin to allow through CORS, once the request is authorized
     */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly ?string $allowOrigin = null,
    ) {}
}
