<?php

namespace Tests\Feature;

use App\Http\Controllers\WaOrderController;
use App\Http\Controllers\StorefrontPaymentController;
use App\Models\WaTemplate;
use App\Models\User;
use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Services\WhatsAppDispatcher;
use App\Services\Waba\TemplateSender;
use App\Support\ZanaKenyaPaymentShortcut;
use App\Support\ZanaManualPayment;
use App\Support\ZanaPaystackMerchantLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class ZanaManualPaymentWorkflowTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        $this->extendCommerceSchema();
    }

    public function test_manual_payment_confirmation_fields_are_saved_without_schema_changes(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => false,
                'local_only' => false,
                'error' => 'No connected device',
            ]);
        });

        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'notes' => 'Waiting for transfer screenshot',
            'payment_link' => '',
            'payment_action' => 'customer_says_paid',
            'zana_payment_status' => 'customer_says_paid',
            'zana_payment_method' => 'mpesa_till',
            'zana_payment_reference' => 'MPP12345',
            'zana_amount_received' => '2500',
            'zana_payer_note' => 'Paid from spouse phone',
            'zana_confirmation_note' => 'Merchant saw M-Pesa SMS pending final verification',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame('customer_says_paid', ZanaManualPayment::paymentStatus($fresh));
        $this->assertSame('mpesa_till', $payment['payment_method'] ?? null);
        $this->assertSame('MPP12345', $payment['transaction_reference'] ?? null);
        $this->assertSame('2500.00', $payment['amount_received'] ?? null);
        $this->assertSame('Paid from spouse phone', $payment['payer_note'] ?? null);
        $this->assertSame('Merchant saw M-Pesa SMS pending final verification', $payment['confirmation_note'] ?? null);
        $this->assertNotEmpty($payment['customer_says_paid_at'] ?? null);
        $this->assertSame('Workspace User', $payment['customer_says_paid_by'] ?? null);
        $this->assertCount(1, $timeline);
        $this->assertSame('customer_says_paid', $timeline[0]['type'] ?? null);
    }

    public function test_send_payment_instructions_falls_back_to_copy_when_native_send_is_unavailable(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_till_number' => '123456',
                'accepted_payment_methods_text' => 'M-Pesa or bank transfer',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => false,
                'local_only' => false,
                'platform' => 'WB',
                'error' => 'No connected device',
            ]);
        });

        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'notes' => '',
            'payment_link' => '',
            'payment_action' => 'send_instructions',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'mpesa_till',
            'zana_payment_reference' => '',
            'zana_amount_received' => '',
            'zana_payer_note' => '',
            'zana_confirmation_note' => '',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotNull($response->getSession()->get('zana_payment_copy'));

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('awaiting_payment', ZanaManualPayment::paymentStatus($fresh));
        $this->assertSame('copy', $payment['last_send_channel'] ?? null);
        $this->assertSame('fallback_copy', $payment['last_send_result'] ?? null);
        $this->assertStringContainsString('Zuri Beauty Store', $payment['last_payment_message'] ?? '');
        $this->assertSame('payment_instructions_copied', $timeline[0]['type'] ?? null);
    }

    public function test_send_payment_instructions_uses_approved_template_after_24_hour_window_failure(): void
    {
        $user = $this->makeWorkspaceUser();
        $template = WaTemplate::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id,
            'channel' => 'waba',
            'meta_status' => 'APPROVED',
            'quality_score' => 'GREEN',
            'template_name' => 'zana_payment_reengage',
            'meta_category' => 'UTILITY',
            'template_type' => 'standard',
            'template_body' => 'Hello {{1}}',
            'language' => 'en',
            'status' => 'approved',
        ]);
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_till_number' => '123456',
                'payment_instruction_template_id' => $template->id,
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => false,
                'local_only' => false,
                'platform' => 'WB',
                'error' => 'Meta error 131047: 24-hour customer service window expired',
            ]);
        });
        $this->mock(TemplateSender::class, function ($mock) use ($template) {
            $mock->shouldReceive('send')->once()->withArgs(function ($resolvedTemplate, $to, $vars) use ($template) {
                return (int) $resolvedTemplate->id === (int) $template->id
                    && $to === '254700000010'
                    && str_contains(strtolower((string) ($vars['body'][0] ?? '')), 'order #1');
            })->andReturn([
                'ok' => true,
                'code' => 'ok',
                'wamid' => 'wamid.template.123',
                'template_id' => $template->id,
                'template_name' => 'zana_payment_reengage',
            ]);
        });

        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'notes' => '',
            'payment_link' => '',
            'payment_action' => 'send_instructions',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'mpesa_till',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($response->getSession()->get('zana_payment_copy'));

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('waba_template', $payment['last_send_channel'] ?? null);
        $this->assertSame('template_fallback_sent', $payment['last_send_result'] ?? null);
        $this->assertSame($template->id, $payment['last_template_fallback_template_id'] ?? null);
        $this->assertSame('payment_instructions_template_sent', $timeline[0]['type'] ?? null);
    }

    public function test_send_payment_instructions_records_native_send_success(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_paybill_number' => '400200',
                'accepted_payment_methods_text' => 'M-Pesa or payment link',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => true,
                'local_only' => false,
                'platform' => 'WB',
                'provider_id' => 'wamid.123',
            ]);
        });

        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'notes' => '',
            'payment_link' => '',
            'payment_action' => 'send_instructions',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'mpesa_paybill',
            'zana_payment_reference' => '',
            'zana_amount_received' => '',
            'zana_payer_note' => '',
            'zana_confirmation_note' => '',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNull($response->getSession()->get('zana_payment_copy'));

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('whatsapp', $payment['last_send_channel'] ?? null);
        $this->assertSame('sent', $payment['last_send_result'] ?? null);
        $this->assertSame('payment_instructions_sent', $timeline[0]['type'] ?? null);
    }

    public function test_send_mpesa_instructions_uses_kenya_copy_and_fast_event(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_till_number' => '123456',
                'mpesa_paybill_number' => '400200',
                'payment_reference_format' => 'ORDER-1',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => true,
                'local_only' => false,
                'platform' => 'WB',
                'provider_id' => 'wamid.mpesa.1',
            ]);
        });

        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'payment_action' => 'send_mpesa_instructions',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'mpesa_till',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('whatsapp', $payment['last_send_channel'] ?? null);
        $this->assertSame('payment_mpesa_instructions_sent', $timeline[0]['type'] ?? null);
        $this->assertSame('sent', $timeline[0]['message_delivery_state'] ?? null);
    }

    public function test_kenya_shortcut_helper_builds_stronger_mpesa_copy(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_till_number' => '123456',
                'mpesa_paybill_number' => '400200',
                'payment_reference_format' => 'ORDER-1',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $copy = ZanaKenyaPaymentShortcut::instructionText($storefront, $order);

        $this->assertStringContainsString('Till: 123456', $copy);
        $this->assertStringContainsString('Paybill: 400200', $copy);
        $this->assertStringContainsString('reply with your M-Pesa confirmation code', $copy);
    }

    public function test_24_hour_failure_without_configured_template_is_reported_honestly(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_till_number' => '123456',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => false,
                'local_only' => false,
                'platform' => 'WB',
                'error' => 'Meta error 131047: 24-hour customer service window expired',
            ]);
        });

        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'payment_action' => 'send_mpesa_instructions',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'mpesa_till',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotNull($response->getSession()->get('zana_payment_copy'));

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('template_required_not_configured', $payment['last_send_result'] ?? null);
        $this->assertSame('payment_instructions_template_required', $timeline[0]['type'] ?? null);
        $this->assertSame('template_required_not_configured', $timeline[0]['message_delivery_state'] ?? null);
    }

    public function test_timeline_surfaces_delivered_and_read_states_from_message_row(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $message = \App\Models\Message::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id,
            'direction' => 'out',
            'to_number' => '254700000010',
            'body' => 'Payment reminder',
            'status' => 'read',
            'sent_at' => now()->subMinutes(2),
            'delivered_at' => now()->subMinute(),
            'read_at' => now(),
            'meta' => ['wa_message_id' => 'wamid.read.1'],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::EVENTS_KEY => [[
                    'type' => 'payment_reminder_sent',
                    'label' => 'Payment reminder sent',
                    'at' => now()->toIso8601String(),
                    'message_id' => $message->id,
                ]],
            ],
        ]);

        $timeline = ZanaManualPayment::timeline($order);

        $this->assertSame('read', $timeline[0]['message_delivery_state'] ?? null);
        $this->assertSame('Read', $timeline[0]['message_delivery_label'] ?? null);
        $this->assertNotEmpty($timeline[0]['message_read_at'] ?? null);
    }

    public function test_order_page_shows_compact_manual_mpesa_payment_status_block(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'customer_says_paid',
                    'payment_method' => 'mpesa_till',
                    'transaction_reference' => 'QWE123XYZ',
                ],
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Payment status', $html);
        $this->assertStringContainsString('Manual M-Pesa', $html);
        $this->assertStringContainsString('Customer Says Paid', $html);
        $this->assertStringContainsString('Review payment reference', $html);
        $this->assertStringContainsString('QWE123XYZ', $html);
        $this->assertStringContainsString('Payment history', $html);
    }

    public function test_order_page_shows_paystack_confirmed_summary_with_exact_amount_match(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront, [
            'status' => 'paid',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'paid_confirmed',
                    'payment_method' => 'payment_link',
                    'transaction_reference' => 'PSK-REF-1',
                    'paystack' => [
                        'reference' => 'PSK-REF-1',
                        'status' => 'confirmed',
                        'callback_received_at' => now()->toIso8601String(),
                        'amount_matches_order' => 'yes',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Paystack', $html);
        $this->assertStringContainsString('Paid Confirmed', $html);
        $this->assertStringContainsString('Callback received', $html);
        $this->assertStringContainsString('Exact amount match', $html);
        $this->assertStringContainsString('No action needed', $html);
    }

    public function test_order_page_shows_paystack_amount_mismatch_summary(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront, [
            'status' => 'confirmed',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'customer_says_paid',
                    'payment_method' => 'payment_link',
                    'transaction_reference' => 'PSK-MISMATCH-1',
                    'paystack' => [
                        'reference' => 'PSK-MISMATCH-1',
                        'status' => 'awaiting_verification',
                        'callback_received_at' => now()->toIso8601String(),
                        'amount_matches_order' => 'no',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Paystack', $html);
        $this->assertStringContainsString('Amount mismatch', $html);
        $this->assertStringContainsString('Review payment reference', $html);
    }

    public function test_order_page_guidance_panels_use_paystack_readiness_state(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
                'paystack_reference_prefix' => 'ZURI',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'payment_link_sent',
                    'paystack' => [
                        'reference' => 'ZURI-ORD-1',
                        'status' => 'link_generated',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Paystack order links', $html);
        $this->assertStringContainsString('Ready', $html);
        $this->assertStringContainsString('Fallback email', $html);
        $this->assertStringContainsString('payments@zuri.test', $html);
        $this->assertStringContainsString('Last Paystack reference', $html);
        $this->assertStringContainsString('ZURI-ORD-1', $html);
    }

    public function test_order_page_shows_daraja_stk_summary_when_callback_is_pending(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'awaiting_payment',
                    'payment_method' => 'daraja_stk',
                    'daraja' => [
                        'status' => 'initiated',
                        'merchant_request_id' => 'MERCHANT-1',
                        'checkout_request_id' => 'CHECKOUT-1',
                    ],
                ],
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Daraja STK', $html);
        $this->assertStringContainsString('STK initiated', $html);
        $this->assertStringContainsString('Check callback', $html);
    }

    public function test_order_page_guidance_panels_show_daraja_missing_config_state(): void
    {
        config()->set('zana.enable_daraja_sandbox', true);

        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'daraja_enabled' => true,
                'daraja_environment' => 'sandbox',
                'daraja_shortcode' => '',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Daraja STK sandbox', $html);
        $this->assertStringContainsString('Config incomplete', $html);
        $this->assertStringContainsString('Missing: shortcode, consumer key, consumer secret, passkey.', $html);
        $this->assertStringContainsString('Callback testing', $html);
    }

    public function test_orders_index_shows_payment_state_badges_and_supports_reference_search(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront, [
            'customer_name' => 'Amina',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'customer_says_paid',
                    'transaction_reference' => 'REF-SEARCH-123',
                    'payment_method' => 'bank_transfer',
                ],
            ],
        ]);

        $this->actingAs($user);
        $view = app(WaOrderController::class)->index($this->request($user, [
            'q' => 'REF-SEARCH-123',
            'payment_state' => 'customer_says_paid',
        ], 'GET'));
        $html = $view->render();

        $this->assertStringContainsString('Customer Says Paid', $html);
        $this->assertStringContainsString('REF-SEARCH-123', $html);
        $this->assertStringContainsString('All payment states', $html);
        $this->assertStringContainsString((string) $order->id, $html);
        $this->assertStringContainsString('Needs review:', $html);
    }

    public function test_orders_index_filters_awaiting_verification_and_payer_note_search(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $matching = $this->makeOrder($user, $storefront, [
            'customer_name' => 'Amina',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'customer_says_paid',
                    'transaction_reference' => '',
                    'payment_method' => 'mpesa_till',
                    'payer_note' => 'Paid from branch phone',
                ],
            ],
        ]);
        $this->makeOrder($user, $storefront, [
            'customer_name' => 'Other Customer',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'paid_confirmed',
                    'transaction_reference' => 'CONF-1',
                    'payment_method' => 'mpesa_till',
                ],
            ],
        ]);

        $this->actingAs($user);
        $view = app(WaOrderController::class)->index($this->request($user, [
            'q' => 'branch phone',
            'verification_state' => 'awaiting_verification',
        ], 'GET'));
        $html = $view->render();

        $this->assertStringContainsString('Awaiting Verification', $html);
        $this->assertStringContainsString((string) $matching->id, $html);
        $this->assertStringContainsString('Reference still missing', $html);
        $this->assertStringContainsString('Paid Confirmed', $html);
    }

    public function test_orders_index_supports_order_reference_and_amount_search_plus_weekly_report_details(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $matching = $this->makeOrder($user, $storefront, [
            'customer_name' => 'Zuri Buyer',
            'total_minor' => 420000,
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'customer_says_paid',
                    'transaction_reference' => 'MPESA-420',
                    'payment_method' => 'mpesa_till',
                    'amount_received' => '4200.00',
                    'amount_received_currency' => 'KES',
                ],
            ],
        ]);
        $this->makeOrder($user, $storefront, [
            'customer_name' => 'Card Customer',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'paid_confirmed',
                    'payment_method' => 'payment_link',
                    'amount_received' => '2500.00',
                    'amount_received_currency' => 'KES',
                    'confirmed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $this->actingAs($user);
        $view = app(WaOrderController::class)->index($this->request($user, [
            'q' => 'ORDER-' . $matching->id,
            'report_days' => 7,
        ], 'GET'));
        $html = $view->render();

        $this->assertStringContainsString('Amount still awaiting verification', $html);
        $this->assertStringContainsString('Payment method breakdown', $html);
        $this->assertStringContainsString('Recent payment activity', $html);
        $this->assertStringContainsString('M-Pesa Till', $html);
        $this->assertStringContainsString('Payment Link', $html);
        $this->assertStringContainsString('ORDER-' . $matching->id, $html);
        $this->assertTrue(
            str_contains($html, '4,200.00') || str_contains($html, '4200.00'),
            'Expected the weekly report or order row to show the searchable amount.'
        );
    }

    public function test_orders_index_can_export_payment_csv(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $this->makeOrder($user, $storefront, [
            'customer_name' => 'Amina',
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'paid_confirmed',
                    'payment_method' => 'mpesa_paybill',
                    'transaction_reference' => 'MPESA-EXPORT-1',
                    'amount_received' => '2500.00',
                    'amount_received_currency' => 'KES',
                    'confirmed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $this->actingAs($user);
        $response = app(WaOrderController::class)->index($this->request($user, [
            'export' => 'csv',
        ], 'GET'));

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('content-type'));
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('order_id,order_reference,customer_name,customer_phone', $csv);
        $this->assertStringContainsString('MPESA-EXPORT-1', $csv);
        $this->assertStringContainsString('Paid Confirmed', $csv);
        $this->assertStringContainsString('M-Pesa Paybill', $csv);
        $this->assertStringContainsString('KES 2,500.00', $csv);
    }

    public function test_storefront_edit_shows_payment_template_guidance(): void
    {
        $user = $this->makeWorkspaceUser();
        $template = WaTemplate::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id,
            'channel' => 'waba',
            'meta_status' => 'APPROVED',
            'template_name' => 'zana_payment_instruction',
            'meta_category' => 'UTILITY',
            'template_body' => 'Hello {{1}}',
            'language' => 'en',
        ]);
        $this->makeStorefront($user, [
            'payment_config_json' => [
                'payment_instruction_template_id' => $template->id,
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(\App\Http\Controllers\WaStorefrontController::class)->edit()->render();

        $this->assertStringContainsString('Template guidance', $html);
        $this->assertStringContainsString('Configured and approved', $html);
        $this->assertStringContainsString('Not configured', $html);
    }

    public function test_storefront_edit_shows_paystack_order_link_configuration_state(): void
    {
        $user = $this->makeWorkspaceUser();
        $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(\App\Http\Controllers\WaStorefrontController::class)->edit()->render();

        $this->assertStringContainsString('Paystack order links', $html);
        $this->assertStringContainsString('Paystack ready for order links', $html);
        $this->assertStringContainsString('Fallback customer email', $html);
    }

    public function test_daraja_ui_stays_hidden_when_feature_flag_is_off(): void
    {
        config()->set('zana.enable_daraja_sandbox', false);

        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $storefrontHtml = app(\App\Http\Controllers\WaStorefrontController::class)->edit()->render();
        $orderHtml = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Daraja STK testing', $storefrontHtml);
        $this->assertStringContainsString('feature flag is off', $storefrontHtml);
        $this->assertStringNotContainsString('Send M-Pesa STK Push', $orderHtml);
        $this->assertStringContainsString('Daraja STK sandbox', $orderHtml);
        $this->assertStringContainsString('Hidden by feature flag', $orderHtml);
    }

    public function test_daraja_ui_fields_and_stk_action_show_when_feature_flag_and_config_are_present(): void
    {
        config()->set('zana.enable_daraja_sandbox', true);

        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'daraja_enabled' => true,
                'daraja_environment' => 'sandbox',
                'daraja_shortcode' => '174379',
                'daraja_consumer_key' => \App\Support\ZanaDarajaSandbox::encryptSecret('sandbox-key'),
                'daraja_consumer_secret' => \App\Support\ZanaDarajaSandbox::encryptSecret('sandbox-secret'),
                'daraja_passkey' => \App\Support\ZanaDarajaSandbox::encryptSecret('sandbox-passkey'),
                'daraja_callback_enabled' => true,
                'daraja_callback_token' => 'zana-daraja-token',
                'daraja_reference_prefix' => 'ORDER',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $storefrontHtml = app(\App\Http\Controllers\WaStorefrontController::class)->edit()->render();
        $orderHtml = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Daraja sandbox (staging only)', $storefrontHtml);
        $this->assertStringContainsString('Business short code', $storefrontHtml);
        $this->assertStringContainsString('Consumer key', $storefrontHtml);
        $this->assertStringContainsString('Sandbox callback URL', $storefrontHtml);
        $this->assertStringContainsString('Send M-Pesa STK Push', $orderHtml);
    }

    public function test_generate_paystack_link_fails_safely_when_merchant_config_is_missing(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_fallback_customer_email' => '',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $this->actingAs($user);
        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'payment_action' => 'generate_paystack_link',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'payment_link',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('generation_failed', $payment['last_send_result'] ?? null);
        $this->assertSame('generation_failed', $payment['paystack']['status'] ?? null);
        $this->assertSame('paystack_link_generation_failed', $timeline[0]['type'] ?? null);
    }

    public function test_generate_paystack_link_succeeds_and_records_order_specific_metadata(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
                'paystack_reference_prefix' => 'ZURI',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'customer_email' => '',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/zuri-order-1',
                    'access_code' => 'ACCESS-1',
                    'reference' => 'ZURI-ORD-1-120000',
                ],
            ], 200),
        ]);

        $this->actingAs($user);
        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'payment_action' => 'generate_paystack_link',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'payment_link',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://checkout.paystack.com/zuri-order-1', $order->fresh()->payment_link);

        $payment = ZanaManualPayment::paymentMeta($order->fresh());
        $timeline = ZanaManualPayment::timeline($order->fresh());

        $this->assertSame('link_generated', $payment['paystack']['status'] ?? null);
        $this->assertSame('ZURI-ORD-1-120000', $payment['paystack']['reference'] ?? null);
        $this->assertSame('ACCESS-1', $payment['paystack']['access_code'] ?? null);
        $this->assertSame('paystack_link_generated', $timeline[0]['type'] ?? null);
    }

    public function test_generate_paystack_link_and_send_preserves_copy_fallback_when_whatsapp_send_is_unavailable(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
                'payment_instruction_template_id' => null,
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'customer_email' => '',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/zuri-order-send',
                    'access_code' => 'ACCESS-2',
                    'reference' => 'ZANA-ORD-1-120500',
                ],
            ], 200),
        ]);
        $this->mock(WhatsAppDispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->once()->andReturn([
                'ok' => false,
                'local_only' => false,
                'platform' => 'WB',
                'error' => 'No connected device',
            ]);
        });

        $this->actingAs($user);
        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'payment_action' => 'generate_paystack_link_send',
            'zana_payment_status' => 'awaiting_payment',
            'zana_payment_method' => 'payment_link',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotNull($response->getSession()->get('zana_payment_copy'));

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('https://checkout.paystack.com/zuri-order-send', $fresh->payment_link);
        $this->assertSame('copy', $payment['last_send_channel'] ?? null);
        $this->assertContains('paystack_link_generated', array_column($timeline, 'type'));
        $this->assertContains('paystack_link_copied', array_column($timeline, 'type'));
    }

    public function test_paystack_callback_confirms_order_when_signature_and_amount_match(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'payment_link_sent',
                    'payment_method' => 'payment_link',
                    'paystack' => [
                        'reference' => 'ZURI-REF-1',
                        'status' => 'link_generated',
                    ],
                ],
            ],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 991,
                'reference' => 'ZURI-REF-1',
                'status' => 'success',
                'amount' => 250000,
                'currency' => 'KES',
                'paid_at' => now()->toIso8601String(),
                'customer' => ['email' => 'buyer@example.com'],
                'metadata' => ['wa_order_id' => $order->id],
            ],
        ];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha512', $raw, 'sk_test_123');

        $request = Request::create('/webhooks/storefront-pay/paystack', 'POST', [], [], [], [], $raw);
        $request->headers->set('x-paystack-signature', $signature);
        $request->headers->set('Content-Type', 'application/json');

        $response = app(StorefrontPaymentController::class)->paystackMerchantWebhook($request);

        $this->assertSame(200, $response->getStatusCode());
        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('paid', $fresh->status);
        $this->assertSame('paid_confirmed', ZanaManualPayment::paymentStatus($fresh));
        $this->assertSame('confirmed', $payment['paystack']['status'] ?? null);
        $this->assertSame('ZURI-REF-1', $payment['transaction_reference'] ?? null);
        $this->assertContains('paystack_payment_confirmed', array_column($timeline, 'type'));
    }

    public function test_paystack_callback_with_amount_mismatch_stays_awaiting_verification(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'payment_link_sent',
                    'payment_method' => 'payment_link',
                    'paystack' => [
                        'reference' => 'ZURI-REF-2',
                        'status' => 'link_generated',
                    ],
                ],
            ],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 992,
                'reference' => 'ZURI-REF-2',
                'status' => 'success',
                'amount' => 200000,
                'currency' => 'KES',
            ],
        ];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha512', $raw, 'sk_test_123');

        $request = Request::create('/webhooks/storefront-pay/paystack', 'POST', [], [], [], [], $raw);
        $request->headers->set('x-paystack-signature', $signature);
        $request->headers->set('Content-Type', 'application/json');

        $response = app(StorefrontPaymentController::class)->paystackMerchantWebhook($request);

        $this->assertSame(200, $response->getStatusCode());
        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);

        $this->assertSame('confirmed', $fresh->status);
        $this->assertSame('customer_says_paid', ZanaManualPayment::paymentStatus($fresh));
        $this->assertSame('awaiting_verification', $payment['paystack']['status'] ?? null);
    }

    public function test_paystack_duplicate_callback_is_idempotent(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'payment_link_sent',
                    'payment_method' => 'payment_link',
                    'paystack' => [
                        'reference' => 'ZURI-REF-3',
                        'status' => 'link_generated',
                    ],
                ],
            ],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 993,
                'reference' => 'ZURI-REF-3',
                'status' => 'success',
                'amount' => 250000,
                'currency' => 'KES',
            ],
        ];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha512', $raw, 'sk_test_123');

        $requestOne = Request::create('/webhooks/storefront-pay/paystack', 'POST', [], [], [], [], $raw);
        $requestOne->headers->set('x-paystack-signature', $signature);
        $requestOne->headers->set('Content-Type', 'application/json');
        $requestTwo = Request::create('/webhooks/storefront-pay/paystack', 'POST', [], [], [], [], $raw);
        $requestTwo->headers->set('x-paystack-signature', $signature);
        $requestTwo->headers->set('Content-Type', 'application/json');

        $first = app(StorefrontPaymentController::class)->paystackMerchantWebhook($requestOne);
        $second = app(StorefrontPaymentController::class)->paystackMerchantWebhook($requestTwo);

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame(1, $payment['paystack']['duplicate_callback_count'] ?? 1);
        $this->assertCount(1, array_values(array_filter($timeline, fn ($event) => ($event['type'] ?? null) === 'paystack_payment_confirmed')));
    }

    public function test_paystack_unmatched_callback_is_handled_safely(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'UNKNOWN-REF',
                'status' => 'success',
                'amount' => 250000,
                'currency' => 'KES',
            ],
        ];
        $raw = json_encode($payload);
        $request = Request::create('/webhooks/storefront-pay/paystack', 'POST', [], [], [], [], $raw);
        $request->headers->set('x-paystack-signature', hash_hmac('sha512', $raw, 'sk_test_123'));
        $request->headers->set('Content-Type', 'application/json');

        $response = app(StorefrontPaymentController::class)->paystackMerchantWebhook($request);

        $this->assertSame(202, $response->getStatusCode());
    }

    public function test_paystack_bad_signature_is_rejected(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'paystack_enabled' => true,
                'paystack_secret_key' => ZanaPaystackMerchantLink::encryptSecret('sk_test_123'),
                'paystack_fallback_customer_email' => 'payments@zuri.test',
            ],
        ]);
        $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'payment_link_sent',
                    'payment_method' => 'payment_link',
                    'paystack' => [
                        'reference' => 'ZURI-REF-4',
                        'status' => 'link_generated',
                    ],
                ],
            ],
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ZURI-REF-4',
                'status' => 'success',
                'amount' => 250000,
                'currency' => 'KES',
            ],
        ];
        $raw = json_encode($payload);
        $request = Request::create('/webhooks/storefront-pay/paystack', 'POST', [], [], [], [], $raw);
        $request->headers->set('x-paystack-signature', 'bad-signature');
        $request->headers->set('Content-Type', 'application/json');

        $response = app(StorefrontPaymentController::class)->paystackMerchantWebhook($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_daraja_stk_initiation_stores_request_ids_when_feature_flag_and_config_are_present(): void
    {
        config()->set('zana.enable_daraja_sandbox', true);

        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'daraja_enabled' => true,
                'daraja_environment' => 'sandbox',
                'daraja_shortcode' => '174379',
                'daraja_consumer_key' => \App\Support\ZanaDarajaSandbox::encryptSecret('sandbox-key'),
                'daraja_consumer_secret' => \App\Support\ZanaDarajaSandbox::encryptSecret('sandbox-secret'),
                'daraja_passkey' => \App\Support\ZanaDarajaSandbox::encryptSecret('sandbox-passkey'),
                'daraja_callback_enabled' => true,
                'daraja_callback_token' => 'zana-daraja-token',
                'daraja_reference_prefix' => 'ORDER',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'customer_phone' => '0700000010',
        ]);

        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response(['access_token' => 'sandbox-token'], 200),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => 'MERCHANT-1',
                'CheckoutRequestID' => 'CHECKOUT-1',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'STK request sent',
            ], 200),
        ]);

        $this->actingAs($user);
        $response = app(WaOrderController::class)->updateStatus($this->request($user, [
            'status' => 'pending',
            'payment_action' => 'send_daraja_stk',
            'zana_payment_status' => 'awaiting_payment',
        ], 'PUT'), $order->id);

        $this->assertSame(302, $response->getStatusCode());

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('daraja_stk', $payment['payment_method'] ?? null);
        $this->assertSame('initiated', $payment['daraja']['status'] ?? null);
        $this->assertSame('MERCHANT-1', $payment['daraja']['merchant_request_id'] ?? null);
        $this->assertSame('CHECKOUT-1', $payment['daraja']['checkout_request_id'] ?? null);
        $this->assertSame('daraja_stk_initiated', $timeline[0]['type'] ?? null);
    }

    public function test_daraja_storefront_edit_shows_safe_guidance_when_feature_flag_is_on_but_config_is_incomplete(): void
    {
        config()->set('zana.enable_daraja_sandbox', true);

        $user = $this->makeWorkspaceUser();
        $this->makeStorefront($user, [
            'payment_config_json' => [
                'daraja_enabled' => true,
                'daraja_environment' => 'sandbox',
                'daraja_shortcode' => '',
            ],
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());
        $html = app(\App\Http\Controllers\WaStorefrontController::class)->edit()->render();

        $this->assertStringContainsString('Daraja sandbox', $html);
        $this->assertStringContainsString('Sandbox config incomplete', $html);
        $this->assertStringContainsString('Add shortcode, consumer key, consumer secret, and passkey before testing STK initiation.', $html);
    }

    public function test_daraja_callback_success_links_order_and_duplicate_is_idempotent(): void
    {
        config()->set('zana.enable_daraja_sandbox', true);

        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'daraja_enabled' => true,
                'daraja_environment' => 'sandbox',
                'daraja_callback_token' => 'zana-daraja-token',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'awaiting_payment',
                    'daraja' => [
                        'merchant_request_id' => 'MERCHANT-1',
                        'checkout_request_id' => 'CHECKOUT-1',
                    ],
                ],
            ],
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'MERCHANT-1',
                    'CheckoutRequestID' => 'CHECKOUT-1',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 2500],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                            ['Name' => 'PhoneNumber', 'Value' => 254700000010],
                        ],
                    ],
                ],
            ],
        ];

        $controller = app(StorefrontPaymentController::class);
        $first = $controller->darajaSandboxWebhook($this->request(null, $payload, 'POST'), 'zana-daraja-token');
        $second = $controller->darajaSandboxWebhook($this->request(null, $payload, 'POST'), 'zana-daraja-token');

        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode());

        $fresh = $order->fresh();
        $payment = ZanaManualPayment::paymentMeta($fresh);
        $timeline = ZanaManualPayment::timeline($fresh);

        $this->assertSame('customer_says_paid', ZanaManualPayment::paymentStatus($fresh));
        $this->assertSame('daraja_stk', $payment['payment_method'] ?? null);
        $this->assertSame('NLJ7RT61SV', $payment['transaction_reference'] ?? null);
        $this->assertSame('callback_success', $payment['daraja']['status'] ?? null);
        $this->assertSame(1, $payment['daraja']['duplicate_callback_count'] ?? 1);
        $this->assertCount(1, array_values(array_filter($timeline, fn ($event) => ($event['type'] ?? null) === 'daraja_callback_success')));
    }

    public function test_daraja_unmatched_callback_is_handled_safely_without_mutating_orders(): void
    {
        config()->set('zana.enable_daraja_sandbox', true);

        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'payment_config_json' => [
                'daraja_enabled' => true,
                'daraja_environment' => 'sandbox',
                'daraja_callback_token' => 'zana-daraja-token',
            ],
        ]);
        $order = $this->makeOrder($user, $storefront);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'UNKNOWN-MERCHANT',
                    'CheckoutRequestID' => 'UNKNOWN-CHECKOUT',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                ],
            ],
        ];

        $response = app(StorefrontPaymentController::class)
            ->darajaSandboxWebhook($this->request(null, $payload, 'POST'), 'zana-daraja-token');

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame([], ZanaManualPayment::timeline($order->fresh()));
        $this->assertSame('awaiting_payment', ZanaManualPayment::paymentStatus($order->fresh()));
    }

    public function test_order_detail_shows_manual_review_cues_for_verification(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user);
        $order = $this->makeOrder($user, $storefront, [
            'meta_json' => [
                ZanaManualPayment::PAYMENT_KEY => [
                    'status' => 'customer_says_paid',
                    'payment_method' => 'mpesa_till',
                    'payer_note' => 'Paid from salon number',
                ],
            ],
        ]);

        $this->actingAs($user);
        $html = app(WaOrderController::class)->show($order->id)->render();

        $this->assertStringContainsString('Order reference', $html);
        $this->assertStringContainsString('Expected amount', $html);
        $this->assertStringContainsString('Manual review cue', $html);
        $this->assertStringContainsString('Ask the customer for the M-Pesa confirmation code', $html);
    }

    private function extendCommerceSchema(): void
    {
        Schema::create('wa_storefronts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('slug')->nullable();
            $table->string('custom_domain')->nullable();
            $table->boolean('custom_domain_verified')->default(false);
            $table->string('theme_key')->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('settings_json')->nullable();
            $table->json('shipping_json')->nullable();
            $table->string('payment_provider')->nullable();
            $table->json('payment_config_json')->nullable();
            $table->string('currency_code')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('source')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('customer_address')->nullable();
            $table->json('items_json')->nullable();
            $table->integer('total_minor')->default(0);
            $table->integer('shipping_minor')->default(0);
            $table->integer('discount_minor')->default(0);
            $table->string('coupon_code')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('rto_score')->nullable();
            $table->string('rto_band')->nullable();
            $table->string('status')->nullable();
            $table->text('payment_link')->nullable();
            $table->text('notes')->nullable();
            $table->string('recovery_token')->nullable();
            $table->unsignedBigInteger('wa_message_id')->nullable();
            $table->unsignedBigInteger('storefront_id')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('provider')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('direction')->nullable();
            $table->text('to_number')->nullable();
            $table->text('from_number')->nullable();
            $table->longText('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_type')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->nullable();
            $table->text('failure_reason')->nullable();
            $table->boolean('pinned')->default(false);
            $table->boolean('starred')->default(false);
            $table->string('reaction')->nullable();
            $table->integer('quality_score')->nullable();
            $table->string('quality_note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspace_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('provider_config_id')->nullable();
            $table->string('config_name')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('country')->nullable();
            $table->string('currency')->nullable();
            $table->string('merchant_category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->longText('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('provider_config_id')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->string('twilio_content_sid')->nullable();
            $table->string('channel')->nullable();
            $table->string('meta_status')->nullable();
            $table->string('quality_score')->nullable();
            $table->string('template_name')->nullable();
            $table->string('category')->nullable();
            $table->string('meta_category')->nullable();
            $table->string('template_type')->nullable();
            $table->text('header')->nullable();
            $table->json('header_location')->nullable();
            $table->text('template_body')->nullable();
            $table->text('footer')->nullable();
            $table->json('buttons')->nullable();
            $table->json('carousel_data')->nullable();
            $table->json('variable_map')->nullable();
            $table->string('attachment_type')->nullable();
            $table->string('attachment_file')->nullable();
            $table->string('language')->nullable();
            $table->string('parameter_format')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('wa_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_product_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('text')->nullable();
            $table->string('tone')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();
            $table->boolean('dismissible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function request(?object $user = null, array $payload = [], string $method = 'GET'): Request
    {
        $uri = $method === 'GET' && $payload ? ('/?' . http_build_query($payload)) : '/';
        $request = Request::create($uri, $method, $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function makeStorefront(User $user, array $overrides = []): WaStorefront
    {
        return WaStorefront::query()->create(array_merge([
            'workspace_id' => $user->current_workspace_id,
            'shop_name' => 'Zuri Beauty Store',
            'slug' => 'zuri-beauty-store',
            'theme_key' => WaStorefront::DEFAULT_THEME,
            'enabled' => true,
            'payment_provider' => 'manual_instructions',
            'payment_config_json' => [],
            'currency_code' => 'KES',
        ], $overrides));
    }

    private function makeOrder(User $user, WaStorefront $storefront, array $overrides = []): WaOrder
    {
        return WaOrder::withoutEvents(fn () => WaOrder::query()->create(array_merge([
            'workspace_id' => $user->current_workspace_id,
            'source' => 'storefront',
            'customer_phone' => '254700000010',
            'customer_name' => 'Customer',
            'items_json' => [],
            'total_minor' => 250000,
            'shipping_minor' => 0,
            'discount_minor' => 0,
            'currency_code' => 'KES',
            'payment_method' => 'manual',
            'status' => 'pending',
            'payment_link' => null,
            'storefront_id' => $storefront->id,
            'meta_json' => [],
        ], $overrides)));
    }
}
