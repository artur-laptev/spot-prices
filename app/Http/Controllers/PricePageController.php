<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Pricing\BuildPricePage;
use App\Domain\Pricing\DayOutOfRange;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PricesUnavailable;
use App\Http\ViewModels\PricePage;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PricePageController
{
    public function __construct(
        private readonly BuildPricePage $pages,
        private readonly PriceCalendar $calendar,
        private readonly DateTimeZone $timezone,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $day = $this->requestedDay($request);
        $windowHours = $this->requestedWindowHours($request);

        if ($day === null || $windowHours === null) {
            return redirect()->route('prices');
        }

        try {
            $page = $this->pages->for($day, $windowHours);
        } catch (DayOutOfRange) {
            return redirect()->route('prices');
        } catch (PricesUnavailable) {
            $page = PricePage::unavailable(
                $day,
                $windowHours,
                $this->calendar->earliest(),
                $this->calendar->latest(),
            );
        }

        return view('prices', [
            'page' => $page,
            'windowOptions' => range(
                (int) config('prices.window.min_hours'),
                (int) config('prices.window.max_hours'),
            ),
        ]);
    }

    private function requestedDay(Request $request): ?LocalDay
    {
        $date = $request->query('date');

        if ($date === null) {
            return $this->calendar->today();
        }

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return LocalDay::fromIsoDate($date, $this->timezone);
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
