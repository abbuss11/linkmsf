<?php

namespace App\Exceptions;

use LogicException;

class MissingOrganizationContextException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self("Cannot create [{$model}] without an organization_id or an active organization context.");
    }
}
