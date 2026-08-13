<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Window
{
    /**
     * @param  non-empty-list<PricePoint>  $points
     */
    public function __construct(public array $points)
    {
        if ($points === []) {
            throw new InvalidArgumentException('A window needs at least one price point.');
        }
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->points[0]->startsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->points[count($this->points) - 1]->endsAt();
    }

    public function meanEurPerMwh(): float
    {
        $prices = array_map(static fn (PricePoint $p): float => $p->eurPerMwh, $this->points);

        return array_sum($prices) / count($prices);
    }

    public function covers(PricePoint $point): bool
    {
        return $point->startsAt >= $this->startsAt() && $point->endsAt() <= $this->endsAt();
    }
}
