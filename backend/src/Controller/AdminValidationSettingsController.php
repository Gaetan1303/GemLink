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
    private const CLE_IDENTIFICATION_CONFIDENCE_THRESHOLD = 'identification.confidence_threshold';
    private const POINT_ACTIONS = [
        'postCreated' => 'POST_CREATED',
        'likeReceived' => 'LIKE_RECEIVED',
        'validationSubmitted' => 'VALIDATION_SUBMITTED',
        'validationConsensusConfirmed' => 'VALIDATION_CONSENSUS_CONFIRMED',
    ];

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

        if (array_key_exists('identificationConfidenceThreshold', $payload)) {
            $value = $payload['identificationConfidenceThreshold'];
            if (!is_numeric($value) || $value < 0 || $value > 1) {
                return $this->json(['message' => 'identificationConfidenceThreshold doit être un nombre entre 0 et 1.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->upsert(self::CLE_IDENTIFICATION_CONFIDENCE_THRESHOLD, (string) (float) $value);
        }

        if (array_key_exists('points', $payload)) {
            if (!is_array($payload['points'])) {
                return $this->json(['message' => 'points doit être un objet.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            foreach ($payload['points'] as $name => $value) {
                if (!isset(self::POINT_ACTIONS[$name]) || !is_int($value) || $value < 0 || $value > 10000) {
                    return $this->json(['message' => 'Le barème de points est invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $this->upsert('points.' . strtolower(self::POINT_ACTIONS[$name]), (string) $value);
            }
        }

        $this->em->flush();

        return $this->json($this->currentSettings());
    }

    private function currentSettings(): array
    {
        return [
            'consensusThreshold' => $this->adminSettings->getConsensusThreshold(),
            'datasetCandidateTrustThreshold' => $this->adminSettings->getDatasetCandidateTrustThreshold(),
            'identificationConfidenceThreshold' => $this->adminSettings->getIdentificationConfidenceThreshold(),
            'points' => array_map(fn (string $action) => $this->adminSettings->getPointsForAction($action), self::POINT_ACTIONS),
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
