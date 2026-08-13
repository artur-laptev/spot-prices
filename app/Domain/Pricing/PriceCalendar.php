<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use DateTimeImmutable;
use DateTimeZone;

final readonly class PriceCalendar
{
    public function __construct(
        private DateTimeZone $timezone,
        private int $pastDays,
        private int $futureDays,
    ) {}

    public function today(?DateTimeImmutable $now = null): LocalDay
    {
        return LocalDay::today($this->timezone, $now);
    }

    public function earliest(?DateTimeImmutable $now = null): LocalDay
    {
        return $this->today($now)->addDays(-$this->pastDays);
    }

    public function latest(?DateTimeImmutable $now = null): LocalDay
    {
        return $this->today($now)->addDays($this->futureDays);
    }

    public function allows(LocalDay $day, ?DateTimeImmutable $now = null): bool
    {
        return ! $day->isBefore($this->earliest($now)) && ! $day->isAfter($this->latest($now));
    }
}
