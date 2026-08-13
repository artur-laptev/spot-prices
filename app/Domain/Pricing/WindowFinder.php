<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class WindowFinder
{
    public function __construct(private PriceSeries $series) {}

    public function periodsPerWindow(float $hours): ?int
    {
        $resolution = $this->series->resolutionSeconds();

        if ($resolution === null) {
            return null;
        }

        return max(1, (int) round($hours * 3600 / $resolution));
    }

    public function cheapest(float $hours): ?Window
    {
        return $this->best($hours, static fn (float $candidate, float $best): bool => $candidate < $best);
    }

    public function priciest(float $hours): ?Window
    {
        return $this->best($hours, static fn (float $candidate, float $best): bool => $candidate > $best);
    }

    /**
     * @param  callable(float, float): bool  $beats
     */
    private function best(float $hours, callable $beats): ?Window
    {
        $size = $this->periodsPerWindow($hours);

        if ($size === null) {
            return null;
        }

        $best = null;
        $bestMean = 0.0;

        foreach ($this->contiguousWindows($size) as $window) {
            $mean = $window->meanEurPerMwh();

            if ($best === null || $beats($mean, $bestMean)) {
                $best = $window;
                $bestMean = $mean;
            }
        }

        return $best;
    }

    /**
     * @return iterable<Window>
     */
    private function contiguousWindows(int $size): iterable
    {
        $points = $this->series->points();
        $lastStart = count($points) - $size;

        for ($start = 0; $start <= $lastStart; $start++) {
            $slice = array_slice($points, $start, $size);

            if ($this->isContiguous($slice)) {
                yield new Window($slice);
            }
        }
    }

    /**
     * @param  list<PricePoint>  $points
     */
    private function isContiguous(array $points): bool
    {
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            if (! $points[$i - 1]->isFollowedBy($points[$i])) {
                return false;
            }
        }

        return true;
    }
}
