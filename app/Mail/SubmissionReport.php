<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Submission\Submission;
use App\Http\ViewModels\PricePage;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SubmissionReport extends Mailable
{
    public function __construct(
        public readonly Submission $submission,
        public readonly PricePage $page,
        public readonly string $zone,
        private readonly DateTimeZone $timezone,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Homework submission — {$this->submission->name} — {$this->page->isoDate}",
            replyTo: [$this->submission->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.submission',
            text: 'mail.submission-text',
            with: [
                'sentAt' => (new DateTimeImmutable('now', $this->timezone))->format('d.m.Y H:i:s T'),
                'phpVersion' => PHP_VERSION,
                'zoneLabel' => strtoupper($this->zone),
            ],
        );
    }
}
