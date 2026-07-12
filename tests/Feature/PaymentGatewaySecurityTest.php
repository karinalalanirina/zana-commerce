<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\App\BillingController;
use App\Http\Controllers\Api\App\PaymentGatewayController;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class PaymentGatewaySecurityTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    private const SEEDED_SECRET = 'zana-test-secret-never-expose-7d19f3';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        File::ensureDirectoryExists(storage_path('logs'));
        File::put(storage_path('logs/laravel.log'), '');
    }

    private function apiRequest(?object $user = null, array $payload = [], string $method = 'GET'): Request
    {
        $request = Request::create('/', $method, $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_admin_gateway_endpoint_rejects_guests(): void
    {
        $gateway = $this->makeStripeGateway();

        $response = app(PaymentGatewayController::class)->show($this->apiRequest(), $gateway->id);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_non_admin_workspace_user_cannot_retrieve_admin_gateway_configuration(): void
    {
        $user = $this->makeWorkspaceUser();
        $gateway = $this->makeStripeGateway();

        $response = app(PaymentGatewayController::class)->show($this->apiRequest($user), $gateway->id);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_authorized_billing_response_contains_only_public_gateway_fields(): void
    {
        $this->makeStripeGateway();

        $response = app(BillingController::class)->paymentGatewaySettings($this->apiRequest());
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['status']);
        $this->assertSame('stripe', $payload['gateways'][0]['slug']);
        $this->assertSame('pk_test_zana_public', $payload['gateways'][0]['public_keys']['publishable_key']);
        $this->assertArrayNotHasKey('credentials', $payload['gateways'][0]);
        $this->assertArrayNotHasKey('credentials_json', $payload['gateways'][0]);

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString('secret_key', $body);
        $this->assertStringNotContainsString('zana-webhook-secret-hidden', $body);
        $this->assertStringNotContainsString(self::SEEDED_SECRET, $body);
        $this->assertStringContainsString('stripe_publishable_key', $body);
    }

    public function test_admin_gateway_endpoint_does_not_return_stored_secret_values(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);
        $gateway = $this->makeStripeGateway();

        $response = app(PaymentGatewayController::class)->show($this->apiRequest($admin), $gateway->id);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('stripe', $payload['data']['slug']);
        $this->assertSame('pk_test_zana_public', $payload['data']['public_values']['publishable_key']);
        $this->assertTrue($payload['data']['credentials_set']['secret_key']);
        $this->assertArrayNotHasKey('credentials_plain', $payload['data']);

        $body = $response->getContent() ?: '';
        $this->assertStringNotContainsString(self::SEEDED_SECRET, $body);
        $this->assertStringNotContainsString('zana-webhook-secret-hidden', $body);
    }

    public function test_server_side_gateway_credentials_remain_accessible_after_sanitized_response(): void
    {
        $gateway = $this->makeStripeGateway();

        app(BillingController::class)->paymentGatewaySettings($this->apiRequest());

        $this->assertSame(
            self::SEEDED_SECRET,
            PaymentGateway::findOrFail($gateway->id)->getCredential('secret_key')
        );
    }

    public function test_updating_non_secret_setting_does_not_erase_stored_secret(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);
        $gateway = $this->makeStripeGateway();

        $response = app(PaymentGatewayController::class)->update($this->apiRequest($admin, [
            'mode' => 'sandbox',
            'credentials' => [
                'publishable_key' => 'pk_test_updated',
            ],
        ], 'PATCH'), $gateway->id);

        $this->assertSame(200, $response->getStatusCode());

        $fresh = PaymentGateway::findOrFail($gateway->id);
        $this->assertSame('pk_test_updated', $fresh->getCredential('publishable_key'));
        $this->assertSame(self::SEEDED_SECRET, $fresh->getCredential('secret_key'));
    }

    public function test_submitting_blank_or_omitted_secret_does_not_erase_stored_secret(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);
        $gateway = $this->makeStripeGateway();

        $response = app(PaymentGatewayController::class)->update($this->apiRequest($admin, [
            'credentials' => [
                'secret_key' => '',
                'publishable_key' => 'pk_test_blank_secret_keep',
            ],
        ], 'PATCH'), $gateway->id);

        $this->assertSame(200, $response->getStatusCode());

        $fresh = PaymentGateway::findOrFail($gateway->id);
        $this->assertSame('pk_test_blank_secret_keep', $fresh->getCredential('publishable_key'));
        $this->assertSame(self::SEEDED_SECRET, $fresh->getCredential('secret_key'));
    }

    public function test_submitting_deliberate_replacement_secret_updates_it_correctly(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);
        $gateway = $this->makeStripeGateway();

        $response = app(PaymentGatewayController::class)->update($this->apiRequest($admin, [
            'credentials' => [
                'secret_key' => 'zana-test-secret-replaced',
            ],
        ], 'PATCH'), $gateway->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'zana-test-secret-replaced',
            PaymentGateway::findOrFail($gateway->id)->getCredential('secret_key')
        );
    }

    public function test_logs_do_not_contain_seeded_secret_value(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);
        $gateway = $this->makeStripeGateway();

        app(BillingController::class)->paymentGatewaySettings($this->apiRequest());
        app(PaymentGatewayController::class)->show($this->apiRequest($admin), $gateway->id);

        $log = File::get(storage_path('logs/laravel.log'));
        $this->assertStringNotContainsString(self::SEEDED_SECRET, $log);
    }
}
