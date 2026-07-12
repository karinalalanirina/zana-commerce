<?php

namespace Tests\Feature;

use App\Http\Controllers\WhatsAppPayController;
use App\Models\User;
use App\Models\WaOrder;
use App\Models\WaStorefront;
use App\Support\ZanaAfricaPayments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class ZanaAfricaMerchantPaymentsTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        $this->extendCommerceSchema();
        Config::set('zana.hide_india_merchant_payments', true);
    }

    public function test_store_payments_page_shows_africa_fallback_for_non_india_workspace(): void
    {
        $user = $this->makeWorkspaceUser();
        $this->makeWabaConfig($user, ['phone_number' => '+254700000001']);
        $this->makeStorefront($user);

        $view = app(WhatsAppPayController::class)->index($this->request($user, [], 'GET', '/store/payments'));
        $html = $view->render();

        $this->assertStringContainsString('Africa launch path', $html);
        $this->assertStringContainsString('Open storefront payment setup', $html);
        $this->assertStringContainsString('merchant confirms', $html);
        $this->assertStringNotContainsString('Review and Pay', $html);
    }

    public function test_store_payments_post_redirects_back_to_orders_for_non_india_workspace(): void
    {
        $user = $this->makeWorkspaceUser();
        $this->makeWabaConfig($user, ['phone_number' => '+254700000001']);

        $response = app(WhatsAppPayController::class)->store($this->request($user, [
            'config_name' => 'Primary India Pay',
            'payment_type' => 'razorpay',
            'is_active' => 1,
        ], 'POST', '/store/payments'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/store/orders', (string) $response->headers->get('Location'));
    }

    public function test_africa_payment_helper_builds_manual_instruction_text_from_storefront_config(): void
    {
        $user = $this->makeWorkspaceUser();
        $storefront = $this->makeStorefront($user, [
            'shop_name' => 'Zuri Beauty Store',
            'payment_provider' => 'manual_instructions',
            'payment_config_json' => [
                'accepted_payment_methods_text' => 'M-Pesa, bank transfer, or a Paystack payment link',
                'mpesa_business_name' => 'Zuri Beauty Store',
                'mpesa_till_number' => '123456',
                'mpesa_paybill_number' => '400200',
                'payment_reference_format' => 'ORDER-{order_id}',
                'bank_transfer_instructions' => 'KCB Bank, Account 00123456789',
                'external_payment_link' => 'https://paystack.com/pay/zuri-order',
            ],
        ]);

        $order = $this->makeOrder($user, $storefront, [
            'customer_name' => 'Amina',
            'total_minor' => 125000,
            'currency_code' => 'KES',
        ]);

        $instructions = ZanaAfricaPayments::instructionsText($storefront, $order);

        $this->assertNotNull($instructions);
        $this->assertStringContainsString('Order #' . $order->id . ' is awaiting payment', $instructions);
        $this->assertStringContainsString('M-Pesa business name: Zuri Beauty Store.', $instructions);
        $this->assertStringContainsString('Till number: 123456.', $instructions);
        $this->assertStringContainsString('Paybill number: 400200.', $instructions);
        $this->assertStringContainsString('Use reference: ORDER-{order_id}.', $instructions);
        $this->assertStringContainsString('Bank transfer instructions: KCB Bank, Account 00123456789', $instructions);
        $this->assertStringContainsString('Payment link: https://paystack.com/pay/zuri-order', $instructions);
    }

    public function test_africa_payment_helper_uses_visible_fallback_when_storefront_is_not_configured(): void
    {
        $instructions = ZanaAfricaPayments::instructionsText(new WaStorefront([
            'payment_config_json' => [],
        ]));

        $this->assertNotNull($instructions);
        $this->assertStringContainsString('Accepted payment methods:', $instructions);
        $this->assertStringContainsString('M-Pesa, bank transfer, manual payment link, or cash on delivery', $instructions);
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

    private function request(?object $user = null, array $payload = [], string $method = 'GET', string $uri = '/'): Request
    {
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
            'total_minor' => 10000,
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
