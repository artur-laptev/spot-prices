<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PricePoint
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $durationSeconds,
        public float $eurPerMwh,
    ) {
        if ($durationSeconds <= 0) {
            throw new InvalidArgumentException("Period duration must be positive, got {$durationSeconds}.");
        }
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt->modify("+{$this->durationSeconds} seconds");
    }

    public function isFollowedBy(self $other): bool
    {
        return $this->endsAt()->getTimestamp() === $other->startsAt->getTimestamp();
    }

    public function withDuration(int $durationSeconds): self
    {
        return new self($this->startsAt, $durationSeconds, $this->eurPerMwh);
    }
}
