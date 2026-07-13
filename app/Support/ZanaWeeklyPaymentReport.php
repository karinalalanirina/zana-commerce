<?php

namespace App\Support;

use App\Models\WaOrder;
use Illuminate\Support\Collection;

class ZanaWeeklyPaymentReport
{
    public static function build(Collection $orders, int $days): array
    {
        $windowOrders = $orders->filter(
            fn (WaOrder $order) => ZanaPaymentVerification::withinWindow($order, $days)
        )->values();

        $methodBreakdown = collect(ZanaManualPayment::METHODS)
            ->map(function (string $method) use ($windowOrders) {
                $count = $windowOrders->filter(
                    fn (WaOrder $order) => (ZanaManualPayment::paymentMeta($order)['payment_method'] ?? '') === $method
                )->count();

                return [
                    'method' => $method,
                    'label' => ZanaManualPayment::methodLabel($method),
                    'count' => $count,
                ];
            })
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();

        $recentActivity = $windowOrders
            ->sortByDesc(fn (WaOrder $order) => optional($order->updated_at)?->timestamp ?? 0)
            ->take(5)
            ->map(function (WaOrder $order) {
                $paymentMeta = ZanaManualPayment::paymentMeta($order);

                return [
                    'order_id' => $order->id,
                    'order_reference' => ZanaPaymentVerification::orderReference($order),
                    'customer_name' => $order->customer_name ?: 'Customer',
                    'payment_state' => ZanaManualPayment::statusLabel(ZanaManualPayment::paymentStatus($order)),
                    'verification_state' => ZanaPaymentVerification::derivedLabel($order),
                    'reference' => $paymentMeta['transaction_reference'] ?? null,
                    'amount_received' => ZanaManualPayment::amountReceivedDisplay($order),
                    'updated_at' => optional($order->updated_at)?->format('M d, H:i'),
                ];
            })
            ->values();

        return [
            'label' => ZanaPaymentVerification::reportWindowLabel($days),
            'awaiting_payment' => $windowOrders->filter(fn (WaOrder $order) => ZanaManualPayment::paymentStatus($order) === 'awaiting_payment')->count(),
            'customer_says_paid' => $windowOrders->filter(fn (WaOrder $order) => ZanaManualPayment::paymentStatus($order) === 'customer_says_paid')->count(),
            'awaiting_verification' => $windowOrders->filter(fn (WaOrder $order) => ZanaPaymentVerification::needsVerification($order))->count(),
            'missing_reference' => $windowOrders->filter(fn (WaOrder $order) => ZanaPaymentVerification::missingReference($order))->count(),
            'paid_confirmed' => $windowOrders->filter(fn (WaOrder $order) => ZanaManualPayment::paymentStatus($order) === 'paid_confirmed')->count(),
            'payment_failed' => $windowOrders->filter(fn (WaOrder $order) => ZanaManualPayment::paymentStatus($order) === 'payment_failed')->count(),
            'refunded' => $windowOrders->filter(fn (WaOrder $order) => ZanaManualPayment::paymentStatus($order) === 'refunded')->count(),
            'confirmed_total' => $windowOrders->sum(fn (WaOrder $order) => ZanaPaymentVerification::confirmedAmount($order)),
            'awaiting_verification_total' => $windowOrders->sum(fn (WaOrder $order) => ZanaPaymentVerification::amountAwaitingVerification($order)),
            'method_breakdown' => $methodBreakdown,
            'recent_activity' => $recentActivity,
        ];
    }
}
