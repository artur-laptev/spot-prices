<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Pricing\BuildPricePage;
use App\Domain\Pricing\DayOutOfRange;
use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PricesUnavailable;
use App\Http\Requests\PriceQuery;
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
        $query = PriceQuery::fromRequest($request, $this->calendar, $this->timezone);

        if ($query === null) {
            return redirect()->route('prices');
        }

        try {
            $page = $this->pages->for($query->day, $query->windowHours);
        } catch (DayOutOfRange) {
            return redirect()->route('prices');
        } catch (PricesUnavailable) {
            $page = PricePage::unavailable(
                $query->day,
                $query->windowHours,
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
}
