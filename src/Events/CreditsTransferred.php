<?php

namespace Climactic\Credits\Events;

class CreditsTransferred
{
    public function __construct(
        public $sender,
        public $recipient,
        public float $amount,
        public float $senderNewBalance,
        public float $recipientNewBalance,
        public ?string $description,
        public array $metadata
    ) {}
}
