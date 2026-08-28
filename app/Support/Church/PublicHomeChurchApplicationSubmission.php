<?php

namespace App\Support\Church;

use App\Models\HomeChurchApplication;

final readonly class PublicHomeChurchApplicationSubmission
{
    public function __construct(
        public HomeChurchApplication $application,
        public bool $wasCreated,
    ) {}
}
