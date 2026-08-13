<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use DateTimeImmutable;

final readonly class PriceReading
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public float $eurPerMwh,
    ) {}
}
