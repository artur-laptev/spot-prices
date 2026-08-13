<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Pricing\LocalDay;
use App\Domain\Pricing\PricesUnavailable;
use App\Infrastructure\Elering\EleringClient;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EleringClientTest extends TestCase
{
    #[Test]
    public function it_requests_the_utc_range_of_the_estonian_day(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['ee' => []]])]);

        $this->client()->fetchDay($this->day('2026-08-13'), 'ee');

        Http::assertSent(function ($request): bool {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);

            return $query['start'] === '2026-08-12T21:00:00.000Z'
                && $query['end'] === '2026-08-13T20:59:59.999Z';
        });
    }

    #[Test]
    public function it_reads_quarter_hourly_prices_from_a_real_response(): void
    {
        Http::fake(['*' => Http::response(json_decode($this->fixture(), true))]);

        $rows = $this->client()->fetchDay($this->day('2026-08-12'), 'ee');

        $this->assertCount(96, $rows);
        $this->assertSame(900, $rows[1]['timestamp'] - $rows[0]['timestamp']);
        $this->assertIsFloat($rows[0]['price']);
    }

    #[Test]
    public function it_retries_before_giving_up(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(status: 500)
            ->push(['success' => true, 'data' => ['ee' => [['timestamp' => 1786482000, 'price' => 5.08]]]]),
        ]);

        $rows = $this->client()->fetchDay($this->day('2026-08-12'), 'ee');

        $this->assertCount(1, $rows);
        Http::assertSentCount(2);
    }

    #[Test]
    public function a_server_error_becomes_a_domain_failure(): void
    {
        Http::fake(['*' => Http::response(status: 503)]);

        $this->expectException(PricesUnavailable::class);

        $this->client()->fetchDay($this->day('2026-08-12'), 'ee');
    }

    #[Test]
    public function an_unsuccessful_payload_becomes_a_domain_failure(): void
    {
        Http::fake(['*' => Http::response(['success' => false])]);

        $this->expectException(PricesUnavailable::class);

        $this->client()->fetchDay($this->day('2026-08-12'), 'ee');
    }

    #[Test]
    public function malformed_rows_are_skipped_rather_than_fatal(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['ee' => [
            ['timestamp' => 1786482000, 'price' => 5.08],
            ['price' => 1.0],
            'nonsense',
        ]]])]);

        $rows = $this->client()->fetchDay($this->day('2026-08-12'), 'ee');

        $this->assertCount(1, $rows);
    }

    private function client(): EleringClient
    {
        return $this->app->make(EleringClient::class);
    }

    private function day(string $isoDate): LocalDay
    {
        return LocalDay::fromIsoDate($isoDate, new DateTimeZone('Europe/Tallinn'));
    }

    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/elering-quarter-hourly.json');
    }
}
