<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;
use App\Models\WaOrder;
use Illuminate\Support\Carbon;

class ZanaManualPayment
{
    public const PAYMENT_KEY = 'zana_manual_payment';
    public const EVENTS_KEY = 'zana_payment_events';

    public const STATUSES = [
        'awaiting_payment',
        'payment_link_sent',
        'payment_reminder_sent',
        'customer_says_paid',
        'paid_confirmed',
        'payment_failed',
        'refunded',
    ];

    public const METHODS = [
        'mpesa_till',
        'mpesa_paybill',
        'daraja_stk',
        'bank_transfer',
        'payment_link',
        'cash',
        'other',
    ];

    public static function paymentMeta(?WaOrder $order): array
    {
        $meta = is_array($order?->meta_json) ? $order->meta_json : [];
        $payment = $meta[self::PAYMENT_KEY] ?? [];

        return is_array($payment) ? $payment : [];
    }

    public static function paymentStatus(?WaOrder $order): string
    {
        $payment = self::paymentMeta($order);
        $status = trim((string) ($payment['status'] ?? ''));
        if ($status !== '') {
            return $status;
        }

        $legacy = trim((string) ((is_array($order?->meta_json) ? ($order->meta_json['zana_payment_step'] ?? '') : '')));
        if ($legacy !== '') {
            return $legacy;
        }

        return match ((string) ($order?->status ?? '')) {
            'paid' => 'paid_confirmed',
            'confirmed' => 'customer_says_paid',
            'cancelled' => 'payment_failed',
            default => 'awaiting_payment',
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            'awaiting_payment' => 'Awaiting Payment',
            'payment_link_sent' => 'Payment Link Sent',
            'payment_reminder_sent' => 'Payment Reminder Sent',
            'customer_says_paid' => 'Customer Says Paid',
            'paid_confirmed' => 'Paid Confirmed',
            'payment_failed' => 'Payment Failed',
            'refunded' => 'Refunded',
            default => 'Awaiting Payment',
        };
    }

    public static function methodLabel(?string $method): string
    {
        return match ((string) $method) {
            'mpesa_till' => 'M-Pesa Till',
            'mpesa_paybill' => 'M-Pesa Paybill',
            'daraja_stk' => 'Daraja STK',
            'bank_transfer' => 'Bank Transfer',
            'payment_link' => 'Payment Link',
            'cash' => 'Cash',
            'other' => 'Other',
            default => 'Not set',
        };
    }

    public static function amountReceivedDisplay(?WaOrder $order): ?string
    {
        $payment = self::paymentMeta($order);
        $value = trim((string) ($payment['amount_received'] ?? ''));
        if ($value === '') {
            return null;
        }

        $currency = (string) ($payment['amount_received_currency'] ?? $order?->currency_code ?? 'KES');
        if (is_numeric($value)) {
            return \App\Support\FormatSettings::formatIn((float) $value, $currency);
        }

        return $currency . ' ' . $value;
    }

    public static function darajaMeta(?WaOrder $order): array
    {
        $payment = self::paymentMeta($order);
        $daraja = $payment['daraja'] ?? [];

        return is_array($daraja) ? $daraja : [];
    }

    public static function paystackMeta(?WaOrder $order): array
    {
        $payment = self::paymentMeta($order);
        $paystack = $payment['paystack'] ?? [];

        return is_array($paystack) ? $paystack : [];
    }

    public static function timeline(?WaOrder $order): array
    {
        $meta = is_array($order?->meta_json) ? $order->meta_json : [];
        $events = $meta[self::EVENTS_KEY] ?? [];
        if (!is_array($events)) {
            return [];
        }

        $cache = [];
        $events = array_map(function ($event) use (&$cache) {
            if (!is_array($event)) {
                return $event;
            }

            if (in_array((string) ($event['type'] ?? ''), [
                'payment_instructions_template_required',
                'payment_reminder_template_required',
            'payment_link_template_required',
            'paystack_link_template_required',
            'paystack_callback_unmatched',
        ], true)) {
                $event['message_delivery_state'] = 'template_required_not_configured';
                $event['message_delivery_label'] = self::deliveryStateLabel('template_required_not_configured');

                return $event;
            }

            if (in_array((string) ($event['type'] ?? ''), [
                'payment_instructions_copied',
                'payment_reminder_copied',
                'payment_link_copied',
                'payment_mpesa_instructions_copied',
                'paystack_link_copied',
            ], true)) {
                $event['message_delivery_state'] = 'copied_instead';
                $event['message_delivery_label'] = self::deliveryStateLabel('copied_instead');

                return $event;
            }

            $messageId = (int) ($event['message_id'] ?? 0);
            if ($messageId > 0) {
                if (!array_key_exists($messageId, $cache)) {
                    $cache[$messageId] = Message::query()->find($messageId);
                }
                $msg = $cache[$messageId];
                if ($msg) {
                    $state = self::messageStateFromMessage($msg);
                    $event['message_delivery_state'] = $state;
                    $event['message_delivery_label'] = self::deliveryStateLabel($state);
                    $event['message_sent_at'] = optional($msg->sent_at)?->toIso8601String();
                    $event['message_delivered_at'] = optional($msg->delivered_at)?->toIso8601String();
                    $event['message_read_at'] = optional($msg->read_at)?->toIso8601String();
                    if (($event['wa_message_id'] ?? '') === '' && !empty($msg->meta['wa_message_id'])) {
                        $event['wa_message_id'] = (string) $msg->meta['wa_message_id'];
                    }
                }
            } elseif (($event['send_platform'] ?? '') === 'waba_template' && in_array((string) ($event['type'] ?? ''), [
                'payment_instructions_template_sent',
                'payment_reminder_template_sent',
                'payment_link_template_sent',
                'payment_mpesa_instructions_template_sent',
                'paystack_link_template_sent',
            ], true)) {
                $event['message_delivery_state'] = 'submitted';
                $event['message_delivery_label'] = self::deliveryStateLabel('submitted');
            }

            return $event;
        }, $events);

        usort($events, static function ($a, $b) {
            $at = strtotime((string) ($a['at'] ?? '')) ?: 0;
            $bt = strtotime((string) ($b['at'] ?? '')) ?: 0;

            return $bt <=> $at;
        });

        return $events;
    }

    public static function mergeIntoOrder(WaOrder $order, array $attributes, ?User $actor = null, ?string $eventType = null, array $eventMeta = []): array
    {
        $meta = is_array($order->meta_json) ? $order->meta_json : [];
        $payment = self::paymentMeta($order);

        foreach ([
            'status',
            'payment_method',
            'transaction_reference',
            'amount_received',
            'amount_received_currency',
            'payer_note',
            'confirmation_note',
            'customer_says_paid',
            'customer_says_paid_at',
            'customer_says_paid_by',
            'confirmed_by',
            'confirmed_at',
            'last_send_channel',
            'last_send_result',
            'last_send_error',
            'last_payment_message',
            'last_payment_message_at',
            'last_payment_message_type',
            'last_payment_link',
            'last_template_fallback_template_id',
            'last_template_fallback_template_name',
            'last_template_fallback_wamid',
        ] as $key) {
            if (array_key_exists($key, $attributes)) {
                $payment[$key] = $attributes[$key];
            }
        }

        if (array_key_exists('daraja', $attributes) && is_array($attributes['daraja'])) {
            $existingDaraja = is_array($payment['daraja'] ?? null) ? $payment['daraja'] : [];
            $payment['daraja'] = array_merge($existingDaraja, $attributes['daraja']);
        }

        if (array_key_exists('paystack', $attributes) && is_array($attributes['paystack'])) {
            $existingPaystack = is_array($payment['paystack'] ?? null) ? $payment['paystack'] : [];
            $payment['paystack'] = array_merge($existingPaystack, $attributes['paystack']);
        }

        $payment['updated_at'] = now()->toIso8601String();
        $meta[self::PAYMENT_KEY] = $payment;
        $meta['zana_payment_step'] = $payment['status'] ?? self::paymentStatus($order);

        $events = $meta[self::EVENTS_KEY] ?? [];
        if (!is_array($events)) {
            $events = [];
        }

        if ($eventType) {
            $events[] = array_filter(array_merge([
                'type' => $eventType,
                'label' => self::eventLabel($eventType),
                'at' => now()->toIso8601String(),
                'actor_user_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'payment_status' => $payment['status'] ?? null,
                'payment_method' => $payment['payment_method'] ?? null,
                'payment_method_label' => self::methodLabel($payment['payment_method'] ?? null),
                'transaction_reference' => $payment['transaction_reference'] ?? null,
                'amount_received' => $payment['amount_received'] ?? null,
                'amount_received_display' => self::amountReceivedDisplay($order->forceFill(['meta_json' => $meta])),
                'note' => $payment['confirmation_note'] ?? null,
            ], $eventMeta), static fn ($value) => $value !== null && $value !== '');
        }

        $meta[self::EVENTS_KEY] = array_slice($events, -50);

        return $meta;
    }

    public static function applyAction(WaOrder $order, string $action, ?User $actor = null): array
    {
        $status = self::paymentStatus($order);

        return match ($action) {
            'send_instructions' => [
                'status' => 'awaiting_payment',
                'event' => 'payment_instructions_prepared',
                'order_status' => 'pending',
            ],
            'send_reminder' => [
                'status' => 'payment_reminder_sent',
                'event' => 'payment_reminder_prepared',
                'order_status' => 'pending',
            ],
            'resend_link' => [
                'status' => 'payment_link_sent',
                'event' => 'payment_link_prepared',
                'order_status' => 'pending',
            ],
            'generate_paystack_link' => [
                'status' => 'payment_link_sent',
                'event' => 'paystack_link_generated',
                'order_status' => 'pending',
            ],
            'generate_paystack_link_send' => [
                'status' => 'payment_link_sent',
                'event' => 'paystack_link_generated',
                'order_status' => 'pending',
            ],
            'customer_says_paid' => [
                'status' => 'customer_says_paid',
                'event' => 'customer_says_paid',
                'order_status' => 'confirmed',
                'customer_says_paid' => true,
                'customer_says_paid_at' => now()->toIso8601String(),
                'customer_says_paid_by' => $actor?->name,
            ],
            'paid_confirmed' => [
                'status' => 'paid_confirmed',
                'event' => 'paid_confirmed',
                'order_status' => 'paid',
                'confirmed_by' => $actor?->name,
                'confirmed_at' => now()->toIso8601String(),
            ],
            'payment_failed' => [
                'status' => 'payment_failed',
                'event' => 'payment_failed',
                'order_status' => 'cancelled',
            ],
            'refunded' => [
                'status' => 'refunded',
                'event' => 'refunded',
                'order_status' => $status === 'paid_confirmed' ? 'cancelled' : (string) $order->status,
            ],
            default => [
                'status' => $status,
                'event' => null,
                'order_status' => (string) $order->status,
            ],
        };
    }

    public static function eventLabel(string $type): string
    {
        return match ($type) {
            'payment_instructions_prepared' => 'Payment instructions prepared',
            'payment_instructions_sent' => 'Payment instructions sent',
            'payment_instructions_copied' => 'Payment instructions copied instead',
            'payment_reminder_prepared' => 'Payment reminder prepared',
            'payment_reminder_sent' => 'Payment reminder sent',
            'payment_reminder_copied' => 'Payment reminder copied instead',
            'payment_link_prepared' => 'Payment link prepared',
            'payment_link_sent' => 'Payment link sent',
            'payment_link_copied' => 'Payment link copied instead',
            'paystack_link_generated' => 'Paystack link generated',
            'paystack_link_sent' => 'Paystack link sent',
            'paystack_link_copied' => 'Paystack link copied instead',
            'paystack_link_generation_failed' => 'Paystack link generation failed',
            'paystack_callback_received' => 'Paystack callback received',
            'paystack_payment_confirmed' => 'Paystack payment confirmed',
            'paystack_payment_failed' => 'Paystack payment failed',
            'paystack_callback_unmatched' => 'Paystack callback unmatched',
            'paystack_duplicate_callback_ignored' => 'Paystack duplicate callback ignored',
            'payment_mpesa_instructions_sent' => 'M-Pesa instructions sent',
            'payment_mpesa_instructions_copied' => 'M-Pesa instructions copied instead',
            'payment_mpesa_instructions_template_sent' => 'M-Pesa instructions sent with approved template',
            'payment_mpesa_instructions_template_failed' => 'Approved M-Pesa template failed',
            'daraja_stk_initiated' => 'Daraja sandbox STK initiated',
            'daraja_stk_initiation_failed' => 'Daraja sandbox STK initiation failed',
            'daraja_callback_success' => 'Daraja sandbox callback success received',
            'daraja_callback_failed' => 'Daraja sandbox callback failure received',
            'payment_instructions_template_required' => 'Template required but not configured',
            'payment_reminder_template_required' => 'Reminder template required but not configured',
            'payment_link_template_required' => 'Payment-link template required but not configured',
            'paystack_link_template_required' => 'Paystack-link template required but not configured',
            'payment_instructions_template_sent' => 'Payment instructions sent with approved template',
            'payment_instructions_template_failed' => 'Approved payment instructions template failed',
            'payment_reminder_template_sent' => 'Payment reminder sent with approved template',
            'payment_reminder_template_failed' => 'Approved payment reminder template failed',
            'payment_link_template_sent' => 'Payment link sent with approved template',
            'payment_link_template_failed' => 'Approved payment link template failed',
            'paystack_link_template_sent' => 'Paystack link sent with approved template',
            'paystack_link_template_failed' => 'Approved Paystack link template failed',
            'customer_says_paid' => 'Customer says paid',
            'reference_recorded' => 'Reference recorded',
            'payment_details_updated' => 'Payment details updated',
            'paid_confirmed' => 'Paid confirmed',
            'payment_failed' => 'Payment failed',
            'refunded' => 'Refunded',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    public static function eventTone(string $type): string
    {
        return match ($type) {
            'paid_confirmed' => 'success',
            'payment_failed', 'refunded' => 'danger',
            'payment_instructions_sent', 'payment_reminder_sent', 'payment_link_sent',
            'payment_mpesa_instructions_sent', 'paystack_link_generated', 'paystack_link_sent',
            'payment_instructions_template_sent', 'payment_reminder_template_sent', 'payment_link_template_sent',
            'paystack_link_template_sent', 'paystack_callback_received' => 'info',
            'payment_instructions_template_failed', 'payment_reminder_template_failed', 'payment_link_template_failed',
            'paystack_link_generation_failed', 'paystack_link_template_failed', 'paystack_payment_failed', 'paystack_callback_unmatched' => 'danger',
            'payment_mpesa_instructions_template_sent' => 'info',
            'payment_mpesa_instructions_template_failed',
            'daraja_stk_initiated', 'daraja_callback_success' => 'info',
            'daraja_stk_initiation_failed', 'daraja_callback_failed',
            'payment_instructions_template_required', 'payment_reminder_template_required', 'payment_link_template_required',
            'paystack_link_template_required' => 'danger',
            'paystack_payment_confirmed' => 'success',
            default => 'neutral',
        };
    }

    public static function deliveryStateLabel(?string $state): ?string
    {
        return match ((string) $state) {
            'pending' => 'Queued',
            'sent' => 'Sent',
            'submitted' => 'Submitted',
            'delivered' => 'Delivered',
            'read' => 'Read',
            'failed' => 'Failed',
            'copied_instead' => 'Copied instead',
            'template_required_not_configured' => 'Template required but not configured',
            default => null,
        };
    }

    private static function messageStateFromMessage(Message $message): string
    {
        if ($message->status === 'failed') {
            return 'failed';
        }
        if ($message->read_at) {
            return 'read';
        }
        if ($message->delivered_at) {
            return 'delivered';
        }
        if ($message->status === 'read') {
            return 'read';
        }
        if ($message->status === 'delivered') {
            return 'delivered';
        }
        if ($message->status === 'sent') {
            return 'sent';
        }

        return 'pending';
    }

    public static function parseAmount(?string $amount): ?string
    {
        $value = trim((string) $amount);
        if ($value === '') {
            return null;
        }

        return number_format((float) preg_replace('/[^0-9.]/', '', $value), 2, '.', '');
    }

    public static function displayAt(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('M d, Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
