<?php
namespace App\Dto;

use App\Exception\AiAnalysisException;

/**
 * US 3.1 CA-2 : représentation typée de la réponse JSON de FastAPI POST
 * /analyze (schema.py::StoneAnalysisResponse côté Python). Isole le handler
 * Symfony du format exact renvoyé par le service IA.
 */
final class AiAnalysisResult
{
    /**
     * @param float[] $embedding
     * @param array{yolo: string, vit: string, clip: string} $modelVersion
     * @param list<array{nom: string, confidence: float, detectorConfidence: float, bbox: list<int>}> $detections
     */
    private function __construct(
        private readonly string $label,
        private readonly ?string $category,
        private readonly ?string $hardnessRange,
        private readonly ?string $crystalSystem,
        private readonly ?string $composition,
        private readonly ?string $description,
        private readonly float $confidence,
        private readonly float $detectorConfidence,
        private readonly array $embedding,
        private readonly array $modelVersion,
        private readonly array $detections,
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['nom', 'confidence', 'detector_confidence', 'embedding', 'model_version'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw AiAnalysisException::missingField($required);
            }
        }

        if (!is_array($data['embedding']) || count($data['embedding']) !== 512) {
            throw AiAnalysisException::invalidEmbedding(is_array($data['embedding']) ? count($data['embedding']) : 0);
        }

        if (!is_array($data['model_version'])) {
            throw AiAnalysisException::missingField('model_version');
        }

        foreach (['yolo', 'vit', 'clip'] as $modelType) {
            if (!isset($data['model_version'][$modelType]) || trim((string) $data['model_version'][$modelType]) === '') {
                throw AiAnalysisException::missingField(sprintf('model_version.%s', $modelType));
            }
        }

        $physique = $data['physique'] ?? [];

        return new self(
            label: (string) $data['nom'],
            category: $data['categorie_geologique'] ?? null,
            hardnessRange: $physique['durete'] ?? null,
            crystalSystem: $physique['systeme_cristallin'] ?? null,
            composition: $data['formule_chimique'] ?? null,
            description: $data['description'] ?? null,
            confidence: (float) $data['confidence'],
            detectorConfidence: (float) $data['detector_confidence'],
            embedding: array_map('floatval', $data['embedding']),
            modelVersion: array_map('strval', $data['model_version']),
            detections: self::normalizeDetections($data),
        );
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getCrystalSystem(): ?string
    {
        return $this->crystalSystem;
    }

    public function getComposition(): ?string
    {
        return $this->composition;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function getDetectorConfidence(): float
    {
        return $this->detectorConfidence;
    }

    /**
     * @return float[]
     */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /** @return array{yolo: string, vit: string, clip: string} */
    public function getModelVersion(): array
    {
        return $this->modelVersion;
    }

    public function getClipModelVersion(): string
    {
        return $this->modelVersion['clip'];
    }

    /** @return list<array{nom: string, confidence: float, detectorConfidence: float, bbox: list<int>}> */
    public function getDetections(): array
    {
        return $this->detections;
    }

    /** @return list<array{nom: string, confidence: float, detectorConfidence: float, bbox: list<int>}> */
    private static function normalizeDetections(array $data): array
    {
        if (!isset($data['detections']) || !is_array($data['detections']) || $data['detections'] === []) {
            return [[
                'nom' => (string) $data['nom'],
                'confidence' => (float) $data['confidence'],
                'detectorConfidence' => (float) $data['detector_confidence'],
                'bbox' => array_map('intval', array_pad(array_slice(is_array($data['bbox'] ?? null) ? $data['bbox'] : [], 0, 4), 4, 0)),
            ]];
        }

        $detections = [];
        foreach ($data['detections'] as $index => $detection) {
            if (!is_array($detection)) {
                throw AiAnalysisException::missingField(sprintf('detections.%s', $index));
            }
            foreach (['label', 'confidence', 'detector_confidence', 'bbox'] as $field) {
                if (!array_key_exists($field, $detection)) {
                    throw AiAnalysisException::missingField(sprintf('detections.%s.%s', $index, $field));
                }
            }
            if (!is_array($detection['bbox']) || count($detection['bbox']) !== 4) {
                throw AiAnalysisException::missingField(sprintf('detections.%s.bbox', $index));
            }

            $detections[] = [
                'nom' => (string) $detection['label'],
                'confidence' => (float) $detection['confidence'],
                'detectorConfidence' => (float) $detection['detector_confidence'],
                'bbox' => array_map('intval', $detection['bbox']),
            ];
        }

        return $detections;
    }

    /**
     * Extrait une valeur numérique exploitable de la plage Mohs textuelle
     * renvoyée par l'agent connaissance (ex : "6 - 6.5" -> 6.25), pour la
     * colonne pierre.hardness (NUMERIC).
     */
    public function getHardnessValue(): ?float
    {
        if ($this->hardnessRange === null) {
            return null;
        }

        if (!preg_match_all('/\d+(?:\.\d+)?/', $this->hardnessRange, $matches)) {
            return null;
        }

        $values = array_map('floatval', $matches[0]);

        return array_sum($values) / count($values);
    }
}
