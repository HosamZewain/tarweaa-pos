<?php

namespace App\Services;

use App\Enums\PaymentTerminalFeeType;
use App\Exceptions\OrderException;
use App\Models\PaymentTerminal;

class PaymentTerminalFeeService
{
    public function getActiveTerminalOrFail(?int $terminalId): PaymentTerminal
    {
        $terminal = $terminalId
            ? PaymentTerminal::query()->find($terminalId)
            : null;

        if (!$terminal || !$terminal->is_active) {
            throw OrderException::invalidPaymentTerminal();
        }

        return $terminal;
    }

    public function calculate(PaymentTerminal $terminal, float $grossAmount): array
    {
        $grossAmount = round($grossAmount, 2);

        $feeAmount = match ($terminal->fee_type) {
            PaymentTerminalFeeType::Percentage => $grossAmount * ((float) $terminal->fee_percentage / 100),
            PaymentTerminalFeeType::Fixed => (float) $terminal->fee_fixed_amount,
            PaymentTerminalFeeType::PercentagePlusFixed => ($grossAmount * ((float) $terminal->fee_percentage / 100)) + (float) $terminal->fee_fixed_amount,
        };

        $feeAmount = round(max(0, $feeAmount), 2);

        return [
            'fee_amount' => $feeAmount,
            'net_settlement_amount' => round(max(0, $grossAmount - $feeAmount), 2),
        ];
    }
}
