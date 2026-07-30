<?php



namespace App\Tests\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Vitrine;
use App\Entity\VitrinePublication;
use App\Exception\VitrineAccessDeniedException;
use App\Exception\VitrineEmptyException;
use App\Exception\VitrineValidationException;
use App\Repository\VitrinePublicationRepository;
use App\Repository\VitrineRepository;
use App\Repository\VitrineMediaRepository;
use App\Service\AiOrchestrationService;
use App\Service\Media\MediaUploadService;
use App\Service\VitrineQrCodeService;
use App\Service\VitrineService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * US 4.1 — CA-1 (création + slug), CA-2 (ajout d'items), CA-3 (réordonnancement),
 * CA-4 (garde-fou publication vide). VitrineRepository/VitrinePublicationRepository
 * sont mockés (même isolation que PostServiceTest vis-à-vis de TagRepository).
 */
final class VitrineServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private VitrineRepository&MockObject $vitrines;
    private VitrinePublicationRepository&MockObject $vitrinePublications;
    private VitrineService $vitrineService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->vitrines = $this->createMock(VitrineRepository::class);
        $this->vitrinePublications = $this->createMock(VitrinePublicationRepository::class);

        $this->vitrineService = new VitrineService(
            $this->em,
            $this->vitrines,
            $this->vitrinePublications,
            $this->createMock(VitrineMediaRepository::class),
            $this->createMock(MediaUploadService::class),
            $this->createMock(AiOrchestrationService::class),
            $this->createMock(VitrineQrCodeService::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    // ── CA-1 : création ────────────────────────────────────────

    public function testCreateVitrineGeneratesUniqueSlugFromTitle(): void
    {
        $user = $this->makeUser();

        $this->vitrines->expects($this->once())
            ->method('generateUniqueSlug')
            ->with('Mes Améthystes')
            ->willReturn('mes-amethystes');

        $this->vitrines->expects($this->once())->method('save');

        $vitrine = $this->vitrineService->createVitrine($user, 'Mes Améthystes', null);

        $this->assertSame('Mes Améthystes', $vitrine->getTitle());
        $this->assertSame('mes-amethystes', $vitrine->getSlug());
        $this->assertNull($vitrine->getDescription());
        $this->assertSame(Vitrine::STATUS_DRAFT, $vitrine->getStatus());
    }

    public function testCreateVitrineTrimsAndSetsOptionalDescription(): void
    {
        $user = $this->makeUser();
        $this->vitrines->method('generateUniqueSlug')->willReturn('ma-vitrine');

        $vitrine = $this->vitrineService->createVitrine($user, 'Ma Vitrine', '  Une belle collection  ');

        $this->assertSame('Une belle collection', $vitrine->getDescription());
    }

    public function testCreateVitrineIgnoresBlankDescription(): void
    {
        $user = $this->makeUser();
        $this->vitrines->method('generateUniqueSlug')->willReturn('ma-vitrine');

        $vitrine = $this->vitrineService->createVitrine($user, 'Ma Vitrine', '   ');

        $this->assertNull($vitrine->getDescription());
    }

    public function testCreateVitrineRejectsBlankTitle(): void
    {
        $this->vitrines->expects($this->never())->method('save');

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->createVitrine($this->makeUser(), '   ', null);
    }

    public function testCreateVitrineRejectsTitleOverMaxLength(): void
    {
        $tooLongTitle = str_repeat('a', 101);

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->createVitrine($this->makeUser(), $tooLongTitle, null);
    }

    public function testCreateVitrineRejectsDescriptionOverMaxLength(): void
    {
        $this->vitrines->method('generateUniqueSlug')->willReturn('ma-vitrine');
        $tooLongDescription = str_repeat('a', 501);

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->createVitrine($this->makeUser(), 'Ma Vitrine', $tooLongDescription);
    }

    // ── CA-1 : mise à jour ─────────────────────────────────────

    public function testUpdateVitrineByOwnerChangesTitleAndRegeneratesSlug(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner, 'Ancien Titre', 'ancien-titre');

        $this->vitrines->expects($this->once())
            ->method('generateUniqueSlug')
            ->with('Nouveau Titre', $this->callback(
                fn (Uuid $id) => $id->equals($vitrine->getId())
            ))
            ->willReturn('nouveau-titre');

        $this->em->expects($this->once())->method('flush');

        $updated = $this->vitrineService->updateVitrine($vitrine, $owner, 'Nouveau Titre', null);

        $this->assertSame('Nouveau Titre', $updated->getTitle());
        $this->assertSame('nouveau-titre', $updated->getSlug());
    }

    public function testUpdateVitrineKeepsSlugWhenTitleUnchanged(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner, 'Mon Titre', 'mon-titre');

        $this->vitrines->expects($this->never())->method('generateUniqueSlug');
        $this->em->expects($this->once())->method('flush');

        $this->vitrineService->updateVitrine($vitrine, $owner, 'Mon Titre', null);

        $this->assertSame('mon-titre', $vitrine->getSlug());
    }

    public function testUpdateVitrineByNonOwnerThrowsAccessDenied(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(VitrineAccessDeniedException::class);

        $this->vitrineService->updateVitrine($vitrine, $stranger, 'Titre volé', null);
    }

    // ── Suppression ────────────────────────────────────────────

    public function testDeleteVitrineByOwnerRemovesAndFlushes(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);

        $this->em->expects($this->once())->method('remove')->with($vitrine);
        $this->em->expects($this->once())->method('flush');

        $this->vitrineService->deleteVitrine($vitrine, $owner);
    }

    public function testDeleteVitrineByNonOwnerThrowsAccessDenied(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);

        $this->em->expects($this->never())->method('remove');

        $this->expectException(VitrineAccessDeniedException::class);

        $this->vitrineService->deleteVitrine($vitrine, $stranger);
    }

    // ── CA-2 : ajout d'items ───────────────────────────────────

    public function testAddItemAppendsAtNextPosition(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publicationA = $this->makePublication($owner);
        $publicationB = $this->makePublication($owner);

        $itemA = $this->vitrineService->addItem($vitrine, $owner, $publicationA);
        $itemB = $this->vitrineService->addItem($vitrine, $owner, $publicationB);

        $this->assertSame(0, $itemA->getPosition());
        $this->assertSame(1, $itemB->getPosition());
        $this->assertCount(2, $vitrine->getItems());
    }

    public function testAddItemRejectsDuplicatePublication(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publication = $this->makePublication($owner);

        $this->vitrineService->addItem($vitrine, $owner, $publication);

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->addItem($vitrine, $owner, $publication);
    }

    public function testAddItemRejectsDeletedPublication(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publication = $this->makePublication($owner);
        $publication->setDeletedAt(new DateTimeImmutable());

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->addItem($vitrine, $owner, $publication);
    }

    public function testAddItemByNonOwnerThrowsAccessDenied(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publication = $this->makePublication($owner);

        $this->expectException(VitrineAccessDeniedException::class);

        $this->vitrineService->addItem($vitrine, $stranger, $publication);
    }

    public function testRemoveItemByOwnerRemovesFromCollection(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publication = $this->makePublication($owner);
        $item = $this->vitrineService->addItem($vitrine, $owner, $publication);

        $this->vitrinePublications->expects($this->once())->method('remove')->with($item);

        $this->vitrineService->removeItem($vitrine, $owner, $item);

        $this->assertCount(0, $vitrine->getItems());
    }

    // ── CA-3 : réordonnancement ────────────────────────────────

    public function testReorderItemsAppliesNewPositions(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publicationA = $this->makePublication($owner);
        $publicationB = $this->makePublication($owner);
        $publicationC = $this->makePublication($owner);

        $this->vitrineService->addItem($vitrine, $owner, $publicationA);
        $this->vitrineService->addItem($vitrine, $owner, $publicationB);
        $this->vitrineService->addItem($vitrine, $owner, $publicationC);

        $this->em->expects($this->once())->method('flush');

        $this->vitrineService->reorderItems($vitrine, $owner, [
            ['type' => 'post', 'id' => $publicationC->getId()->toRfc4122()],
            ['type' => 'post', 'id' => $publicationA->getId()->toRfc4122()],
            ['type' => 'post', 'id' => $publicationB->getId()->toRfc4122()],
        ]);

        $itemsByPublication = [];
        foreach ($vitrine->getItems() as $item) {
            $itemsByPublication[$item->getPublication()->getId()->toRfc4122()] = $item->getPosition();
        }

        $this->assertSame(0, $itemsByPublication[$publicationC->getId()->toRfc4122()]);
        $this->assertSame(1, $itemsByPublication[$publicationA->getId()->toRfc4122()]);
        $this->assertSame(2, $itemsByPublication[$publicationB->getId()->toRfc4122()]);
    }

    public function testReorderItemsRejectsMismatchedCount(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publicationA = $this->makePublication($owner);
        $publicationB = $this->makePublication($owner);

        $this->vitrineService->addItem($vitrine, $owner, $publicationA);
        $this->vitrineService->addItem($vitrine, $owner, $publicationB);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->reorderItems($vitrine, $owner, [['type' => 'post', 'id' => $publicationA->getId()->toRfc4122()]]);
    }

    public function testReorderItemsRejectsUnknownPublicationId(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publicationA = $this->makePublication($owner);
        $this->vitrineService->addItem($vitrine, $owner, $publicationA);

        $this->expectException(VitrineValidationException::class);

        $this->vitrineService->reorderItems($vitrine, $owner, [['type' => 'post', 'id' => Uuid::v7()->toRfc4122()]]);
    }

    public function testReorderItemsByNonOwnerThrowsAccessDenied(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $publication = $this->makePublication($owner);
        $this->vitrineService->addItem($vitrine, $owner, $publication);

        $this->expectException(VitrineAccessDeniedException::class);

        $this->vitrineService->reorderItems($vitrine, $stranger, [$publication->getId()->toRfc4122()]);
    }

    // ── CA-4 : publication ─────────────────────────────────────

    public function testPublishEmptyVitrineThrowsVitrineEmptyException(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(VitrineEmptyException::class);

        $this->vitrineService->publish($vitrine, $owner);
    }

    public function testPublishVitrineWithItemsSucceeds(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $this->vitrineService->addItem($vitrine, $owner, $this->makePublication($owner));

        $this->em->expects($this->once())->method('flush');

        $this->vitrineService->publish($vitrine, $owner);

        $this->assertTrue($vitrine->isPublished());
    }

    public function testPublishByNonOwnerThrowsAccessDenied(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $this->vitrineService->addItem($vitrine, $owner, $this->makePublication($owner));

        $this->expectException(VitrineAccessDeniedException::class);

        $this->vitrineService->publish($vitrine, $stranger);
    }

    public function testUnpublishSetsStatusBackToDraft(): void
    {
        $owner = $this->makeUser();
        $vitrine = $this->makeVitrine($owner);
        $this->vitrineService->addItem($vitrine, $owner, $this->makePublication($owner));
        $vitrine->publish();

        $this->em->expects($this->once())->method('flush');

        $this->vitrineService->unpublish($vitrine, $owner);

        $this->assertSame(Vitrine::STATUS_DRAFT, $vitrine->getStatus());
    }

    // ── Vue ────────────────────────────────────────────────────

    public function testRecordViewIncrementsViewCountAndFlushes(): void
    {
        $vitrine = $this->makeVitrine($this->makeUser());

        $this->em->expects($this->once())->method('flush');

        $this->vitrineService->recordView($vitrine);

        $this->assertSame(1, $vitrine->getViewCount());
    }

    private function makeUser(string $role = 'USER'): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole($role);

        return $user;
    }

    private function makeVitrine(User $owner, string $title = 'Ma Vitrine', string $slug = 'ma-vitrine'): Vitrine
    {
        return new Vitrine($owner, $title, $slug);
    }

    private function makePublication(User $author): Publication
    {
        return new Publication($author, 'https://media.gem-link.org/publications/2026/07/' . uniqid() . '.jpg');
    }
}
