<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class Indicators
{
    private function __construct(
        public ?float $minEurPerMwh,
        public ?float $maxEurPerMwh,
        public ?float $meanEurPerMwh,
        public ?Window $cheapestWindow,
        public ?Window $priciestWindow,
        public float $windowHours,
        public int $periodCount,
        public ?int $expectedPeriodCount,
    ) {}

    public static function for(PriceSeries $series, float $windowHours): self
    {
        if ($series->isEmpty()) {
            return new self(null, null, null, null, null, $windowHours, 0, $series->expectedCount());
        }

        $prices = $series->pricesEurPerMwh();
        $finder = new WindowFinder($series);

        return new self(
            min($prices),
            max($prices),
            array_sum($prices) / count($prices),
            $finder->cheapest($windowHours),
            $finder->priciest($windowHours),
            $windowHours,
            $series->count(),
            $series->expectedCount(),
        );
    }

    public function hasWindows(): bool
    {
        return $this->cheapestWindow !== null && $this->priciestWindow !== null;
    }

    public function isBelowAverage(PricePoint $point): bool
    {
        return $this->meanEurPerMwh !== null && $point->eurPerMwh < $this->meanEurPerMwh;
    }
}
