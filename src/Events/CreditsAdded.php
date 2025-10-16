<?php

namespace Climactic\Credits\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CreditsAdded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public $creditable,
        public int $transactionId,
        public float $amount,
        public float $newBalance,
        public ?string $description,
        public array $metadata
    ) {}
}
