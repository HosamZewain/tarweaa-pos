<?php

namespace App\Services;

use App\Enums\ChannelPricingRuleType;
use App\Enums\OrderSource;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Exceptions\OrderException;
use App\Models\PosOrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PosOrderTypeService
{
    public function activeQuery(): Builder
    {
        return PosOrderType::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getDefaultActiveType(): ?PosOrderType
    {
        return $this->activeQuery()->first();
    }

    public function resolveForOrderCreation(?int $posOrderTypeId): ?PosOrderType
    {
        if (!$posOrderTypeId) {
            return null;
        }

        $type = PosOrderType::query()
            ->whereKey($posOrderTypeId)
            ->where('is_active', true)
            ->first();

        if (!$type) {
            throw OrderException::invalidPosOrderType();
        }

        return $type;
    }

    public function normalizeForPersistence(array $data): array
    {
        $data['source'] = $data['source'] ?: OrderSource::Pos->value;
        $data['pricing_rule_type'] = $data['pricing_rule_type'] ?: ChannelPricingRuleType::BasePrice->value;
        $data['pricing_rule_value'] = (float) ($data['pricing_rule_value'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_default'] = (bool) ($data['is_default'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($data['pricing_rule_type'] === ChannelPricingRuleType::BasePrice->value) {
            $data['pricing_rule_value'] = 0;
        }

        if ($data['is_default']) {
            $data['is_active'] = true;
        }

        return $data;
    }

    public function syncDefaultState(PosOrderType $record): void
    {
        DB::transaction(function () use ($record): void {
            if ($record->is_default) {
                PosOrderType::query()
                    ->whereKeyNot($record->id)
                    ->update(['is_default' => false]);
            }

            $this->ensureDefaultExists();
        });
    }

    public function ensureDefaultExists(): void
    {
        $hasDefault = PosOrderType::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();

        if ($hasDefault) {
            return;
        }

        $fallback = PosOrderType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($fallback) {
            PosOrderType::query()
                ->whereKey($fallback->id)
                ->update(['is_default' => true]);
        }
    }

    public function orderTypeLabel(PosOrderType $type): string
    {
        return $type->name;
    }

    public function fallbackLabelFromEnum(OrderType $type): string
    {
        return $type->label();
    }

    public function contextualPaymentMethod(?PosOrderType $type): ?string
    {
        if (!$type) {
            return null;
        }

        return $this->contextualPaymentMethodFromValues($type->source, $type->name);
    }

    public function contextualPaymentMethodFromValues(?string $source, ?string $name): ?string
    {
        $source = $this->normalizeContextValue($source);
        $name = $this->normalizeContextValue($name);

        if ($this->containsAny($source, ['talabat', 'طلبات']) || $this->containsAny($name, ['talabat', 'طلبات'])) {
            return PaymentMethod::TalabatPay->value;
        }

        if ($this->containsAny($source, ['jahez', 'جاهز']) || $this->containsAny($name, ['jahez', 'جاهز'])) {
            return PaymentMethod::JahezPay->value;
        }

        if (
            $this->containsAny($source, ['hungerstation', 'hunger station', 'hunger', 'هنقر', 'other', 'online', 'اونلاين', 'أونلاين'])
            || $this->containsAny($name, ['hunger', 'هنقر', 'online', 'اونلاين', 'أونلاين'])
        ) {
            return PaymentMethod::Online->value;
        }

        return null;
    }

    private function normalizeContextValue(?string $value): string
    {
        return str((string) ($value ?? ''))
            ->lower()
            ->squish()
            ->value();
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        if ($haystack === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
