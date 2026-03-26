<?php

namespace App\DTOs;

readonly class OpenDrawerData
{
    public function __construct(
        public int    $cashierId,
        public int    $shiftId,
        public int    $posDeviceId,
        public float  $openingBalance = 0.00,
        public int    $openedBy,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cashierId:      (int)   $data['cashier_id'],
            shiftId:        (int)   $data['shift_id'],
            posDeviceId:    (int)   $data['pos_device_id'],
            openingBalance: (float) ($data['opening_balance'] ?? 0.00),
            openedBy:       (int)   $data['opened_by'],
        );
    }
}
