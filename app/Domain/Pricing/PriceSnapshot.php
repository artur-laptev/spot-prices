<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use DateTimeImmutable;

final readonly class PriceSnapshot
{
    public function __construct(
        public PriceSeries $series,
        public DateTimeImmutable $retrievedAt,
        public bool $servedFromStaleCache = false,
    ) {}

    public function isAwaitingPublication(): bool
    {
        return $this->series->isEmpty();
    }
}
