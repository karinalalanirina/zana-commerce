<?php

namespace Tests\Feature;

use App\Http\Controllers\WaOrderController;
use App\Models\WaTemplate;
use App\Models\User;
use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Services\WhatsAppDispatcher;
use App\Services\Waba\TemplateSender;
use App\Support\ZanaManualPayment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
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
                'error' => '24-hour window closed',
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
