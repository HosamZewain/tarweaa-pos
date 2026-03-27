<?php

namespace App\Services;

use App\DTOs\ProcessPaymentData;
use App\Enums\CashMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPaymentService
{
    public function __construct(
        private readonly RecipeInventoryService $recipeInventoryService,
        private readonly PaymentTerminalFeeService $paymentTerminalFeeService,
    ) {}

    /**
     * Process one or more payments against an order.
     *
     * @param ProcessPaymentData[] $payments
     * @throws OrderException
     */
    public function processPayment(Order $order, array $payments, int $actorId): Order
    {
        if ($order->activeItems()->doesntExist()) {
            throw OrderException::emptyOrder();
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            throw OrderException::orderAlreadyPaid();
        }

        $totalProvided = collect($payments)->sum(fn (ProcessPaymentData $p) => $p->amount);
        $amountDue     = $order->remainingAmount();

        if ($totalProvided < $amountDue) {
            throw OrderException::insufficientPayment($amountDue, $totalProvided);
        }

        return DB::transaction(function () use ($order, $payments, $totalProvided, $actorId): Order {
            foreach ($payments as $paymentData) {
                $terminal = null;
                $feeData = [
                    'fee_amount' => 0.0,
                    'net_settlement_amount' => round($paymentData->amount, 2),
                ];

                if ($paymentData->method === PaymentMethod::Card) {
                    if (blank($paymentData->referenceNumber)) {
                        throw OrderException::paymentReferenceRequired();
                    }

                    $terminal = $this->paymentTerminalFeeService->getActiveTerminalOrFail($paymentData->terminalId);
                    $feeData = $this->paymentTerminalFeeService->calculate($terminal, $paymentData->amount);
                }

                // Create payment record
                $order->payments()->create([
                    'payment_method'   => $paymentData->method,
                    'amount'           => $paymentData->amount,
                    'terminal_id'      => $terminal?->id,
                    'reference_number' => $paymentData->referenceNumber,
                    'fee_amount'       => $feeData['fee_amount'],
                    'net_settlement_amount' => $feeData['net_settlement_amount'],
                    'created_by'       => $actorId,
                ]);

                $order->increment('paid_amount', $paymentData->amount);

                if ($order->isFullyPaid()) {
                    $order->update(['payment_status' => PaymentStatus::Paid]);
                } elseif ((float) $order->paid_amount > 0) {
                    $order->update(['payment_status' => PaymentStatus::Partial]);
                }

                // Record cash movement for cash payments
                if ($paymentData->method === PaymentMethod::Cash) {
                    $order->drawerSession->addMovement(
                        type:          CashMovementType::Sale,
                        amount:        $paymentData->amount,
                        performedBy:   $actorId,
                        referenceType: 'order',
                        referenceId:   $order->id,
                    );
                }
            }

            // Store change given back to customer
            $change = max(0, $totalProvided - (float) $order->total);
            if ($change > 0) {
                $order->update(['change_amount' => $change]);
            }

            // Transition to Confirmed once payment is fully received
            if ($order->isFullyPaid()) {
                $this->recipeInventoryService->deductPendingForOrder($order->fresh(['items.menuItem.recipeLines.inventoryItem']), $actorId);
                $order->transitionTo(OrderStatus::Confirmed, $actorId);
            }

            // Update customer lifetime metrics
            if ($order->customer_id && $order->isFullyPaid()) {
                $order->customer->recordOrder((float) $order->total);
            }

            Log::info('Order payment processed', [
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
                'total'          => $order->total,
                'paid'           => $order->paid_amount,
                'change'         => $order->change_amount,
                'fees_total'     => round($order->payments()->sum('fee_amount'), 2),
                'payment_status' => $order->payment_status->value,
                'processed_by'   => $actorId,
            ]);

            return $order->fresh(['payments', 'items.modifiers']);
        });
    }

    /**
     * Issue a full or partial refund on a delivered order.
     *
     * @throws OrderException
     */
    public function refund(Order $order, User $by, float $refundAmount, string $reason): Order
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            throw OrderException::cannotRefundUnpaid();
        }

        if ($order->payment_status === PaymentStatus::Refunded) {
            throw OrderException::alreadyRefunded();
        }

        if ($refundAmount > (float) $order->total) {
            throw OrderException::refundExceedsTotal((float) $order->total, $refundAmount);
        }

        return DB::transaction(function () use ($order, $by, $refundAmount, $reason): Order {
            // Record refund cash movement if original was cash
            $cashPaid = (float) $order->payments()
                                      ->where('payment_method', PaymentMethod::Cash->value)
                                      ->sum('amount');

            $cashRefund = min($refundAmount, $cashPaid);

            if ($cashRefund > 0) {
                $order->drawerSession->addMovement(
                    type:          CashMovementType::Refund,
                    amount:        $cashRefund,
                    performedBy:   $by->id,
                    referenceType: 'order',
                    referenceId:   $order->id,
                    notes:         "استرجاع طلب رقم {$order->order_number}",
                );
            }

            $order->update([
                'status'         => OrderStatus::Refunded,
                'payment_status' => PaymentStatus::Refunded,
                'refund_amount'  => $refundAmount,
                'refund_reason'  => $reason,
                'refunded_by'    => $by->id,
                'refunded_at'    => now(),
                'updated_by'     => $by->id,
            ]);

            Log::info('Order refunded', [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'refund_amount' => $refundAmount,
                'refunded_by'   => $by->id,
            ]);

            return $order->fresh();
        });
    }
}
