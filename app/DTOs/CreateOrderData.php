<?php

namespace App\DTOs;

use App\Enums\OrderSource;
use App\Enums\OrderType;
use Illuminate\Support\Carbon;

readonly class CreateOrderData
{
    public function __construct(
        public OrderType    $type,
        public OrderSource  $source          = OrderSource::Pos,
        public ?int         $customerId      = null,
        public ?string      $customerName    = null,
        public ?string      $customerPhone   = null,
        public ?string      $deliveryAddress = null,
        public float        $deliveryFee     = 0.00,
        public ?string      $discountType    = null,    // 'fixed' | 'percentage' | null
        public float        $discountValue   = 0.00,
        public float        $taxRate         = 0.00,
        public ?string      $notes           = null,
        public ?Carbon      $scheduledAt     = null,
        public ?string      $externalOrderId     = null,
        public ?string      $externalOrderNumber = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type:                OrderType::from($data['type']),
            source:              OrderSource::from($data['source'] ?? 'pos'),
            customerId:          isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            customerName:        $data['customer_name'] ?? null,
            customerPhone:       $data['customer_phone'] ?? null,
            deliveryAddress:     $data['delivery_address'] ?? null,
            deliveryFee:         (float) ($data['delivery_fee'] ?? 0),
            discountType:        $data['discount_type'] ?? null,
            discountValue:       (float) ($data['discount_value'] ?? 0),
            taxRate:             (float) ($data['tax_rate'] ?? 0),
            notes:               $data['notes'] ?? null,
            scheduledAt:         isset($data['scheduled_at'])
                                    ? Carbon::parse($data['scheduled_at'])
                                    : null,
            externalOrderId:     $data['external_order_id'] ?? null,
            externalOrderNumber: $data['external_order_number'] ?? null,
        );
    }
}
