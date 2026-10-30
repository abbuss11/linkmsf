<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule refused the operation. The message is already translated
 * and safe to show to the user.
 */
class ActionFailed extends RuntimeException
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function because(string $translationKey, array $replace = []): self
    {
        return new self(__($translationKey, $replace));
    }
}
