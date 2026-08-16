<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Pricing\LocalDay;
use App\Mail\SubmissionReport;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake(['*' => Http::response($this->payloadFor($this->today()))]);
    }

    #[Test]
    public function it_mails_the_report_to_the_configured_recipient(): void
    {
        $response = $this->post(route('submit'), $this->validPayload());

        $response->assertRedirect(route('prices', ['date' => $this->today()->toIsoDate(), 'window' => 3]));
        $response->assertSessionHas('submission_status');

        Mail::assertSent(SubmissionReport::class, fn (SubmissionReport $mail): bool => $mail->hasTo('jobs@qilowatt.eu')
            && $mail->submission->name === 'Test Candidate'
            && $mail->page->summary['cheapestWindow'] !== null);
    }

    #[Test]
    public function the_report_carries_the_indicators_recomputed_on_the_server(): void
    {
        $this->post(route('submit'), $this->validPayload(['window' => 2]));

        Mail::assertSent(SubmissionReport::class, function (SubmissionReport $mail): bool {
            $mail->assertSeeInHtml('Cheapest 2 h window');
            $mail->assertSeeInHtml('PHP '.PHP_VERSION);

            return true;
        });
    }

    #[Test]
    public function it_rejects_an_incomplete_form(): void
    {
        $response = $this->from(route('prices'))->post(route('submit'), []);

        $response->assertSessionHasErrors(['name', 'email', 'phone', 'repository_url', 'commit_sha', 'date', 'window']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_rejects_a_malformed_email_and_repository_url(): void
    {
        $response = $this->from(route('prices'))->post(route('submit'), $this->validPayload([
            'email' => 'not-an-email',
            'repository_url' => 'github dot com',
        ]));

        $response->assertSessionHasErrors(['email', 'repository_url']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_rejects_a_window_outside_the_allowed_range(): void
    {
        $response = $this->from(route('prices'))->post(route('submit'), $this->validPayload(['window' => 9]));

        $response->assertSessionHasErrors('window');
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_date_outside_the_calendar_never_reaches_elering(): void
    {
        $response = $this->from(route('prices'))->post(route('submit'), $this->validPayload([
            'date' => '1900-01-01',
        ]));

        $response->assertRedirect(route('prices'));
        $response->assertSessionHas('submission_error');
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[Test]
    public function repeated_submissions_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('submit'), $this->validPayload())->assertRedirect();
        }

        $this->post(route('submit'), $this->validPayload())->assertStatus(429);
    }

    #[Test]
    public function personal_details_are_not_flashed_back_into_the_session(): void
    {
        $this->from(route('prices'))->post(route('submit'), $this->validPayload(['email' => 'nope']));

        $this->assertNull(session('_old_input.name'));
        $this->assertNull(session('_old_input.email'));
        $this->assertNull(session('_old_input.phone'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Candidate',
            'email' => 'candidate@example.com',
            'phone' => '+372 5555 5555',
            'repository_url' => 'https://github.com/candidate/spot-prices',
            'commit_sha' => 'a1b2c3d',
            'date' => $this->today()->toIsoDate(),
            'window' => 3,
        ], $overrides);
    }

    private function today(): LocalDay
    {
        return LocalDay::today(new DateTimeZone('Europe/Tallinn'));
    }

    /**
     * @return array{success: bool, data: array{ee: list<array{timestamp: int, price: float}>}}
     */
    private function payloadFor(LocalDay $day): array
    {
        $rows = [];
        $start = $day->startsAt()->getTimestamp();

        for ($i = 0; $i < 24; $i++) {
            $rows[] = ['timestamp' => $start + $i * 3600, 'price' => 10.0 + $i];
        }

        return ['success' => true, 'data' => ['ee' => $rows]];
    }
}
