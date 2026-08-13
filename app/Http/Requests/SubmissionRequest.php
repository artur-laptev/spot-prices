<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Submission\Submission;
use Illuminate\Foundation\Http\FormRequest;

final class SubmissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'repository_url' => ['required', 'url', 'max:255'],
            'commit_sha' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date_format:Y-m-d'],
            'window' => [
                'required',
                'integer',
                'min:'.config('prices.window.min_hours'),
                'max:'.config('prices.window.max_hours'),
            ],
        ];
    }

    public function toSubmission(): Submission
    {
        return new Submission(
            trim((string) $this->string('name')),
            trim((string) $this->string('email')),
            trim((string) $this->string('phone')),
            trim((string) $this->string('repository_url')),
            trim((string) $this->string('commit_sha')),
        );
    }
}
