<?php

declare(strict_types=1);

namespace App\Http\ViewModels;

/**
 * @phpstan-import-type PeriodRow from PricePage
 * @phpstan-import-type Summary from PricePage
 * @phpstan-import-type Notice from PricePage
 */
final readonly class PriceFeed
{
    private function __construct(
        private PricePage $page,
        private string $zone,
    ) {}

    public static function from(PricePage $page, string $zone): self
    {
        return new self($page, $zone);
    }

    /**
     * @return array{date: string, zone: string, timezone: string, resolution: string|null, windowHours: float, indicators: Summary|null, periods: list<PeriodRow>, notices: list<Notice>}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->page->isoDate,
            'zone' => $this->zone,
            'timezone' => $this->page->timezoneLabel,
            'resolution' => $this->page->resolutionLabel,
            'windowHours' => $this->page->windowHours,
            'indicators' => $this->page->summary === [] ? null : $this->page->summary,
            'periods' => $this->page->rows,
            'notices' => $this->page->notices,
        ];
    }
}
