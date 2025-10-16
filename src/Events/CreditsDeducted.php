<?php

namespace Climactic\Credits\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

class CreditsDeducted implements ShouldDispatchAfterCommit
{
    /**
     * Create a new event representing credits deducted from a creditable model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $creditable  The model from which credits were deducted.
     * @param  int  $transactionId  Identifier for the related transaction.
     * @param  float  $amount  The amount deducted.
     * @param  float  $newBalance  The resulting balance after the deduction.
     * @param  string|null  $description  Optional description of the deduction.
     * @param  array  $metadata  Additional metadata related to the deduction.
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
