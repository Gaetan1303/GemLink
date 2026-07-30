<?php



namespace App\Tests\Entity;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Validation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    private Publication $publication;
    private User $validator;
    private Pierre $pierre;

    protected function setUp(): void
    {
        $this->publication = $this->createMock(Publication::class);
        $this->validator = $this->createMock(User::class);
        $this->pierre = new Pierre('Améthyste');
    }

    public function testConstructSetsAllFieldsWithDefaultActionConfirm(): void
    {
        $validation = new Validation($this->validator, $this->publication, $this->pierre, 42);

        $this->assertSame($this->validator, $validation->getUser());
        $this->assertSame($this->publication, $validation->getPublication());
        $this->assertSame($this->pierre, $validation->getPierre());
        $this->assertSame(Validation::ACTION_CONFIRM, $validation->getAction());
        $this->assertSame(42, $validation->getTrustScoreSnapshot());
        $this->assertNull($validation->getProposedLabel());
    }

    public function testInvalidActionIsRejected(): void
    {
        $validation = new Validation($this->validator, $this->publication, $this->pierre, 42);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action de validation invalide.');

        $validation->setAction('NOT_A_REAL_ACTION');
    }

    public function testSwitchingAwayFromCorrectClearsProposedLabel(): void
    {
        $validation = new Validation($this->validator, $this->publication, $this->pierre, 42);
        $validation->setAction(Validation::ACTION_CORRECT);
        $validation->setProposedLabel('Quartz rose');

        $validation->setAction(Validation::ACTION_REJECT);

        $this->assertNull($validation->getProposedLabel());
    }

    public function testTrustScoreSnapshotCanBeUpdatedOnResubmission(): void
    {
        // CA-2 : à chaque resoumission (upsert), un nouveau snapshot est
        // pris — contrairement au Trust Score réel, il ne bouge jamais
        // tout seul entre deux soumissions.
        $validation = new Validation($this->validator, $this->publication, $this->pierre, 30);

        $validation->setTrustScoreSnapshot(85);

        $this->assertSame(85, $validation->getTrustScoreSnapshot());
    }

    public function testEachValidationGetsAUniqueId(): void
    {
        $first = new Validation($this->validator, $this->publication, $this->pierre, 42);
        $second = new Validation($this->validator, $this->publication, $this->pierre, 42);

        $this->assertFalse($first->getId()->equals($second->getId()));
    }
}
