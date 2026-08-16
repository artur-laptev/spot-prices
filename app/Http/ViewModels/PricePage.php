<?php

declare(strict_types=1);

namespace App\Http\ViewModels;

use App\Domain\Pricing\Indicators;
use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PricePoint;
use App\Domain\Pricing\PriceSnapshot;
use App\Domain\Pricing\Tariff;
use App\Domain\Pricing\Window;
use DateTimeImmutable;

/**
 * @phpstan-type Money array{eurPerMwh: float, exchangeSntKwh: float, retailSntKwhInclVat: float}
 * @phpstan-type WindowSummary array{range: string, startsAt: string, eurPerMwh: float, exchangeSntKwh: float, retailSntKwhInclVat: float}
 * @phpstan-type PeriodRow array{startsAtUtc: string, label: string, range: string, exchangeEurPerMwh: float, exchangeSntKwh: float, retailSntKwhInclVat: float, belowAverage: bool, inCheapestWindow: bool, inPriciestWindow: bool}
 * @phpstan-type Summary array{minimum: Money, maximum: Money, average: Money, cheapestWindow: WindowSummary|null, priciestWindow: WindowSummary|null}
 * @phpstan-type Notice array{level: string, message: string}
 */
final readonly class PricePage
{
    /**
     * @param  list<PeriodRow>  $rows
     * @param  Summary|array{}  $summary
     * @param  list<Notice>  $notices
     */
    private function __construct(
        public string $isoDate,
        public string $headline,
        public string $timezoneLabel,
        public float $windowHours,
        public string $minIsoDate,
        public string $maxIsoDate,
        public array $rows,
        public array $summary,
        public array $notices,
        public ?string $resolutionLabel,
    ) {}

    public static function unavailable(LocalDay $day, float $windowHours, LocalDay $earliest, LocalDay $latest): self
    {
        return new self(
            $day->toIsoDate(),
            self::headlineFor($day),
            self::timezoneLabelFor($day),
            $windowHours,
            $earliest->toIsoDate(),
            $latest->toIsoDate(),
            [],
            [],
            [['level' => 'error', 'message' => 'Elering is not reachable right now and nothing is cached for this day. Try again in a moment.']],
            null,
        );
    }

    public static function from(
        PriceSnapshot $snapshot,
        Indicators $indicators,
        Tariff $tariff,
        LocalDay $earliest,
        LocalDay $latest,
    ): self {
        $day = $snapshot->series->day();
        $rows = self::buildRows($snapshot, $indicators, $tariff);

        return new self(
            $day->toIsoDate(),
            self::headlineFor($day),
            self::timezoneLabelFor($day),
            $indicators->windowHours,
            $earliest->toIsoDate(),
            $latest->toIsoDate(),
            $rows,
            self::buildSummary($indicators, $tariff, $day),
            self::buildNotices($snapshot, $indicators, $day),
            self::resolutionLabel($snapshot),
        );
    }

    public function hasRows(): bool
    {
        return $this->rows !== [];
    }

    public function chartPayload(): string
    {
        return json_encode($this->rows, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<PeriodRow>
     */
    private static function buildRows(PriceSnapshot $snapshot, Indicators $indicators, Tariff $tariff): array
    {
        $timezone = $snapshot->series->day()->timezone();

        return array_map(
            static fn (PricePoint $point): array => [
                'startsAtUtc' => $point->startsAt->format(DateTimeImmutable::ATOM),
                'label' => $point->startsAt->setTimezone($timezone)->format('H:i'),
                'range' => sprintf(
                    '%s–%s',
                    $point->startsAt->setTimezone($timezone)->format('H:i'),
                    $point->endsAt()->setTimezone($timezone)->format('H:i'),
                ),
                'exchangeEurPerMwh' => round($point->eurPerMwh, 2),
                'exchangeSntKwh' => round($tariff->exchangeSntKwh($point->eurPerMwh), 2),
                'retailSntKwhInclVat' => round($tariff->withVat($tariff->retailSntKwh($point->eurPerMwh)), 2),
                'belowAverage' => $indicators->isBelowAverage($point),
                'inCheapestWindow' => $indicators->cheapestWindow?->covers($point) ?? false,
                'inPriciestWindow' => $indicators->priciestWindow?->covers($point) ?? false,
            ],
            $snapshot->series->points(),
        );
    }

    /**
     * @return Summary|array{}
     */
    private static function buildSummary(Indicators $indicators, Tariff $tariff, LocalDay $day): array
    {
        if ($indicators->meanEurPerMwh === null) {
            return [];
        }

        return [
            'minimum' => self::money($indicators->minEurPerMwh, $tariff),
            'maximum' => self::money($indicators->maxEurPerMwh, $tariff),
            'average' => self::money($indicators->meanEurPerMwh, $tariff),
            'cheapestWindow' => self::window($indicators->cheapestWindow, $tariff, $day),
            'priciestWindow' => self::window($indicators->priciestWindow, $tariff, $day),
        ];
    }

    /**
     * @return Money
     */
    private static function money(float $eurPerMwh, Tariff $tariff): array
    {
        $retail = $tariff->retailSntKwh($eurPerMwh);

        return [
            'eurPerMwh' => round($eurPerMwh, 2),
            'exchangeSntKwh' => round($tariff->exchangeSntKwh($eurPerMwh), 2),
            'retailSntKwhInclVat' => round($tariff->withVat($retail), 2),
        ];
    }

    /**
     * @return WindowSummary|null
     */
    private static function window(?Window $window, Tariff $tariff, LocalDay $day): ?array
    {
        if ($window === null) {
            return null;
        }

        $timezone = $day->timezone();

        return [
            'range' => sprintf(
                '%s–%s',
                $window->startsAt()->setTimezone($timezone)->format('H:i'),
                $window->endsAt()->setTimezone($timezone)->format('H:i'),
            ),
            'startsAt' => $window->startsAt()->setTimezone($timezone)->format('H:i'),
        ] + self::money($window->meanEurPerMwh(), $tariff);
    }

    /**
     * @return list<Notice>
     */
    private static function buildNotices(PriceSnapshot $snapshot, Indicators $indicators, LocalDay $day): array
    {
        $notices = [];

        if ($snapshot->isAwaitingPublication()) {
            $notices[] = [
                'level' => 'info',
                'message' => "No prices published for {$day->toIsoDate()} yet. Day-ahead prices for tomorrow usually appear around 14:00 Europe/Tallinn.",
            ];

            return $notices;
        }

        if ($snapshot->servedFromStaleCache) {
            $notices[] = [
                'level' => 'warning',
                'message' => 'Elering is not responding. Showing cached prices retrieved at '
                    .$snapshot->retrievedAt->setTimezone($day->timezone())->format('H:i, d M Y').'.',
            ];
        }

        if (! $snapshot->series->isComplete()) {
            $notices[] = [
                'level' => 'warning',
                'message' => sprintf(
                    'Incomplete data: %d of %d expected periods arrived. Missing periods are left out rather than filled in.',
                    $snapshot->series->count(),
                    $snapshot->series->expectedCount() ?? $snapshot->series->count(),
                ),
            ];
        }

        if (! $indicators->hasWindows() && ! $snapshot->series->isEmpty()) {
            $notices[] = [
                'level' => 'warning',
                'message' => 'Cheapest and most expensive windows cannot be computed: no unbroken run of periods is long enough.',
            ];
        }

        return $notices;
    }

    private static function resolutionLabel(PriceSnapshot $snapshot): ?string
    {
        $seconds = $snapshot->series->resolutionSeconds();

        return $seconds === null ? null : intdiv($seconds, 60).' min';
    }

    private static function headlineFor(LocalDay $day): string
    {
        return $day->startsAt()->setTimezone($day->timezone())->format('l, j F Y');
    }

    private static function timezoneLabelFor(LocalDay $day): string
    {
        $timezone = $day->timezone();

        return $timezone->getName().' ('.$day->startsAt()->setTimezone($timezone)->format('T').')';
    }
}
