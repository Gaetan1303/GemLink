<?php

namespace App\Message;

final readonly class RunFineTuningMessage
{
    public function __construct(public string $jobId) {}
}
