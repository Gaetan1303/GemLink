<?php



namespace App\Exception;

use InvalidArgumentException;

/**
 * US 2.4 CA-1 : contenu de commentaire manquant ou trop long (> 1000 caractères).
 */
final class CommentValidationException extends InvalidArgumentException
{
}
