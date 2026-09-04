<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Validation;
use App\Exception\ValidationPayloadException;
use App\Repository\PublicationRepository;
use App\Repository\ValidationRepository;
use App\Service\ValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.7 — Validation communautaire de l'identification IA.
 *
 * Body JSON attendu : { "action": "CONFIRM"|"CORRECT"|"REJECT", "proposedLabel"?: string }
 * "proposedLabel" est du texte libre (CA-1) : l'autocomplétion catalogue
 * côté front n'est qu'une aide de saisie, pas une contrainte de stockage.
 */
#[Route('/api/publications')]
final class ValidationController extends AbstractController
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly ValidationRepository $validations,
        private readonly ValidationService $validationService,
    ) {
    }

    #[Route('/{id}/validations', name: 'validation_submit', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function submit(string $id, Request $request): JsonResponse
    {
        /** @var User $validator */
        $validator = $this->getUser();

        $publication = $this->findActiveOrFail($id);

        if ($publication instanceof JsonResponse) {
            return $publication;
        }

        $payload = json_decode($request->getContent(), true);
        $action = is_array($payload) && is_string($payload['action'] ?? null) ? $payload['action'] : null;
        $proposedLabel = is_array($payload) && is_string($payload['proposedLabel'] ?? null)
            ? trim($payload['proposedLabel'])
            : null;

        if ($action === null || !in_array($action, Validation::ACTIONS, true)) {
            return $this->json(['message' => 'action est requis et doit être CONFIRM, CORRECT ou REJECT.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $validation = $this->validationService->submitValidation($publication, $validator, $action, $proposedLabel);
        } catch (ValidationPayloadException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeValidation($validation), Response::HTTP_CREATED);
    }

    /**
     * CA-1 : validation de l'utilisateur courant pour ce post (au plus une,
     * contrainte uq_validation_pub_user), pour pré-remplir le composant
     * front avec son choix précédent.
     */
    #[Route('/{id}/validations/mine', name: 'validation_mine', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function mine(string $id): JsonResponse
    {
        /** @var User $validator */
        $validator = $this->getUser();

        $publication = $this->findActiveOrFail($id);

        if ($publication instanceof JsonResponse) {
            return $publication;
        }

        $validation = $this->validations->findOneByPublicationAndUser($publication, $validator);

        return $this->json($validation !== null ? $this->serializeValidation($validation) : null);
    }

    /**
     * Repris de PublicationController::findActiveOrFail() pour une
     * résolution d'id cohérente entre les deux contrôleurs.
     */
    private function findActiveOrFail(string $id): Publication|JsonResponse
    {
        try {
            $postId = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Identifiant de post invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $publication = $this->publications->findOneActiveById($postId);

        if ($publication === null) {
            return $this->json(['message' => 'Post introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $publication;
    }

    private function serializeValidation(Validation $validation): array
    {
        return [
            'id' => (string) $validation->getId(),
            'action' => $validation->getAction(),
            'pierre' => [
                'id' => (string) $validation->getPierre()->getId(),
                'name' => $validation->getPierre()->getName(),
            ],
            'proposedLabel' => $validation->getProposedLabel(),
            'trustScoreSnapshot' => $validation->getTrustScoreSnapshot(),
            'createdAt' => $validation->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
