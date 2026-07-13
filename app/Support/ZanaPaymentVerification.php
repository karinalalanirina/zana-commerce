<?php

namespace App\Support;

use App\Models\WaOrder;
use Illuminate\Support\Carbon;

class ZanaPaymentVerification
{
    public const FILTER_ALL = 'all';
    public const FILTER_AWAITING_VERIFICATION = 'awaiting_verification';
    public const FILTER_MISSING_REFERENCE = 'missing_reference';
    public const FILTER_REFERENCE_RECORDED = 'reference_recorded';
    public const FILTER_PAID_CONFIRMED = 'paid_confirmed';
    public const FILTER_PAYMENT_FAILED = 'payment_failed';
    public const FILTER_REFUNDED = 'refunded';

    public static function filterOptions(): array
    {
        return [
            self::FILTER_ALL => 'All verification states',
            self::FILTER_AWAITING_VERIFICATION => 'Awaiting Verification',
            self::FILTER_MISSING_REFERENCE => 'Missing Reference',
            self::FILTER_REFERENCE_RECORDED => 'Reference Recorded',
            self::FILTER_PAID_CONFIRMED => 'Paid Confirmed',
            self::FILTER_PAYMENT_FAILED => 'Payment Failed',
            self::FILTER_REFUNDED => 'Refunded',
        ];
    }

    public static function paymentMeta(?WaOrder $order): array
    {
        return ZanaManualPayment::paymentMeta($order);
    }

    public static function derivedState(?WaOrder $order): string
    {
        $paymentStatus = ZanaManualPayment::paymentStatus($order);

        return match ($paymentStatus) {
            'customer_says_paid' => self::FILTER_AWAITING_VERIFICATION,
            'paid_confirmed' => self::FILTER_PAID_CONFIRMED,
            'payment_failed' => self::FILTER_PAYMENT_FAILED,
            'refunded' => self::FILTER_REFUNDED,
            default => self::FILTER_ALL,
        };
    }

    public static function derivedLabel(?WaOrder $order): string
    {
        return match (self::derivedState($order)) {
            self::FILTER_AWAITING_VERIFICATION => 'Awaiting Verification',
            self::FILTER_MISSING_REFERENCE => 'Missing Reference',
            self::FILTER_REFERENCE_RECORDED => 'Reference Recorded',
            self::FILTER_PAID_CONFIRMED => 'Paid Confirmed',
            self::FILTER_PAYMENT_FAILED => 'Payment Failed',
            self::FILTER_REFUNDED => 'Refunded',
            default => 'Awaiting Payment',
        };
    }

    public static function hasReference(?WaOrder $order): bool
    {
        return self::reference($order) !== '';
    }

    public static function reference(?WaOrder $order): string
    {
        return trim((string) (self::paymentMeta($order)['transaction_reference'] ?? ''));
    }

    public static function payerNote(?WaOrder $order): string
    {
        return trim((string) (self::paymentMeta($order)['payer_note'] ?? ''));
    }

    public static function customerPhone(?WaOrder $order): string
    {
        return preg_replace('/\s+/', '', trim((string) ($order?->customer_phone ?? '')));
    }

    public static function orderReference(?WaOrder $order): string
    {
        return 'ORDER-' . (int) ($order?->id ?? 0);
    }

    public static function amountReceived(?WaOrder $order): ?string
    {
        $value = trim((string) (self::paymentMeta($order)['amount_received'] ?? ''));

        return $value !== '' ? $value : null;
    }

    public static function needsVerification(?WaOrder $order): bool
    {
        return ZanaManualPayment::paymentStatus($order) === 'customer_says_paid';
    }

    public static function missingReference(?WaOrder $order): bool
    {
        return self::needsVerification($order) && !self::hasReference($order);
    }

    public static function referenceRecorded(?WaOrder $order): bool
    {
        return self::needsVerification($order) && self::hasReference($order);
    }

    public static function matchesFilter(?WaOrder $order, string $filter): bool
    {
        return match ($filter) {
            self::FILTER_ALL => true,
            self::FILTER_AWAITING_VERIFICATION => self::needsVerification($order),
            self::FILTER_MISSING_REFERENCE => self::missingReference($order),
            self::FILTER_REFERENCE_RECORDED => self::referenceRecorded($order),
            self::FILTER_PAID_CONFIRMED => ZanaManualPayment::paymentStatus($order) === 'paid_confirmed',
            self::FILTER_PAYMENT_FAILED => ZanaManualPayment::paymentStatus($order) === 'payment_failed',
            self::FILTER_REFUNDED => ZanaManualPayment::paymentStatus($order) === 'refunded',
            default => true,
        };
    }

    public static function confirmedAmount(?WaOrder $order): float
    {
        if (ZanaManualPayment::paymentStatus($order) !== 'paid_confirmed') {
            return 0.0;
        }

        $amount = self::amountReceived($order);
        if ($amount !== null && is_numeric($amount)) {
            return (float) $amount;
        }

        return ((int) ($order?->total_minor ?? 0)) / 100;
    }

    public static function amountDue(?WaOrder $order): float
    {
        return ((int) ($order?->total_minor ?? 0)) / 100;
    }

    public static function amountAwaitingVerification(?WaOrder $order): float
    {
        if (!self::needsVerification($order)) {
            return 0.0;
        }

        $amount = self::amountReceived($order);
        if ($amount !== null && is_numeric($amount)) {
            return (float) $amount;
        }

        return self::amountDue($order);
    }

    public static function matchesSearch(?WaOrder $order, string $search): bool
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return true;
        }

        $normalizedNeedle = preg_replace('/[^0-9a-z]/i', '', $needle);
        $haystacks = [
            mb_strtolower((string) ($order?->customer_name ?? '')),
            mb_strtolower(self::customerPhone($order)),
            mb_strtolower(self::reference($order)),
            mb_strtolower(self::payerNote($order)),
            mb_strtolower(self::orderReference($order)),
            mb_strtolower((string) ($order?->id ?? '')),
            mb_strtolower(number_format(self::amountDue($order), 2, '.', '')),
            mb_strtolower((string) (self::amountReceived($order) ?? '')),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                return true;
            }

            $normalizedHaystack = preg_replace('/[^0-9a-z]/i', '', $haystack);
            if ($normalizedNeedle !== '' && $normalizedHaystack !== '' && str_contains($normalizedHaystack, $normalizedNeedle)) {
                return true;
            }
        }

        return false;
    }

    public static function reportWindowLabel(int $days): string
    {
        return $days === 7 ? 'Last 7 days' : "Last {$days} days";
    }

    public static function withinWindow(?WaOrder $order, int $days): bool
    {
        if (!$order?->updated_at) {
            return false;
        }

        return $order->updated_at->greaterThanOrEqualTo(Carbon::now()->subDays($days));
    }
}
