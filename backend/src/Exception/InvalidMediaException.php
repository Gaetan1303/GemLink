<?php



namespace App\Exception;

use InvalidArgumentException;

/**
 * US 2.1 CA-1 / CA-2 : levée quand le fichier média est absent, illisible,
 * d'un type MIME non autorisé, ou dépasse les limites de taille/durée.
 */
final class InvalidMediaException extends InvalidArgumentException
{
}
