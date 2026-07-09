<?php

declare(strict_types=1);

namespace App\Exception;

use InvalidArgumentException;

/**
 * US 2.1 CA-1 : titre/description/tags fournis mais invalides (trop longs, etc.).
 */
final class PostValidationException extends InvalidArgumentException
{
}
