<?php

namespace App\Controller;

use App\Entity\ParametreSysteme;
use App\Repository\ParametreSystemeRepository;
use App\Service\AdminSettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * US 2.7 CA-4/CA-5 : pilotage par l'Admin des seuils de validation
 * communautaire (score de consensus, Trust Score du dataset candidat).
 * Réservé à ROLE_ADMIN, cohérent avec User::ROLES_AUTORISES.
 */
#[Route('/api/admin/validation-settings')]
#[IsGranted('ROLE_ADMIN')]
final class AdminValidationSettingsController extends AbstractController
{
    private const CLE_CONSENSUS_THRESHOLD = 'validation.consensus_threshold';
    private const CLE_DATASET_CANDIDATE_TRUST_THRESHOLD = 'validation.dataset_candidate_trust_threshold';

    public function __construct(
        private readonly AdminSettingsProvider $adminSettings,
        private readonly ParametreSystemeRepository $parametres,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'admin_validation_settings_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return $this->json($this->currentSettings());
    }

    #[Route('', name: 'admin_validation_settings_update', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('consensusThreshold', $payload)) {
            $value = $payload['consensusThreshold'];

            if (!is_numeric($value) || $value < 0 || $value > 1) {
                return $this->json(['message' => 'consensusThreshold doit être un nombre entre 0 et 1.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->upsert(self::CLE_CONSENSUS_THRESHOLD, (string) (float) $value);
        }

        if (array_key_exists('datasetCandidateTrustThreshold', $payload)) {
            $value = $payload['datasetCandidateTrustThreshold'];

            if (!is_numeric($value) || $value < 0 || $value > 100) {
                return $this->json(['message' => 'datasetCandidateTrustThreshold doit être un entier entre 0 et 100.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->upsert(self::CLE_DATASET_CANDIDATE_TRUST_THRESHOLD, (string) (int) $value);
        }

        $this->em->flush();

        return $this->json($this->currentSettings());
    }

    private function currentSettings(): array
    {
        return [
            'consensusThreshold' => $this->adminSettings->getConsensusThreshold(),
            'datasetCandidateTrustThreshold' => $this->adminSettings->getDatasetCandidateTrustThreshold(),
        ];
    }

    private function upsert(string $cle, string $valeur): void
    {
        $parametre = $this->parametres->findOneByCle($cle);

        if ($parametre === null) {
            $this->em->persist(new ParametreSysteme($cle, $valeur));

            return;
        }

        $parametre->setValeur($valeur);
    }
}
