<?php



namespace App\Exception;

use RuntimeException;

/**
 * US 4.1 CA-4 : tentative de publication d'une Vitrine sans aucun item.
 */
final class VitrineEmptyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Impossible de publier une Vitrine sans aucun item. Ajoutez au moins un post avant de publier.');
    }
}