<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceCalendar;
use DateTimeZone;
use Illuminate\Http\Request;

final readonly class PriceQuery
{
    private function __construct(
        public LocalDay $day,
        public float $windowHours,
    ) {}

    public static function fromRequest(Request $request, PriceCalendar $calendar, DateTimeZone $timezone): ?self
    {
        $day = self::day($request, $calendar, $timezone);
        $windowHours = self::windowHours($request);

        if ($day === null || $windowHours === null) {
            return null;
        }

        return new self($day, $windowHours);
    }

    private static function day(Request $request, PriceCalendar $calendar, DateTimeZone $timezone): ?LocalDay
    {
        $date = $request->query('date');

        if ($date === null) {
            return $calendar->today();
        }

        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        return LocalDay::fromIsoDate($date, $timezone);
    }

    private static function windowHours(Request $request): ?float
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
