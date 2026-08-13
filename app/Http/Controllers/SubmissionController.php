<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pricing\Indicators;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PriceProvider;
use App\Domain\Pricing\PricesUnavailable;
use App\Domain\Pricing\TariffProvider;
use App\Http\Requests\SubmissionRequest;
use App\Http\ViewModels\PricePage;
use App\Mail\SubmissionReport;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SubmissionController
{
    public function __construct(
        private readonly PriceProvider $prices,
        private readonly TariffProvider $tariffs,
        private readonly DateTimeZone $timezone,
    ) {}

    public function __invoke(SubmissionRequest $request): RedirectResponse
    {
        $day = LocalDay::fromIsoDate($request->string('date')->value(), $this->timezone);
        $windowHours = (float) $request->integer('window');
        $back = redirect()->route('prices', ['date' => $day->toIsoDate(), 'window' => (int) $windowHours]);

        try {
            $page = $this->pageFor($day, $windowHours);
        } catch (PricesUnavailable) {
            return $back->with('submission_error', 'Prices for this day are unavailable, so there is nothing to report yet.');
        }

        $report = new SubmissionReport(
            $request->toSubmission(),
            $page,
            (string) config('prices.zone'),
            $this->timezone,
        );

        try {
            Mail::to((string) config('prices.submission.recipient'))->send($report);
        } catch (Throwable $e) {
            Log::warning('Submission email could not be sent.', ['reason' => $e->getMessage()]);

            return $back->with('submission_error', 'The email could not be sent: '.$e->getMessage());
        }

        Log::info('Submission email sent.', ['date' => $day->toIsoDate()]);

        return $back->with('submission_status', 'Sent to '.config('prices.submission.recipient').'.');
    }

    private function pageFor(LocalDay $day, float $windowHours): PricePage
    {
        $calendar = new PriceCalendar(
            $this->timezone,
            (int) config('prices.calendar.past_days'),
            (int) config('prices.calendar.future_days'),
        );

        $snapshot = $this->prices->snapshotFor($day);

        return PricePage::from(
            $snapshot,
            Indicators::for($snapshot->series, $windowHours),
            $this->tariffs->current(),
            $calendar->earliest(),
            $calendar->latest(),
        );
    }
}
