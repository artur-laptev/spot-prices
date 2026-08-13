<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class LocalDay
{
    private function __construct(
        private DateTimeImmutable $midnight,
        private DateTimeZone $timezone,
    ) {}

    public static function fromIsoDate(string $date, DateTimeZone $timezone): self
    {
        $midnight = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($midnight === false || ($errors !== false && $errors['error_count'] > 0)) {
            throw new InvalidArgumentException("Not an ISO date: {$date}");
        }

        return new self($midnight, $timezone);
    }

    public static function today(DateTimeZone $timezone, ?DateTimeImmutable $now = null): self
    {
        $now ??= new DateTimeImmutable('now', $timezone);

        return self::fromIsoDate($now->setTimezone($timezone)->format('Y-m-d'), $timezone);
    }

    public function toIsoDate(): string
    {
        return $this->midnight->format('Y-m-d');
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->midnight->setTimezone(new DateTimeZone('UTC'));
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->addDays(1)->startsAt();
    }

    public function lengthInSeconds(): int
    {
        return $this->endsAt()->getTimestamp() - $this->startsAt()->getTimestamp();
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->startsAt() && $instant < $this->endsAt();
    }

    public function addDays(int $days): self
    {
        $sign = $days < 0 ? '-' : '+';

        return new self($this->midnight->modify("{$sign}".abs($days).' days'), $this->timezone);
    }

    public function isAfter(self $other): bool
    {
        return $this->startsAt() > $other->startsAt();
    }

    public function isBefore(self $other): bool
    {
        return $this->startsAt() < $other->startsAt();
    }
}
