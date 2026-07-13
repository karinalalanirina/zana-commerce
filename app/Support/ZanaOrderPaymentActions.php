<?php

namespace App\Support;

class ZanaOrderPaymentActions
{
    public static function build(array $context): array
    {
        $hasMpesaShortcut = (bool) ($context['has_mpesa_shortcut'] ?? false);
        $hideIndiaMerchantPayments = (bool) ($context['hide_india_merchant_payments'] ?? true);
        $storedExternalPaymentLink = trim((string) ($context['stored_external_payment_link'] ?? ''));
        $orderPaymentLink = trim((string) ($context['order_payment_link'] ?? ''));
        $paystackReadiness = is_array($context['paystack_readiness'] ?? null) ? $context['paystack_readiness'] : [];
        $darajaReadiness = is_array($context['daraja_readiness'] ?? null) ? $context['daraja_readiness'] : [];

        return [
            'kenya_shortcuts' => array_values(array_filter([
                $hasMpesaShortcut ? self::action(
                    id: 'send_mpesa_instructions',
                    label: 'Send M-Pesa instructions',
                    variant: 'primary',
                    classes: 'px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold'
                ) : null,
                self::action(
                    id: 'customer_says_paid',
                    label: 'Customer says paid',
                    variant: 'accent',
                    classes: 'px-4 py-2 rounded-full border border-[#D9E5F2] bg-[#F3F8FD] text-[#13478A] text-[12px] font-semibold hover:bg-[#E9F2FA]'
                ),
                self::action(
                    id: 'paid_confirmed',
                    label: 'Paid confirmed',
                    variant: 'success',
                    classes: 'px-4 py-2 rounded-full border border-wa-green/30 bg-wa-mint/40 text-wa-deep text-[12px] font-semibold hover:bg-wa-mint/70'
                ),
            ])),
            'provider_actions' => array_values(array_filter([
                self::action(
                    id: 'generate_paystack_link',
                    label: 'Generate Paystack link',
                    variant: 'provider',
                    disabled: empty($paystackReadiness['can_generate']),
                    reason: empty($paystackReadiness['can_generate']) ? ($paystackReadiness['notes'] ?? null) : null,
                    classes: 'px-3 py-1.5 border border-[#D9E5F2] rounded-full text-[12px] ' . (!empty($paystackReadiness['can_generate']) ? 'bg-[#F3F8FD] text-[#13478A] hover:bg-[#E9F2FA]' : 'text-ink-500 bg-paper-50')
                ),
                self::action(
                    id: 'generate_paystack_link_send',
                    label: 'Generate Paystack link + send',
                    variant: 'provider-primary',
                    disabled: empty($paystackReadiness['can_generate']),
                    reason: empty($paystackReadiness['can_generate']) ? ($paystackReadiness['notes'] ?? null) : null,
                    classes: 'px-3 py-1.5 border border-wa-deep/40 text-wa-deep rounded-full text-[12px] hover:bg-wa-mint/40 font-semibold ' . (empty($paystackReadiness['can_generate']) ? 'opacity-60 cursor-not-allowed' : '')
                ),
                !empty($darajaReadiness['enabled']) ? self::action(
                    id: 'send_daraja_stk',
                    label: 'Send M-Pesa STK Push',
                    variant: 'provider',
                    disabled: empty($darajaReadiness['can_initiate']),
                    reason: empty($darajaReadiness['can_initiate']) ? ($darajaReadiness['notes'] ?? null) : null,
                    classes: 'px-3 py-1.5 border border-[#D9E5F2] rounded-full text-[12px] ' . (!empty($darajaReadiness['can_initiate']) ? 'bg-[#F3F8FD] text-[#13478A] hover:bg-[#E9F2FA]' : 'text-ink-500 bg-paper-50')
                ) : null,
                !$hideIndiaMerchantPayments ? self::action(
                    id: null,
                    label: 'Generate Razorpay link + send',
                    variant: 'provider-primary',
                    handler: 'generatePaymentLink()',
                    classes: 'px-3 py-1.5 border border-wa-deep/40 text-wa-deep rounded-full text-[12px] hover:bg-wa-mint/40 font-semibold'
                ) : null,
            ])),
            'payment_messaging' => array_values(array_filter([
                self::action(
                    id: 'send_instructions',
                    label: $hasMpesaShortcut ? 'Send general payment instructions' : 'Send payment instructions',
                    variant: 'message-primary',
                    classes: 'px-3 py-1.5 border border-wa-deep/40 text-wa-deep rounded-full text-[12px] hover:bg-wa-mint/40 font-semibold'
                ),
                self::action(
                    id: 'send_reminder',
                    label: 'Send payment reminder',
                    variant: 'message',
                    classes: 'px-3 py-1.5 border border-paper-200 rounded-full text-[12px] hover:bg-paper-50'
                ),
                ($storedExternalPaymentLink !== '' || $orderPaymentLink !== '') ? self::action(
                    id: null,
                    label: 'Send payment link',
                    variant: 'message',
                    handler: 'sendPaymentLink()',
                    classes: 'px-3 py-1.5 border border-paper-200 rounded-full text-[12px] hover:bg-paper-50'
                ) : null,
                ($storedExternalPaymentLink !== '' || $orderPaymentLink !== '') ? self::action(
                    id: 'resend_link',
                    label: 'Resend payment link',
                    variant: 'message',
                    classes: 'px-3 py-1.5 border border-paper-200 rounded-full text-[12px] hover:bg-paper-50'
                ) : null,
            ])),
            'payment_state_updates' => [
                self::action(
                    id: 'payment_failed',
                    label: 'Mark payment failed',
                    variant: 'state',
                    classes: 'px-3 py-1.5 border border-paper-200 rounded-full text-[12px] hover:bg-paper-50'
                ),
                self::action(
                    id: 'refunded',
                    label: 'Mark refunded',
                    variant: 'state',
                    classes: 'px-3 py-1.5 border border-paper-200 rounded-full text-[12px] hover:bg-paper-50'
                ),
            ],
            'primary_submit' => [
                [
                    'label' => 'Save',
                    'type' => 'submit',
                    'classes' => 'px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold',
                ],
            ],
        ];
    }

    private static function action(
        ?string $id,
        string $label,
        string $variant,
        string $classes,
        bool $disabled = false,
        ?string $reason = null,
        ?string $handler = null
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'variant' => $variant,
            'classes' => $classes,
            'disabled' => $disabled,
            'reason' => $reason,
            'handler' => $handler,
            'type' => 'button',
        ];
    }
}
