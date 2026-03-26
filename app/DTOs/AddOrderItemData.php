<?php

namespace App\DTOs;

readonly class AddOrderItemData
{
    /**
     * @param int                $menuItemId   Menu item ID
     * @param int                $quantity     Must be >= 1
     * @param int|null           $variantId    For variable items (size, etc.)
     * @param array<int, int>    $modifiers    [modifier_id => quantity]
     * @param float              $discountAmount  Item-level fixed discount
     * @param string|null        $notes        e.g. "بدون بصل"
     */
    public function __construct(
        public int     $menuItemId,
        public int     $quantity       = 1,
        public ?int    $variantId      = null,
        public array   $modifiers      = [],
        public float   $discountAmount = 0.00,
        public ?string $notes          = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            menuItemId:     (int)   $data['menu_item_id'],
            quantity:       (int)   ($data['quantity'] ?? 1),
            variantId:      isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            modifiers:      $data['modifiers'] ?? [],
            discountAmount: (float) ($data['discount_amount'] ?? 0),
            notes:          $data['notes'] ?? null,
        );
    }
}
