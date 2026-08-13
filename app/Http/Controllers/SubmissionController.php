<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Pricing\BuildPricePage;
use App\Domain\Pricing\DayOutOfRange;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PricesUnavailable;
use App\Http\Requests\SubmissionRequest;
use App\Mail\SubmissionReport;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SubmissionController
{
    public function __construct(
        private readonly BuildPricePage $pages,
        private readonly DateTimeZone $timezone,
    ) {}

    public function __invoke(SubmissionRequest $request): RedirectResponse
    {
        $day = LocalDay::fromIsoDate($request->string('date')->value(), $this->timezone);
        $windowHours = (float) $request->integer('window');
        $back = redirect()->route('prices', ['date' => $day->toIsoDate(), 'window' => (int) $windowHours]);

        try {
            $page = $this->pages->for($day, $windowHours);
        } catch (DayOutOfRange) {
            return redirect()->route('prices')
                ->with('submission_error', 'That date is outside the range this page covers.');
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

            return $back->with('submission_error', 'The email could not be sent. Please try again in a moment.');
        }

        Log::info('Submission email sent.', ['date' => $day->toIsoDate()]);

        return $back->with('submission_status', 'Sent to '.config('prices.submission.recipient').'.');
    }
}
