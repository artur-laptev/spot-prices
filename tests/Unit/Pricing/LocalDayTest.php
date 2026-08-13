<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\LocalDay;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocalDayTest extends TestCase
{
    private DateTimeZone $tallinn;

    protected function setUp(): void
    {
        $this->tallinn = new DateTimeZone('Europe/Tallinn');
    }

    #[Test]
    public function a_summer_day_starts_at_21_utc(): void
    {
        $day = LocalDay::fromIsoDate('2026-08-13', $this->tallinn);

        $this->assertSame('2026-08-12T21:00:00+00:00', $day->startsAt()->format(DateTimeImmutable::ATOM));
        $this->assertSame('2026-08-13T21:00:00+00:00', $day->endsAt()->format(DateTimeImmutable::ATOM));
    }

    #[Test]
    public function a_winter_day_starts_at_22_utc(): void
    {
        $day = LocalDay::fromIsoDate('2026-01-15', $this->tallinn);

        $this->assertSame('2026-01-14T22:00:00+00:00', $day->startsAt()->format(DateTimeImmutable::ATOM));
    }

    #[Test]
    public function the_spring_clock_change_day_is_23_hours_long(): void
    {
        $day = LocalDay::fromIsoDate('2026-03-29', $this->tallinn);

        $this->assertSame(23 * 3600, $day->lengthInSeconds());
    }

    #[Test]
    public function the_autumn_clock_change_day_is_25_hours_long(): void
    {
        $day = LocalDay::fromIsoDate('2026-10-25', $this->tallinn);

        $this->assertSame(25 * 3600, $day->lengthInSeconds());
    }

    #[Test]
    public function it_contains_only_instants_within_its_own_bounds(): void
    {
        $day = LocalDay::fromIsoDate('2026-08-13', $this->tallinn);

        $this->assertTrue($day->contains(new DateTimeImmutable('2026-08-12T21:00:00+00:00')));
        $this->assertFalse($day->contains(new DateTimeImmutable('2026-08-12T20:59:59+00:00')));
        $this->assertFalse($day->contains(new DateTimeImmutable('2026-08-13T21:00:00+00:00')));
    }

    #[Test]
    public function adding_days_crosses_the_clock_change_without_drifting(): void
    {
        $day = LocalDay::fromIsoDate('2026-10-24', $this->tallinn);

        $this->assertSame('2026-10-26', $day->addDays(2)->toIsoDate());
        $this->assertSame('2026-10-23', $day->addDays(-1)->toIsoDate());
    }

    #[Test]
    public function it_rejects_anything_that_is_not_an_iso_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalDay::fromIsoDate('13.08.2026', $this->tallinn);
    }
}
