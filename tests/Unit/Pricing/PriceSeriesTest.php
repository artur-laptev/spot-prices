<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceReading;
use App\Domain\Pricing\PriceSeries;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceSeriesTest extends TestCase
{
    use MakesReadings;

    #[Test]
    public function it_derives_hourly_resolution_from_the_readings(): void
    {
        $day = $this->day('2026-08-13');
        $series = PriceSeries::fromReadings($day, $this->hourlyReadings($day, 24));

        $this->assertSame(3600, $series->resolutionSeconds());
        $this->assertSame(24, $series->count());
        $this->assertSame(24, $series->expectedCount());
        $this->assertTrue($series->isComplete());
    }

    #[Test]
    public function it_derives_quarter_hourly_resolution_from_the_readings(): void
    {
        $day = $this->day('2026-08-13');
        $series = PriceSeries::fromReadings($day, $this->readings($day, 96, 900));

        $this->assertSame(900, $series->resolutionSeconds());
        $this->assertSame(96, $series->expectedCount());
        $this->assertTrue($series->isComplete());
    }

    #[Test]
    public function a_25_hour_day_expects_25_hourly_periods(): void
    {
        $day = $this->day('2026-10-25');
        $series = PriceSeries::fromReadings($day, $this->hourlyReadings($day, 25));

        $this->assertSame(25, $series->count());
        $this->assertSame(25, $series->expectedCount());
        $this->assertTrue($series->isComplete());
    }

    #[Test]
    public function a_23_hour_day_expects_23_hourly_periods(): void
    {
        $day = $this->day('2026-03-29');
        $series = PriceSeries::fromReadings($day, $this->hourlyReadings($day, 23));

        $this->assertSame(23, $series->expectedCount());
        $this->assertTrue($series->isComplete());
    }

    #[Test]
    public function a_missing_period_does_not_stretch_its_neighbour(): void
    {
        $day = $this->day('2026-08-13');
        $readings = $this->hourlyReadings($day, 24);
        unset($readings[5]);

        $series = PriceSeries::fromReadings($day, array_values($readings));

        $this->assertSame(3600, $series->resolutionSeconds());
        $this->assertSame(23, $series->count());
        $this->assertSame(1, $series->missingCount());
        $this->assertFalse($series->isComplete());
        $this->assertSame(3600, $series->points()[4]->durationSeconds);
    }

    #[Test]
    public function the_last_reading_for_a_duplicated_timestamp_wins(): void
    {
        $day = $this->day('2026-08-13');
        $start = $day->startsAt();

        $series = PriceSeries::fromReadings($day, [
            new PriceReading($start, 10.0),
            new PriceReading($start->modify('+1 hour'), 20.0),
            new PriceReading($start, 99.0),
        ]);

        $this->assertSame(2, $series->count());
        $this->assertSame(99.0, $series->points()[0]->eurPerMwh);
    }

    #[Test]
    public function readings_outside_the_day_are_dropped(): void
    {
        $day = $this->day('2026-08-13');
        $readings = $this->hourlyReadings($day, 24);
        $readings[] = new PriceReading($day->endsAt(), 42.0);

        $series = PriceSeries::fromReadings($day, $readings);

        $this->assertSame(24, $series->count());
    }

    #[Test]
    public function negative_prices_survive_untouched(): void
    {
        $day = $this->day('2026-08-13');
        $series = PriceSeries::fromReadings($day, [
            new PriceReading($day->startsAt(), -12.5),
            new PriceReading($day->startsAt()->modify('+1 hour'), 3.0),
        ]);

        $this->assertSame([-12.5, 3.0], $series->pricesEurPerMwh());
    }

    #[Test]
    public function an_empty_response_yields_an_empty_series(): void
    {
        $series = PriceSeries::fromReadings($this->day('2026-08-13'), []);

        $this->assertTrue($series->isEmpty());
        $this->assertFalse($series->isComplete());
        $this->assertNull($series->resolutionSeconds());
    }

    private function day(string $isoDate): LocalDay
    {
        return LocalDay::fromIsoDate($isoDate, new DateTimeZone('Europe/Tallinn'));
    }
}
