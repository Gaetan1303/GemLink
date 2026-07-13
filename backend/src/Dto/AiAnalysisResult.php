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
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['nom', 'confidence', 'detector_confidence', 'embedding'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw AiAnalysisException::missingField($required);
            }
        }

        if (!is_array($data['embedding']) || count($data['embedding']) !== 512) {
            throw AiAnalysisException::invalidEmbedding(is_array($data['embedding']) ? count($data['embedding']) : 0);
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