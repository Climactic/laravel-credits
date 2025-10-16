<?php

namespace Climactic\Credits\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

class CreditsAdded implements ShouldDispatchAfterCommit
{
    /**
     * Create a CreditsAdded event payload.
     *
     * @param  Model  $creditable  The model that received credits.
     * @param  int  $transactionId  Identifier of the credit transaction.
     * @param  float  $amount  Amount of credits added.
     * @param  float  $newBalance  Resulting balance after the addition.
     * @param  string|null  $description  Optional description of the transaction.
     * @param  array  $metadata  Additional contextual data associated with the event.
     */
    public function __construct(
        public Model $creditable,
        public int $transactionId,
        public float $amount,
        public float $newBalance,
        public ?string $description,
        public array $metadata
    ) {}
}
