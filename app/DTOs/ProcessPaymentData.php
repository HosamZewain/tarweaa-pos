<?php

namespace App\DTOs;

use App\Enums\PaymentMethod;

readonly class ProcessPaymentData
{
    public function __construct(
        public PaymentMethod $method,
        public float         $amount,
        public ?string       $referenceNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            method:          PaymentMethod::from($data['method']),
            amount:          (float) $data['amount'],
            referenceNumber: $data['reference_number'] ?? null,
        );
    }

    /**
     * Build a collection of payment DTOs from a payments array.
     * Useful for split payments.
     *
     * @return ProcessPaymentData[]
     */
    public static function collectionFromArray(array $payments): array
    {
        return array_map(
            fn (array $p) => self::fromArray($p),
            $payments
        );
    }
}
