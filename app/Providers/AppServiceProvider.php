<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Pricing\PriceCalendar;
use App\Domain\Pricing\PriceProvider;
use App\Domain\Pricing\TariffProvider;
use App\Infrastructure\Elering\CachedEleringPriceProvider;
use App\Infrastructure\Elering\EleringClient;
use App\Infrastructure\Tariffs\StaticTariffProvider;
use DateTimeZone;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TariffProvider::class, fn (): TariffProvider => new StaticTariffProvider(
            (float) config('prices.tariff.vat_rate'),
            (float) config('prices.tariff.grid_fee_snt_kwh'),
            (float) config('prices.tariff.seller_margin_snt_kwh'),
        ));

        $this->app->singleton(DateTimeZone::class, fn (): DateTimeZone => new DateTimeZone(
            (string) config('prices.display_timezone'),
        ));

        $this->app->singleton(EleringClient::class, fn ($app): EleringClient => new EleringClient(
            $app->make(HttpClient::class),
            rtrim((string) config('prices.elering.base_url'), '/'),
            (int) config('prices.elering.timeout_seconds'),
            (int) config('prices.elering.retries'),
            (int) config('prices.elering.retry_delay_ms'),
        ));

        $this->app->singleton(PriceProvider::class, fn ($app): PriceProvider => new CachedEleringPriceProvider(
            $app->make(EleringClient::class),
            $app->make(Cache::class),
            (string) config('prices.zone'),
            (int) config('prices.cache.unsettled_ttl_seconds'),
            (int) config('prices.cache.settled_ttl_seconds'),
            (int) config('prices.cache.upstream_down_ttl_seconds'),
        ));

        $this->app->singleton(PriceCalendar::class, fn ($app): PriceCalendar => new PriceCalendar(
            $app->make(DateTimeZone::class),
            (int) config('prices.calendar.past_days'),
            (int) config('prices.calendar.future_days'),
        ));
    }
}
