<?php



namespace App\Exception;

use InvalidArgumentException;

/**
 * US 4.1 CA-1/CA-2/CA-3 : titre/description/items/ordre fournis mais invalides.
 */
final class VitrineValidationException extends InvalidArgumentException
{
}