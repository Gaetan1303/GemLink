<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Internal DTO, never deserialized from an HTTP request. No URL or free user metadata. */
final readonly class StoneAiReviewRequest
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Uuid] public string $requestId,
        #[Assert\NotBlank] #[Assert\Length(max: 100)] public string $requesterKey,
        #[Assert\Count(min: 1, max: 10)] #[Assert\Valid] public array $candidates,
        #[Assert\Range(min: 0, max: 1)] public float $modelConfidence,
        #[Assert\Count(max: 20)] #[Assert\Valid] public array $nearestReferences,
        #[Assert\Length(min: 1, max: 13981016)] public string $imageBase64,
        #[Assert\Choice(choices: ['image/jpeg', 'image/png', 'image/webp'])] public string $mimeType,
    ) {}
}
