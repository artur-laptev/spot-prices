<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceSeries;
use App\Domain\Pricing\WindowFinder;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WindowFinderTest extends TestCase
{
    use MakesReadings;

    #[Test]
    public function it_finds_the_cheapest_run_of_hours(): void
    {
        $day = $this->day('2026-08-13');
        $prices = [50.0, 40.0, 10.0, 12.0, 14.0, 90.0];
        $finder = new WindowFinder(PriceSeries::fromReadings($day, $this->hourlyReadings($day, 6, $prices)));

        $window = $finder->cheapest(3);

        $this->assertNotNull($window);
        $this->assertSame(12.0, $window->meanEurPerMwh());
        $this->assertSame('02:00', $window->startsAt()->setTimezone($day->timezone())->format('H:i'));
    }

    #[Test]
    public function it_finds_the_most_expensive_run_of_hours(): void
    {
        $day = $this->day('2026-08-13');
        $prices = [50.0, 40.0, 10.0, 12.0, 14.0, 90.0];
        $finder = new WindowFinder(PriceSeries::fromReadings($day, $this->hourlyReadings($day, 6, $prices)));

        $window = $finder->priciest(2);

        $this->assertNotNull($window);
        $this->assertSame(52.0, $window->meanEurPerMwh());
    }

    #[Test]
    public function a_tie_resolves_to_the_earlier_window(): void
    {
        $day = $this->day('2026-08-13');
        $prices = [10.0, 10.0, 50.0, 10.0, 10.0];
        $finder = new WindowFinder(PriceSeries::fromReadings($day, $this->hourlyReadings($day, 5, $prices)));

        $window = $finder->cheapest(2);

        $this->assertNotNull($window);
        $this->assertSame('2026-08-12T21:00:00+00:00', $window->startsAt()->format('c'));
    }

    #[Test]
    public function three_hours_of_quarter_hourly_data_is_twelve_periods(): void
    {
        $day = $this->day('2026-08-13');
        $finder = new WindowFinder(PriceSeries::fromReadings($day, $this->readings($day, 96, 900)));

        $this->assertSame(12, $finder->periodsPerWindow(3));
        $this->assertCount(12, $finder->cheapest(3)->points);
    }

    #[Test]
    public function a_window_is_never_stretched_across_a_gap(): void
    {
        $day = $this->day('2026-08-13');
        $readings = $this->hourlyReadings($day, 6, [1.0, 1.0, 1.0, 99.0, 99.0, 99.0]);
        unset($readings[1]);

        $finder = new WindowFinder(PriceSeries::fromReadings($day, array_values($readings)));
        $window = $finder->cheapest(3);

        $this->assertNotNull($window);
        $this->assertSame((1.0 + 99.0 + 99.0) / 3, $window->meanEurPerMwh());
        $this->assertSame('02:00', $window->startsAt()->setTimezone($day->timezone())->format('H:i'));
    }

    #[Test]
    public function no_window_exists_when_every_candidate_spans_a_gap(): void
    {
        $day = $this->day('2026-08-13');
        $readings = $this->hourlyReadings($day, 4, [1.0, 2.0, 3.0, 4.0]);
        unset($readings[2]);

        $finder = new WindowFinder(PriceSeries::fromReadings($day, array_values($readings)));

        $this->assertNull($finder->cheapest(3));
        $this->assertNull($finder->priciest(3));
    }

    #[Test]
    public function negative_prices_win_the_cheapest_window(): void
    {
        $day = $this->day('2026-08-13');
        $prices = [5.0, -20.0, -30.0, 5.0];
        $finder = new WindowFinder(PriceSeries::fromReadings($day, $this->hourlyReadings($day, 4, $prices)));

        $window = $finder->cheapest(2);

        $this->assertNotNull($window);
        $this->assertSame(-25.0, $window->meanEurPerMwh());
    }

    #[Test]
    public function a_window_longer_than_the_data_cannot_be_found(): void
    {
        $day = $this->day('2026-08-13');
        $finder = new WindowFinder(PriceSeries::fromReadings($day, $this->hourlyReadings($day, 2)));

        $this->assertNull($finder->cheapest(6));
    }

    private function day(string $isoDate): LocalDay
    {
        return LocalDay::fromIsoDate($isoDate, new DateTimeZone('Europe/Tallinn'));
    }
}
