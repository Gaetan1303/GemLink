<?php



namespace App\Exception;

use RuntimeException;

/**
 * US 4.1 : action tentée par un utilisateur qui n'est pas propriétaire
 * de la Vitrine (update, delete, items, publish...).
 */
final class VitrineAccessDeniedException extends RuntimeException
{
}