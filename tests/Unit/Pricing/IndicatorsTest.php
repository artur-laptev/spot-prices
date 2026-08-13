<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Indicators;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceSeries;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndicatorsTest extends TestCase
{
    use MakesReadings;

    #[Test]
    public function it_summarises_the_day(): void
    {
        $day = $this->day('2026-08-13');
        $series = PriceSeries::fromReadings($day, $this->hourlyReadings($day, 4, [10.0, 20.0, 30.0, 40.0]));

        $indicators = Indicators::for($series, 2);

        $this->assertSame(10.0, $indicators->minEurPerMwh);
        $this->assertSame(40.0, $indicators->maxEurPerMwh);
        $this->assertSame(25.0, $indicators->meanEurPerMwh);
        $this->assertTrue($indicators->hasWindows());
    }

    #[Test]
    public function an_empty_day_has_no_figures_at_all(): void
    {
        $indicators = Indicators::for(PriceSeries::empty($this->day('2026-08-13')), 3);

        $this->assertNull($indicators->minEurPerMwh);
        $this->assertNull($indicators->meanEurPerMwh);
        $this->assertFalse($indicators->hasWindows());
        $this->assertSame(0, $indicators->periodCount);
    }

    #[Test]
    public function averages_are_computed_over_the_periods_that_arrived(): void
    {
        $day = $this->day('2026-08-13');
        $readings = $this->hourlyReadings($day, 4, [10.0, 20.0, 30.0, 40.0]);
        unset($readings[1]);

        $indicators = Indicators::for(PriceSeries::fromReadings($day, array_values($readings)), 2);

        $this->assertSame((10.0 + 30.0 + 40.0) / 3, $indicators->meanEurPerMwh);
        $this->assertSame(3, $indicators->periodCount);
        $this->assertSame(24, $indicators->expectedPeriodCount);
    }

    #[Test]
    public function a_point_below_the_mean_is_marked_as_such(): void
    {
        $day = $this->day('2026-08-13');
        $series = PriceSeries::fromReadings($day, $this->hourlyReadings($day, 3, [10.0, 20.0, 30.0]));

        $indicators = Indicators::for($series, 1);

        $this->assertTrue($indicators->isBelowAverage($series->points()[0]));
        $this->assertFalse($indicators->isBelowAverage($series->points()[2]));
    }

    private function day(string $isoDate): LocalDay
    {
        return LocalDay::fromIsoDate($isoDate, new DateTimeZone('Europe/Tallinn'));
    }
}
