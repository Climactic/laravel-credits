<?php

namespace Climactic\Credits\Exceptions;

use Exception;

class InsufficientCreditsException extends Exception
{
    public function __construct(float $requested, float $available)
    {
        parent::__construct(
            sprintf(
                'Insufficient credits. Requested: %s, Available: %s',
                $requested,
                $available
            )
        );
    }
} 
