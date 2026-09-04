<?php



namespace App\Exception;

use RuntimeException;

/**
 * US 2.1 CA-4 : post inexistant ou déjà soft-deleted.
 */
final class PostNotFoundException extends RuntimeException
{
}
