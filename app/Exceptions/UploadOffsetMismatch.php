<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The client sent a chunk for an offset the server does not expect (lost
 * response, duplicate retry). The client resumes from receivedBytes.
 */
class UploadOffsetMismatch extends RuntimeException
{
    public function __construct(public readonly int $receivedBytes)
    {
        parent::__construct('Unexpected upload offset.');
    }
}
