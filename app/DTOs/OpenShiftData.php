<?php

namespace App\DTOs;

readonly class OpenShiftData
{
    public function __construct(
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            notes: $data['notes'] ?? null,
        );
    }
}
