<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pricing\Indicators;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PriceProvider;
use App\Domain\Pricing\PricesUnavailable;
use App\Domain\Pricing\TariffProvider;
use App\Http\ViewModels\PricePage;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PricePageController
{
    public function __construct(
        private readonly PriceProvider $prices,
        private readonly TariffProvider $tariffs,
        private readonly DateTimeZone $timezone,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $calendar = $this->calendar();
        $day = $this->requestedDay($request, $calendar);
        $windowHours = $this->requestedWindowHours($request);

        if ($day === null || $windowHours === null) {
            return redirect()->route('prices');
        }

        return view('prices', [
            'page' => $this->pageFor($day, $windowHours, $calendar),
            'windowOptions' => range(
                (int) config('prices.window.min_hours'),
                (int) config('prices.window.max_hours'),
            ),
        ]);
    }

    private function pageFor(LocalDay $day, float $windowHours, PriceCalendar $calendar): PricePage
    {
        try {
            $snapshot = $this->prices->snapshotFor($day);
        } catch (PricesUnavailable) {
            return PricePage::unavailable($day, $windowHours, $calendar->earliest(), $calendar->latest());
        }

        return PricePage::from(
            $snapshot,
            Indicators::for($snapshot->series, $windowHours),
            $this->tariffs->current(),
            $calendar->earliest(),
            $calendar->latest(),
        );
    }

    private function calendar(): PriceCalendar
    {
        return new PriceCalendar(
            $this->timezone,
            (int) config('prices.calendar.past_days'),
            (int) config('prices.calendar.future_days'),
        );
    }

    private function requestedDay(Request $request, PriceCalendar $calendar): ?LocalDay
    {
        $date = $request->query('date');

        if ($date === null) {
            return $calendar->today();
        }

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $day = LocalDay::fromIsoDate($date, $this->timezone);

        return $calendar->allows($day) ? $day : null;
    }

    private function requestedWindowHours(Request $request): ?float
    {
        $hours = $request->query('window');

        if ($hours === null) {
            return (float) config('prices.window.default_hours');
        }

        if (! is_string($hours) || preg_match('/^\d+$/', $hours) !== 1) {
            return null;
        }

        $value = (int) $hours;
        $min = (int) config('prices.window.min_hours');
        $max = (int) config('prices.window.max_hours');

        return $value >= $min && $value <= $max ? (float) $value : null;
    }
}
