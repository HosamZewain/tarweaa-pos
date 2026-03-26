<?php

namespace App\DTOs;

readonly class CashMovementData
{
    public function __construct(
        public float   $amount,
        public string  $notes,
        public int     $performedBy,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount:      (float)  $data['amount'],
            notes:       (string) $data['notes'],
            performedBy: (int)    $data['performed_by'],
        );
    }
}
