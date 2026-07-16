<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\PublicationPierre;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires purs (pas de base de données) sur PublicationPierre :
 * validation de la confidence (contrat imposé par setConfidence) et logique
 * de seuil isHighConfidence(), utilisée pour la modération auto (US 4.x).
 */
final class PublicationPierreTest extends TestCase
{
    private Publication $publication;
    private Pierre $pierre;

    protected function setUp(): void
    {
        // Publication est mockée : PublicationPierre n'a besoin que d'une
        // référence typée, pas du comportement réel de l'entité.
        $this->publication = $this->createMock(Publication::class);
        $this->pierre = new Pierre('Améthyste');
    }

    #[Test]
    public function construitAvecUneConfidenceValide(): void
    {
        $match = new PublicationPierre($this->publication, $this->pierre, 0.87);

        $this->assertSame(0.87, $match->getConfidence());
        $this->assertSame($this->publication, $match->getPublication());
        $this->assertSame($this->pierre, $match->getPierre());
    }

    #[Test]
    #[DataProvider('confidencesInvalides')]
    public function rejetteUneConfidenceHorsBornes(float $confidenceInvalide): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La confiance doit être comprise entre 0 et 1.');

        new PublicationPierre($this->publication, $this->pierre, $confidenceInvalide);
    }

    public static function confidencesInvalides(): array
    {
        return [
            'négative' => [-0.01],
            'supérieure à 1' => [1.01],
            'très négative' => [-5.0],
            'très supérieure à 1' => [42.0],
        ];
    }

    #[Test]
    #[DataProvider('bornesValides')]
    public function accepteLesValeursLimitesInclusives(float $confidenceLimite): void
    {
        $match = new PublicationPierre($this->publication, $this->pierre, $confidenceLimite);

        $this->assertSame($confidenceLimite, $match->getConfidence());
    }

    public static function bornesValides(): array
    {
        return [
            'zéro' => [0.0],
            'un' => [1.0],
        ];
    }

    #[Test]
    public function setConfidencePermetDeMettreAJourApresConstruction(): void
    {
        $match = new PublicationPierre($this->publication, $this->pierre, 0.5);

        $match->setConfidence(0.92);

        $this->assertSame(0.92, $match->getConfidence());
    }

    #[Test]
    public function laConfidenceEstArrondieAQuatreDecimales(): void
    {
        // Cohérence avec le mapping Doctrine : decimal(5,4). Un appelant qui
        // passerait plus de précision ne doit pas provoquer d'erreur SQL
        // silencieuse plus tard — on vérifie que le stockage interne tronque
        // proprement au lieu de laisser trainer une valeur non représentable.
        $match = new PublicationPierre($this->publication, $this->pierre, 0.123456789);

        $this->assertSame(0.1235, $match->getConfidence());
    }

    #[Test]
    #[DataProvider('casConfianceElevee')]
    public function isHighConfidenceUtiliseLeSeuilParDefautDe075(float $confidence, bool $attendu): void
    {
        $match = new PublicationPierre($this->publication, $this->pierre, $confidence);

        $this->assertSame($attendu, $match->isHighConfidence());
    }

    public static function casConfianceElevee(): array
    {
        return [
            'juste sous le seuil' => [0.7499, false],
            'exactement au seuil' => [0.75, true],
            'nettement au-dessus' => [0.95, true],
            'nettement en dessous' => [0.1, false],
        ];
    }

    #[Test]
    public function isHighConfidenceAccepteUnSeuilPersonnalise(): void
    {
        $match = new PublicationPierre($this->publication, $this->pierre, 0.6);

        $this->assertTrue($match->isHighConfidence(0.5));
        $this->assertFalse($match->isHighConfidence(0.9));
    }
}