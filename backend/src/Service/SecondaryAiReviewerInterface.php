<?php

namespace App\Service;

use App\Dto\StoneAiReviewRequest;
use App\Dto\StoneAiReviewResponse;

interface SecondaryAiReviewerInterface
{
    public function review(StoneAiReviewRequest $request): StoneAiReviewResponse;
}
