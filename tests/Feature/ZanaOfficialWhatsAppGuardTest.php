<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\App\DeviceController;
use App\Http\Controllers\Api\App\GroupController;
use App\Services\WhatsAppDispatcher;
use App\Services\WorkspaceEngine;
use App\Support\ZanaWhatsAppPolicy;
use Illuminate\Http\Request;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class ZanaOfficialWhatsAppGuardTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        config(['zana.allow_unofficial_whatsapp' => false]);
    }

    private function apiRequest(?object $user = null, array $payload = [], string $method = 'POST'): Request
    {
        $request = Request::create('/', $method, $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_waba_remains_allowed_when_unofficial_providers_are_disabled(): void
    {
        \App\Models\SystemSetting::set('allowed_send_methods', ['baileys', 'waba'], 'json');
        \App\Models\SystemSetting::set('default_send_method', 'baileys', 'string');

        $this->assertTrue(ZanaWhatsAppPolicy::allows('waba'));
        $this->assertFalse(ZanaWhatsAppPolicy::allows('baileys'));
        $this->assertSame(['waba'], ZanaWhatsAppPolicy::filterAllowedProviders(['baileys', 'waba']));
        $this->assertSame('waba', WorkspaceEngine::platformDefault());
    }

    public function test_baileys_is_removed_from_allowed_provider_sets_when_unofficial_is_disabled(): void
    {
        $this->assertSame(
            ['waba', 'twilio'],
            ZanaWhatsAppPolicy::filterAllowedProviders(['baileys', 'waba', 'twilio'])
        );
    }

    public function test_stale_baileys_provider_cannot_dispatch_a_message_when_blocked(): void
    {
        $result = app(WhatsAppDispatcher::class)->sendRaw([
            'provider' => 'baileys',
            'workspace_id' => 1,
            'to_number' => '254700000001',
            'body' => 'hello from stale provider',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('blocked', $result['platform']);
        $this->assertStringContainsString('disabled', $result['error'] ?? '');
    }

    public function test_manipulated_request_cannot_force_baileys_device_creation(): void
    {
        $user = $this->makeWorkspaceUser();

        $response = app(DeviceController::class)->store($this->apiRequest($user, [
            'device_name' => 'Unofficial Device',
            'country_code' => '254',
            'phone_number' => '700000001',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('disabled', (string) ($response->getData(true)['message'] ?? ''));
    }

    public function test_group_controller_middleware_blocks_baileys_only_group_features_when_unofficial_is_disabled(): void
    {
        $user = $this->makeWorkspaceUser();
        $response = app(GroupController::class)->create($this->apiRequest($user, [
            'subject' => 'Blocked Group',
            'participants' => ['254700000001'],
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('disabled', (string) ($response->getData(true)['message'] ?? ''));
    }

    public function test_enabling_the_explicit_flag_restores_legacy_unofficial_behavior(): void
    {
        config(['zana.allow_unofficial_whatsapp' => true]);

        $this->assertTrue(ZanaWhatsAppPolicy::allows('baileys'));

        $result = app(WhatsAppDispatcher::class)->sendRaw([
            'provider' => 'baileys',
            'to_number' => '254700000001',
            'body' => 'legacy unofficial path',
        ]);

        $this->assertNotSame('blocked', $result['platform'] ?? null);
    }
}
