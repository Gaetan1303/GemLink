<?php
namespace App\Exception;

use RuntimeException;

/**
 * US 3.1 CA-2/CA-3 : réponse du service IA absente, malformée ou incomplète.
 * Volontairement une RuntimeException non avalée : elle doit déclencher le
 * retry exponentiel de Symfony Messenger comme toute autre erreur transitoire.
 */
final class AiAnalysisException extends RuntimeException
{
    public static function missingField(string $field): self
    {
        return new self(sprintf('Réponse du service IA incomplète : champ "%s" manquant.', $field));
    }

    public static function invalidEmbedding(int $length): self
    {
        return new self(sprintf('Embedding invalide : attendu 512 dimensions, reçu %d.', $length));
    }

    public static function invalidHttpStatus(int $statusCode): self
    {
        return new self(sprintf('Réponse du service IA invalide (HTTP %d).', $statusCode));
    }

    public static function unreachableMedia(string $mediaUrl, int $statusCode): self
    {
        return new self(sprintf('Média introuvable sur le CDN (%s, HTTP %d).', $mediaUrl, $statusCode));
    }
}