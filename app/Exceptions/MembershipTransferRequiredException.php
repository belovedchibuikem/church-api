<?php

namespace App\Exceptions;

use RuntimeException;

class MembershipTransferRequiredException extends RuntimeException
{
    /**
     * @param  array{kind: string, from_id: string, from_name: string, to_id: string, to_name: string}  $details
     */
    public function __construct(
        public readonly array $details,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function conventional(string $fromId, string $fromName, string $toId, string $toName): self
    {
        return new self(
            [
                'kind' => 'conventional',
                'from_id' => $fromId,
                'from_name' => $fromName,
                'to_id' => $toId,
                'to_name' => $toName,
            ],
            "You already belong to {$fromName}. Confirm to move your church membership to {$toName}. Your records will move with you. Home church membership is kept separately.",
        );
    }

    public static function homeChurch(string $fromId, string $fromName, string $toId, string $toName): self
    {
        return new self(
            [
                'kind' => 'home_church',
                'from_id' => $fromId,
                'from_name' => $fromName,
                'to_id' => $toId,
                'to_name' => $toName,
            ],
            "You already belong to {$fromName}. Confirm to move your home church membership to {$toName}. Your conventional church membership is kept.",
        );
    }
}
