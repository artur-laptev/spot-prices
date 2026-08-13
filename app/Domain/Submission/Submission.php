<?php

declare(strict_types=1);

namespace App\Domain\Submission;

final readonly class Submission
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $repositoryUrl,
        public string $commitSha,
    ) {}
}
