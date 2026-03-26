<?php

namespace App\DTOs;

readonly class CloseShiftData
{
    public function __construct(
        public float   $actualCash,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            actualCash: (float) ($data['actual_cash'] ?? 0),
            notes:      $data['notes'] ?? null,
        );
    }
}
