<?php
namespace App\Controller;

use App\Entity\PublicIdentification;
use App\Exception\InvalidMediaException;
use App\Repository\PublicIdentificationRepository;
use App\Service\PublicIdentificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/** Identification visiteur : 2/jour/IP, analyse Redis low_priority, expiration 1 h. */
#[Route('/api/public/identifications')]
final class PublicIdentificationController extends AbstractController
{
    public function __construct(private readonly PublicIdentificationService $service, private readonly PublicIdentificationRepository $identifications, private readonly RateLimiterFactory $publicIdentificationLimiter) {}
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $ip = $request->getClientIp() ?? 'unknown';
        if (!$this->publicIdentificationLimiter->create($ip)->consume()->isAccepted()) return $this->json(['message' => 'Limite atteinte : 2 identifications publiques par jour.'], Response::HTTP_TOO_MANY_REQUESTS);
        $file = $request->files->get('image');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) return $this->json(['message' => 'Une image est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        try { $identification = $this->service->submit(hash('sha256', $ip), $file); }
        catch (InvalidMediaException $e) { return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
        if ($identification === null) return $this->json(['message' => 'Une analyse est déjà en cours.'], Response::HTTP_CONFLICT);
        return $this->json($this->serialize($identification), Response::HTTP_ACCEPTED);
    }
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try { $identification = $this->identifications->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { return $this->json(['message' => 'Identifiant invalide.'], 400); }
        if (!$identification instanceof PublicIdentification || $identification->isExpired()) return $this->json(['message' => 'Résultat introuvable ou expiré.'], 404);
        return $this->json($this->serialize($identification));
    }
    private function serialize(PublicIdentification $i): array { return ['id' => $i->getId()->toRfc4122(), 'status' => $i->getStatus(), 'result' => $i->getResult(), 'expiresAt' => $i->getExpiresAt()->format(DATE_ATOM)]; }
}
