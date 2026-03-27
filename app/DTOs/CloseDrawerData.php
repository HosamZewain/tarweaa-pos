<?php

namespace App\DTOs;

readonly class CloseDrawerData
{
    public function __construct(
        public float   $actualCash,
        public int     $closedBy,
        public ?string $previewToken = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            actualCash: (float) ($data['actual_cash'] ?? 0),
            closedBy:   (int)   $data['closed_by'],
            previewToken: $data['preview_token'] ?? null,
            notes:      $data['notes'] ?? null,
        );
    }
}
