<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NearestReference
{
    public function __construct(
        #[Assert\NotBlank] #[Assert\Length(max: 100)] public string $name,
        #[Assert\Range(min: 0, max: 1)] public float $similarity,
    ) {}
}
