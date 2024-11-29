<?php

namespace Climactic\Credits\Events;

class CreditsDeducted
{
    public function __construct(
        public $creditable,
        public float $amount,
        public float $newBalance,
        public ?string $description,
        public array $metadata
    ) {}
}
