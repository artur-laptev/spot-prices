<?php

declare(strict_types=1);

namespace App\Application\Pricing;

use App\Domain\Pricing\DayOutOfRange;
use App\Domain\Pricing\Indicators;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PriceProvider;
use App\Domain\Pricing\PricesUnavailable;
use App\Domain\Pricing\TariffProvider;
use App\Http\ViewModels\PricePage;

final readonly class BuildPricePage
{
    public function __construct(
        private PriceProvider $prices,
        private TariffProvider $tariffs,
        private PriceCalendar $calendar,
    ) {}

    /**
     * @throws DayOutOfRange
     * @throws PricesUnavailable
     */
    public function for(LocalDay $day, float $windowHours): PricePage
    {
        if (! $this->calendar->allows($day)) {
            throw DayOutOfRange::for($day);
        }

        $snapshot = $this->prices->snapshotFor($day);

        return PricePage::from(
            $snapshot,
            Indicators::for($snapshot->series, $windowHours),
            $this->tariffs->current(),
            $this->calendar->earliest(),
            $this->calendar->latest(),
        );
    }
}
