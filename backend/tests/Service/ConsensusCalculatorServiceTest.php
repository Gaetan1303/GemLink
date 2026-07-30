<?php



namespace App\Tests\Service;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Validation;
use App\Repository\PierreRepository;
use App\Repository\ValidationRepository;
use App\Service\AdminSettingsProvider;
use App\Service\ConsensusCalculatorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConsensusCalculatorServiceTest extends TestCase
{
    private ValidationRepository&MockObject $validations;
    private PierreRepository&MockObject $pierres;
    private AdminSettingsProvider&MockObject $adminSettings;
    private ConsensusCalculatorService $consensusCalculator;
    private Publication $publication;
    private Pierre $amethyste;
    private Pierre $quartz;

    protected function setUp(): void
    {
        $this->validations = $this->createMock(ValidationRepository::class);
        $this->pierres = $this->createMock(PierreRepository::class);
        $this->adminSettings = $this->createMock(AdminSettingsProvider::class);

        $this->consensusCalculator = new ConsensusCalculatorService($this->validations, $this->pierres, $this->adminSettings);

        $this->publication = new Publication($this->makeUser(50), 'https://media.gem-link.org/x.jpg');
        $this->amethyste = new Pierre('Améthyste');
        $this->quartz = new Pierre('Quartz rose');

        $this->adminSettings->method('getConsensusThreshold')->willReturn(0.66);
    }

    public function testNoValidationsReturnNoneResult(): void
    {
        $this->validations->method('findByPublication')->willReturn([]);

        $result = $this->consensusCalculator->calculate($this->publication);

        $this->assertFalse($result->isValidated);
        $this->assertNull($result->winningPierreId);
    }

    public function testConfirmVotesTargetTheirOwnPierre(): void
    {
        $confirm = $this->makeValidation(Validation::ACTION_CONFIRM, trustScore: 80, pierre: $this->amethyste);
        $this->validations->method('findByPublication')->willReturn([$confirm]);

        $result = $this->consensusCalculator->calculate($this->publication);

        $this->assertTrue($result->isValidated);
        $this->assertTrue($result->winningPierreId->equals($this->amethyste->getId()));
        $this->assertSame(1.0, $result->score);
    }

    public function testHighTrustCorrectionOutweighsSeveralLowTrustConfirmations(): void
    {
        // CA-3 : un Trust Score de 80 doit peser 4x plus qu'un Trust Score
        // de 20. Une seule CORRECT à 80 doit l'emporter sur 3 CONFIRM à 20
        // (total 60).
        $this->pierres->method('findOneByNameIgnoreCase')->with('Quartz rose')->willReturn($this->quartz);

        $correction = $this->makeValidation(Validation::ACTION_CORRECT, trustScore: 80, pierre: $this->amethyste, proposedLabel: 'Quartz rose');
        $confirm1 = $this->makeValidation(Validation::ACTION_CONFIRM, trustScore: 20, pierre: $this->amethyste);
        $confirm2 = $this->makeValidation(Validation::ACTION_CONFIRM, trustScore: 20, pierre: $this->amethyste);
        $confirm3 = $this->makeValidation(Validation::ACTION_CONFIRM, trustScore: 20, pierre: $this->amethyste);

        $this->validations->method('findByPublication')->willReturn([$correction, $confirm1, $confirm2, $confirm3]);

        $result = $this->consensusCalculator->calculate($this->publication);

        $this->assertTrue($result->winningPierreId->equals($this->quartz->getId()));
        // 80 / (80 + 60) = 0.571..., sous le seuil par défaut de 0.66.
        $this->assertFalse($result->isValidated);
        $this->assertEqualsWithDelta(0.571, $result->score, 0.001);
    }

    public function testRejectDilutesConsensusWithoutVotingForAnyLabel(): void
    {
        $confirm = $this->makeValidation(Validation::ACTION_CONFIRM, trustScore: 70, pierre: $this->amethyste);
        $reject = $this->makeValidation(Validation::ACTION_REJECT, trustScore: 30, pierre: $this->amethyste);

        $this->validations->method('findByPublication')->willReturn([$confirm, $reject]);

        $result = $this->consensusCalculator->calculate($this->publication);

        // 70 / (70 + 30) = 0.7, au-dessus du seuil par défaut de 0.66.
        $this->assertTrue($result->isValidated);
        $this->assertEqualsWithDelta(0.7, $result->score, 0.001);
    }

    public function testUnresolvedProposedLabelDoesNotWinButStillDilutes(): void
    {
        // proposedLabel en texte libre qui ne correspond à aucun Pierre du
        // catalogue : ne peut pas remporter le consensus, mais compte dans
        // le total (comme un REJECT) plutôt que d'être ignoré.
        $this->pierres->method('findOneByNameIgnoreCase')->willReturn(null);

        $correction = $this->makeValidation(Validation::ACTION_CORRECT, trustScore: 40, pierre: $this->amethyste, proposedLabel: 'Minéral inconnu');
        $confirm = $this->makeValidation(Validation::ACTION_CONFIRM, trustScore: 60, pierre: $this->amethyste);

        $this->validations->method('findByPublication')->willReturn([$correction, $confirm]);

        $result = $this->consensusCalculator->calculate($this->publication);

        $this->assertTrue($result->winningPierreId->equals($this->amethyste->getId()));
        $this->assertEqualsWithDelta(0.6, $result->score, 0.001);
    }

    public function testOnlyRejectVotesReturnNoneResult(): void
    {
        $reject = $this->makeValidation(Validation::ACTION_REJECT, trustScore: 100, pierre: $this->amethyste);
        $this->validations->method('findByPublication')->willReturn([$reject]);

        $result = $this->consensusCalculator->calculate($this->publication);

        $this->assertFalse($result->isValidated);
        $this->assertNull($result->winningPierreId);
    }

    private function makeValidation(string $action, int $trustScore, Pierre $pierre, ?string $proposedLabel = null): Validation
    {
        $validation = new Validation($this->makeUser($trustScore), $this->publication, $pierre, $trustScore);
        $validation->setAction($action);
        $validation->setProposedLabel($proposedLabel);

        return $validation;
    }

    private function makeUser(int $trustScore): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');
        $user->setTrustScore($trustScore);

        return $user;
    }
}
