<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Pricing\LocalDay;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PricePageTest extends TestCase
{
    #[Test]
    public function it_renders_the_prices_of_the_selected_day(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::response($this->payloadFor($day, 24, 3600))]);

        $response = $this->get(route('prices', ['date' => $day->toIsoDate(), 'window' => 3]));

        $response->assertOk();
        $response->assertSee('Europe/Tallinn', escape: false);
        $response->assertSee('Cheapest 3 h');
        $response->assertSee('60 min periods');
    }

    #[Test]
    public function it_renders_a_quarter_hourly_day_without_assuming_24_periods(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::response($this->payloadFor($day, 96, 900))]);

        $response = $this->get(route('prices', ['date' => $day->toIsoDate()]));

        $response->assertOk();
        $response->assertSee('15 min periods');
    }

    #[Test]
    public function it_explains_that_tomorrow_is_not_published_yet(): void
    {
        $tomorrow = $this->today()->addDays(1);
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['ee' => []]])]);

        $response = $this->get(route('prices', ['date' => $tomorrow->toIsoDate()]));

        $response->assertOk();
        $response->assertSee('No prices published for '.$tomorrow->toIsoDate().' yet', escape: false);
    }

    #[Test]
    public function it_reports_an_outage_when_nothing_is_cached(): void
    {
        Http::fake(['*' => Http::response(status: 503)]);

        $response = $this->get(route('prices', ['date' => $this->today()->toIsoDate()]));

        $response->assertOk();
        $response->assertSee('Elering is not reachable right now');
    }

    #[Test]
    public function it_falls_back_to_stale_cache_when_elering_stops_responding(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::sequence()
            ->push($this->payloadFor($day, 24, 3600))
            ->whenEmpty(Http::response(status: 503)),
        ]);

        $this->get(route('prices', ['date' => $day->toIsoDate()]))->assertOk();
        $this->travel(2)->hours();

        $response = $this->get(route('prices', ['date' => $day->toIsoDate()]));

        $response->assertOk();
        $response->assertSee('Showing cached prices retrieved at');
    }

    #[Test]
    public function it_serves_a_cached_day_without_calling_elering_again(): void
    {
        $day = $this->today();
        Http::fake(['*' => Http::response($this->payloadFor($day, 24, 3600))]);

        $this->get(route('prices', ['date' => $day->toIsoDate()]))->assertOk();
        $this->get(route('prices', ['date' => $day->toIsoDate()]))->assertOk();

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_warns_when_periods_are_missing(): void
    {
        $day = $this->today();
        $payload = $this->payloadFor($day, 24, 3600);
        unset($payload['data']['ee'][5]);
        $payload['data']['ee'] = array_values($payload['data']['ee']);

        Http::fake(['*' => Http::response($payload)]);

        $response = $this->get(route('prices', ['date' => $day->toIsoDate()]));

        $response->assertOk();
        $response->assertSee('Incomplete data: 23 of 24 expected periods arrived', escape: false);
    }

    #[Test]
    public function a_date_outside_the_calendar_falls_back_to_the_default_view(): void
    {
        Http::fake(['*' => Http::response($this->payloadFor($this->today(), 24, 3600))]);

        $this->get(route('prices', ['date' => '2019-01-01']))->assertRedirect(route('prices'));
        $this->get(route('prices', ['date' => 'yesterday']))->assertRedirect(route('prices'));
        $this->get(route('prices', ['window' => 99]))->assertRedirect(route('prices'));
    }

    private function today(): LocalDay
    {
        return LocalDay::today(new DateTimeZone('Europe/Tallinn'));
    }

    private function payloadFor(LocalDay $day, int $count, int $stepSeconds): array
    {
        $rows = [];
        $start = $day->startsAt()->getTimestamp();

        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['timestamp' => $start + $i * $stepSeconds, 'price' => 10.0 + $i];
        }

        return ['success' => true, 'data' => ['ee' => $rows]];
    }
}
