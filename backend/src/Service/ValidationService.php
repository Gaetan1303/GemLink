<?php

namespace App\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Validation;
use App\Exception\ValidationPayloadException;
use App\Message\RecalculateConsensusMessage;
use App\Message\AwardPointsMessage;
use App\Repository\PublicationPierreRepository;
use App\Repository\ValidationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US 2.7 : soumission d'une validation communautaire d'identification IA.
 *
 * Upsert sur (publication, user) — contrainte uq_validation_pub_user :
 * une resoumission met à jour la ligne existante (action, proposedLabel,
 * trust_score_snapshot repris à cet instant) plutôt que d'en créer une
 * nouvelle. L'INSERT/UPDATE Doctrine déclenche le trigger PostgreSQL
 * update_trust_score_after_validation, qui recalcule automatiquement le
 * Trust Score de l'auteur du post — aucune action PHP supplémentaire
 * requise pour ça.
 */
class ValidationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidationRepository $validations,
        private readonly PublicationPierreRepository $publicationPierres,
        private readonly AdminSettingsProvider $adminSettings,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function submitValidation(
        Publication $publication,
        User $validator,
        string $action,
        ?string $proposedLabel = null,
    ): Validation {
        $this->assertCoherentPayload($action, $proposedLabel);

        // La pierre validée est toujours le label IA actuellement affiché
        // (confidence max), pas une valeur fournie par le client — évite
        // qu'un post analysé entre-temps par une nouvelle version du
        // modèle IA laisse une validation pointer sur un label obsolète.
        $currentMatch = $this->publicationPierres->findBestMatch($publication);

        if ($currentMatch === null) {
            throw new ValidationPayloadException("Ce post n'a pas encore de label IA à valider.");
        }

        $trustScoreSnapshot = $validator->getTrustScore();

        $validation = $this->validations->findOneByPublicationAndUser($publication, $validator);
        $isNew = $validation === null;

        if ($isNew) {
            $validation = new Validation($validator, $publication, $currentMatch->getPierre(), $trustScoreSnapshot);
        } else {
            $validation->setPierre($currentMatch->getPierre());
            $validation->setTrustScoreSnapshot($trustScoreSnapshot);
        }

        $validation->setAction($action);
        $validation->setProposedLabel($proposedLabel);

        if ($isNew) {
            $this->em->persist($validation);
        }

        $this->em->flush();

        // Recalcul en tâche de fond, cohérent avec le pipeline IA existant
        // (AnalyzeMediaMessage) : ne bloque jamais la réponse HTTP.
        $this->messageBus->dispatch(new RecalculateConsensusMessage((string) $publication->getId()));
        if ($isNew) {
            $this->messageBus->dispatch(new AwardPointsMessage(
                $validator->getId()->toRfc4122(),
                PointsService::ACTION_VALIDATION_SUBMITTED,
                $validation->getId()->toRfc4122(),
            ));
        }

        return $validation;
    }

    private function assertCoherentPayload(string $action, ?string $proposedLabel): void
    {
        if (!in_array($action, Validation::ACTIONS, true)) {
            throw new ValidationPayloadException('Action de validation invalide.');
        }

        if ($action === Validation::ACTION_CORRECT && ($proposedLabel === null || trim($proposedLabel) === '')) {
            throw new ValidationPayloadException('Un label alternatif est requis pour une correction.');
        }

        if ($action !== Validation::ACTION_CORRECT && $proposedLabel !== null) {
            throw new ValidationPayloadException('Aucun label alternatif ne doit être fourni pour ce type de validation.');
        }
    }
}
