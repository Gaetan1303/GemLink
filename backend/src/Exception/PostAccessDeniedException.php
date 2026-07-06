<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * US 2.1 CA-4 : suppression tentée par un utilisateur qui n'est ni l'auteur,
 * ni modérateur, ni administrateur.
 */
final class PostAccessDeniedException extends RuntimeException
{
}
