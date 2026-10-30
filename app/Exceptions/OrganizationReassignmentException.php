<?php

namespace App\Exceptions;

use LogicException;

class OrganizationReassignmentException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self("The organization_id of [{$model}] cannot be changed once set.");
    }
}
