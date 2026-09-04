<?php



namespace App\Exception;

use RuntimeException;

/**
 * US 2.4 CA-2 : suppression tentée par un utilisateur qui n'est ni l'auteur
 * du commentaire, ni modérateur, ni administrateur.
 */
final class CommentAccessDeniedException extends RuntimeException
{
}
