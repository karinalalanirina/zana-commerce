<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminPagesController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Package;
use App\Models\SystemSetting;
use App\Support\ZanaPlatformBillingCurrency;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class PlatformBillingCurrencyTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        $this->extendBillingSchema();
        $this->seedLookupTables();
    }

    public function test_admin_can_save_platform_billing_currency_and_it_persists_on_reload(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);

        $response = app(AdminPagesController::class)->settingGeneralUpdate(
            $this->controllerRequest($admin, [
                'app_name' => 'Zana',
                'default_currency' => 'ngn',
            ], 'PATCH')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('NGN', SystemSetting::get('default_currency'));
        $this->assertSame('string', SystemSetting::query()->where('key', 'default_currency')->value('type'));

        $view = app(AdminPagesController::class)->settingGeneral();
        $settings = $view->getData()['settings'] ?? [];
        $this->assertSame('NGN', $settings['default_currency'] ?? null);
    }

    public function test_non_admin_cannot_change_platform_billing_currency(): void
    {
        $user = $this->makeWorkspaceUser();
        SystemSetting::set('default_currency', 'USD', 'string');

        $request = $this->controllerRequest($user, [
            'app_name' => 'Zana',
            'default_currency' => 'NGN',
        ], 'PATCH');

        try {
            app(EnsureUserIsAdmin::class)->handle($request, static fn () => response('ok'));
            $this->fail('Expected non-admin billing currency update to be blocked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertSame('USD', SystemSetting::get('default_currency'));
    }

    public function test_package_pricing_uses_saved_platform_currency(): void
    {
        SystemSetting::set('default_currency', 'NGN', 'string');
        $package = $this->makePlanPackage([
            'plan_amount' => 10,
            'currency' => 'USD',
        ]);

        $display = $package->price_display;

        $this->assertStringContainsString('₦', $display);
        $this->assertStringNotContainsString('$', $display);
    }

    public function test_package_pricing_fallback_still_shows_explicit_currency_when_catalog_row_is_missing(): void
    {
        SystemSetting::set('default_currency', 'BWP', 'string');
        $package = $this->makePlanPackage([
            'plan_amount' => 10,
            'currency' => 'USD',
        ]);

        $display = ZanaPlatformBillingCurrency::formatPackageAmount($package);

        $this->assertStringContainsString('BWP ', $display);
        $this->assertMatchesRegularExpression('/BWP\s+\d/', $display);
    }

    public function test_legacy_malformed_default_currency_row_is_recovered_as_string(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'default_currency'],
            ['type' => 'int', 'value' => 'NGN', 'description' => 'legacy malformed row']
        );

        $this->assertSame('NGN', SystemSetting::get('default_currency'));
        $this->assertSame('NGN', ZanaPlatformBillingCurrency::code());
        $this->assertSame('string', SystemSetting::query()->where('key', 'default_currency')->value('type'));
    }

    public function test_currency_admin_default_action_writes_default_currency_as_string(): void
    {
        $response = app(CurrencyController::class)->setDefault(
            $this->controllerRequest(null, ['code' => 'KES'], 'POST')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('KES', SystemSetting::get('default_currency'));
        $this->assertSame('string', SystemSetting::query()->where('key', 'default_currency')->value('type'));
    }

    private function controllerRequest(?object $user = null, array $payload = [], string $method = 'GET'): Request
    {
        $request = Request::create('/', $method, $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function extendBillingSchema(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 12)->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->string('plan_id')->nullable();
            $table->string('pname')->nullable();
            $table->string('plan_unit')->nullable();
            $table->integer('plan_duration')->nullable();
            $table->decimal('plan_amount', 12, 2)->default(0);
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->boolean('free')->default(false);
            $table->boolean('lifetime')->default(false);
            $table->boolean('status')->default(false);
            $table->boolean('is_custom_quote')->default(false);
            $table->integer('sort_order')->default(0);
        });
    }

    private function seedLookupTables(): void
    {
        \App\Models\Language::query()->create([
            'name' => 'English',
            'code' => 'en',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        \App\Models\Currency::query()->updateOrCreate(
            ['code' => 'NGN'],
            [
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'precision' => 2,
                'exchange_rate' => 1500,
                'is_active' => true,
            ]
        );
        \App\Models\Currency::query()->updateOrCreate(
            ['code' => 'KES'],
            [
                'name' => 'Kenyan Shilling',
                'symbol' => 'KSh',
                'precision' => 2,
                'exchange_rate' => 130,
                'is_active' => true,
            ]
        );
    }

    private function makePlanPackage(array $overrides = []): Package
    {
        return Package::query()->create(array_merge([
            'type' => Package::TYPE_PLAN,
            'plan_id' => 'growth',
            'pname' => 'Growth',
            'plan_unit' => 'month',
            'plan_duration' => 1,
            'plan_amount' => 0,
            'offer_price' => null,
            'currency' => 'USD',
            'free' => false,
            'lifetime' => false,
            'status' => true,
            'is_custom_quote' => false,
            'sort_order' => 1,
        ], $overrides));
    }
}
