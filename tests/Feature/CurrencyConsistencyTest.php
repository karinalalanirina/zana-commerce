<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\SystemSetting;
use App\Models\WaProduct;
use App\Models\WaStorefront;
use App\Services\Storefront\StorefrontCheckoutService;
use App\Services\WhatsAppCatalog\CatalogSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class CurrencyConsistencyTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        $this->buildCommerceSchema();
        $this->seedCurrencies();
    }

    public function test_plan_paywall_uses_platform_billing_currency_instead_of_package_dollar_symbol(): void
    {
        SystemSetting::set('default_currency', 'NGN', 'string');

        $user = $this->makeWorkspaceUser();
        $currentPlan = Package::query()->create([
            'pname' => 'Starter',
            'status' => true,
            'sort_order' => 0,
            'plan_amount' => 0,
            'currency' => 'USD',
            'free' => true,
            'is_custom_quote' => false,
            'access_analytics' => false,
        ]);
        $package = Package::query()->create([
            'pname' => 'Growth',
            'status' => true,
            'sort_order' => 1,
            'plan_amount' => 10,
            'offer_price' => null,
            'currency' => 'USD',
            'free' => false,
            'is_custom_quote' => false,
            'access_analytics' => true,
        ]);
        $user->currentWorkspace->forceFill(['plan' => (string) $currentPlan->id])->save();
        $user = $user->fresh();

        Auth::login($user);
        view()->share('planPaywall', [
            'feature' => 'access_analytics',
            'label' => 'Analytics',
        ]);

        $html = view('components.plan-paywall')->render();

        $this->assertStringContainsString('₦', $html);
        $this->assertStringNotContainsString('$10', $html);
        $this->assertStringContainsString($package->pname, $html);
    }

    public function test_storefront_prices_and_quotes_use_storefront_currency_even_when_product_is_saved_in_usd(): void
    {
        SystemSetting::set('default_currency', 'NGN', 'string');
        SystemSetting::set('catalog_default_currency', 'KES', 'string');

        $user = $this->makeWorkspaceUser();
        $user->currentWorkspace->forceFill(['currency' => 'KES'])->save();
        $user = $user->fresh();
        $storefront = WaStorefront::query()->create([
            'workspace_id' => $user->current_workspace_id,
            'shop_name' => 'Zuri Beauty Store',
            'slug' => 'zuri-beauty-store',
            'theme_key' => WaStorefront::DEFAULT_THEME,
            'enabled' => true,
            'currency_code' => 'KES',
            'shipping_json' => ['flat_minor' => 5000],
        ]);

        $product = null;
        CatalogSyncService::withoutAutoSync(function () use (&$product, $user) {
            $product = WaProduct::query()->create([
                'workspace_id' => $user->current_workspace_id,
                'user_id' => $user->id,
                'name' => 'Face Serum',
                'slug' => 'face-serum',
                'price_minor' => 2500,
                'compare_price_minor' => 3000,
                'currency_code' => 'USD',
                'in_stock' => true,
                'status' => 'active',
            ]);
        });

        $this->assertSame(325000, $product->storefrontPriceMinor($storefront));
        $this->assertSame('KSh 3,250', $product->storefrontPriceDisplay($storefront));
        $this->assertSame('KSh 3,900', $product->storefrontComparePriceDisplay($storefront));

        $quote = app(StorefrontCheckoutService::class)->quote($storefront, [
            ['id' => $product->id, 'qty' => 2],
        ]);

        $this->assertSame(650000, $quote['subtotal']);
        $this->assertSame(5000, $quote['shipping']);
        $this->assertSame(655000, $quote['total']);

        $order = app(StorefrontCheckoutService::class)->placeOrder($storefront, [
            'name' => 'Jane Buyer',
            'phone' => '+254700123456',
            'items' => [['id' => $product->id, 'qty' => 1]],
        ]);

        $this->assertNotNull($order);
        $this->assertSame('KES', $order->currency_code);
        $this->assertSame(330000, $order->total_minor);
        $this->assertSame(325000, (int) ($order->items_json[0]['price_minor'] ?? 0));
    }

    private function buildCommerceSchema(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('pname')->nullable();
            $table->boolean('status')->default(false);
            $table->integer('sort_order')->default(0);
            $table->decimal('plan_amount', 12, 2)->default(0);
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->boolean('free')->default(false);
            $table->boolean('is_custom_quote')->default(false);
            $table->boolean('access_analytics')->default(false);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('plan')->nullable();
        });

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

        Schema::create('wa_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('storefront_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->longText('body_html')->nullable();
            $table->integer('price_minor');
            $table->integer('compare_price_minor')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('image_url')->nullable();
            $table->json('gallery_json')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_qty')->nullable();
            $table->integer('reserved_qty')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status')->nullable();
            $table->integer('weight_grams')->nullable();
            $table->string('category')->nullable();
            $table->json('tags_json')->nullable();
            $table->json('aliases_json')->nullable();
            $table->string('availability')->nullable();
            $table->string('condition')->nullable();
            $table->string('brand')->nullable();
            $table->string('product_url')->nullable();
            $table->string('google_product_category')->nullable();
            $table->string('meta_retailer_id')->nullable();
            $table->string('meta_sync_status')->nullable();
            $table->timestamp('meta_synced_at')->nullable();
            $table->string('meta_batch_handle')->nullable();
            $table->text('meta_last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wa_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('storefront_id')->nullable();
            $table->string('code')->nullable();
            $table->boolean('active')->default(false);
            $table->string('type')->nullable();
            $table->integer('amount_minor')->nullable();
            $table->decimal('percent_off', 8, 2)->nullable();
            $table->boolean('free_shipping')->default(false);
            $table->integer('minimum_subtotal_minor')->nullable();
            $table->integer('used_count')->default(0);
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
            $table->decimal('rto_score', 8, 2)->nullable();
            $table->string('rto_band')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('storefront_id')->nullable();
            $table->string('recovery_token')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('storefront_cart_recoveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('storefront_id')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->json('items_json')->nullable();
            $table->integer('subtotal_minor')->default(0);
            $table->string('currency_code')->nullable();
            $table->json('scheduled_ids')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    private function seedCurrencies(): void
    {
        foreach ([
            [
                'name' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'precision' => 2,
                'exchange_rate' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kenyan Shilling',
                'code' => 'KES',
                'symbol' => 'KSh ',
                'precision' => 2,
                'exchange_rate' => 130,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nigerian Naira',
                'code' => 'NGN',
                'symbol' => '₦',
                'precision' => 2,
                'exchange_rate' => 1500,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ] as $currency) {
            \App\Models\Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
