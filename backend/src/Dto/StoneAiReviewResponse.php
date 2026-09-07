<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exception\CloudflareAiException;

final readonly class StoneAiReviewResponse
{
    private function __construct(
        public string $decision,
        public ?string $stoneId,
        public float $confidence,
        public array $reasoningSummary,
        public array $warnings,
        public string $model,
    ) {}

    public static function fromArray(array $data, StoneAiReviewRequest $request, string $model): self
    {
        $fields = ['decision', 'stoneId', 'confidence', 'reasoningSummary', 'warnings'];
        if (count($data) !== count($fields) || array_diff($fields, array_keys($data)) !== []
            || !in_array($data['decision'], ['candidate', 'unknown'], true)
            || (!is_float($data['confidence']) && !is_int($data['confidence']))
            || !is_finite((float) $data['confidence']) || $data['confidence'] < 0 || $data['confidence'] > 1) {
            throw new CloudflareAiException('invalid_response');
        }
        if (($data['decision'] === 'unknown' && $data['stoneId'] !== null)
            || ($data['decision'] === 'candidate' && !in_array($data['stoneId'], array_column($request->candidates, 'stoneId'), true))) {
            throw new CloudflareAiException('inconsistent_response');
        }
        foreach (['reasoningSummary', 'warnings'] as $field) {
            if (!is_array($data[$field]) || !array_is_list($data[$field]) || count($data[$field]) > 5) throw new CloudflareAiException('invalid_response');
            foreach ($data[$field] as $text) {
                if (!is_string($text) || mb_strlen($text) > 200 || trim($text) === '') throw new CloudflareAiException('invalid_response');
            }
        }
        return new self($data['decision'], $data['stoneId'], (float) $data['confidence'], $data['reasoningSummary'], $data['warnings'], $model);
    }

    public static function schema(StoneAiReviewRequest $request): array
    {
        return ['type' => 'object', 'additionalProperties' => false,
            'required' => ['decision', 'stoneId', 'confidence', 'reasoningSummary', 'warnings'],
            'properties' => [
                'decision' => ['type' => 'string', 'enum' => ['candidate', 'unknown']],
                'stoneId' => ['enum' => [null, ...array_column($request->candidates, 'stoneId')]],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'reasoningSummary' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200]],
                'warnings' => ['type' => 'array', 'maxItems' => 5, 'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200]],
            ]];
    }
}
