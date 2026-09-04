<?php

namespace App\Exception;

use InvalidArgumentException;

/**
 * US 2.7 CA-1 : payload de validation communautaire incohérent
 * (ex : CORRECT sans proposedLabel).
 */
final class ValidationPayloadException extends InvalidArgumentException
{
}
