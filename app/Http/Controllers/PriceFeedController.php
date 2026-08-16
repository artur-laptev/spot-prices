<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Pricing\BuildPricePage;
use App\Domain\Pricing\DayOutOfRange;
use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PricesUnavailable;
use App\Http\Requests\PriceQuery;
use App\Http\ViewModels\PriceFeed;
use App\Http\ViewModels\PricePage;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PriceFeedController
{
    public function __construct(
        private readonly BuildPricePage $pages,
        private readonly PriceCalendar $calendar,
        private readonly DateTimeZone $timezone,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = PriceQuery::fromRequest($request, $this->calendar, $this->timezone);

        if ($query === null) {
            return $this->error(
                'Give date as YYYY-MM-DD and window as a whole number of hours between '
                .config('prices.window.min_hours').' and '.config('prices.window.max_hours').'.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $page = $this->pages->for($query->day, $query->windowHours);
        } catch (DayOutOfRange) {
            return $this->error(
                'This feed covers '.$this->calendar->earliest()->toIsoDate()
                .' to '.$this->calendar->latest()->toIsoDate().'.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (PricesUnavailable) {
            $page = PricePage::unavailable(
                $query->day,
                $query->windowHours,
                $this->calendar->earliest(),
                $this->calendar->latest(),
            );

            return $this->feed($page, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->feed($page, Response::HTTP_OK);
    }

    private function feed(PricePage $page, int $status): JsonResponse
    {
        return response()->json(
            PriceFeed::from($page, (string) config('prices.zone'))->toArray(),
            $status,
            [],
            JSON_UNESCAPED_UNICODE,
        );
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }
}
