<?php

namespace Climactic\Credits\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class CreditsTransferred implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $transactionId,
        public $sender,
        public $recipient,
        public float $amount,
        public float $senderNewBalance,
        public float $recipientNewBalance,
        public ?string $description,
        public array $metadata
    ) {}
}
