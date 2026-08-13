<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class Tariff
{
    private const CENTS_PER_EURO = 100;

    private const KWH_PER_MWH = 1000;

    public function __construct(
        public float $vatRate,
        public float $gridFeeSntKwh,
        public float $sellerMarginSntKwh,
    ) {}

    public static function eurPerMwhToSntKwh(float $eurPerMwh): float
    {
        return $eurPerMwh * self::CENTS_PER_EURO / self::KWH_PER_MWH;
    }

    public function exchangeSntKwh(float $eurPerMwh): float
    {
        return self::eurPerMwhToSntKwh($eurPerMwh);
    }

    public function retailSntKwh(float $eurPerMwh): float
    {
        return $this->exchangeSntKwh($eurPerMwh) + $this->gridFeeSntKwh + $this->sellerMarginSntKwh;
    }

    public function withVat(float $sntKwh): float
    {
        return $sntKwh * (1 + $this->vatRate);
    }
}
