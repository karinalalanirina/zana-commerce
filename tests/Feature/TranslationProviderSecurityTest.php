<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TranslationProviderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class TranslationProviderSecurityTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    private const SEEDED_SECRET = 'zana-translation-secret-hidden';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
        File::ensureDirectoryExists(storage_path('logs'));
        File::put(storage_path('logs/laravel.log'), '');
    }

    private function request(?object $user = null, array $payload = [], string $method = 'GET'): Request
    {
        $request = Request::create('/', $method, $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_translation_provider_index_does_not_render_stored_secret_values(): void
    {
        $this->makeLibreTranslateProvider();

        $view = app(TranslationProviderController::class)->index();
        $html = $view->render();

        $this->assertStringContainsString('https://translate.zana.test', $html);
        $this->assertStringNotContainsString(self::SEEDED_SECRET, $html);
        $this->assertStringNotContainsString('value="zana-translation-secret-hidden"', $html);
        $this->assertStringContainsString('saved — leave blank to keep', $html);
    }

    public function test_translation_provider_blank_secret_keeps_existing_value(): void
    {
        $admin = $this->makeWorkspaceUser(admin: true);
        $provider = $this->makeLibreTranslateProvider();

        $response = app(TranslationProviderController::class)->update($this->request($admin, [
            'credentials' => [
                'endpoint' => 'https://translate-updated.zana.test',
                'api_key' => '',
            ],
        ], 'PATCH'), $provider->id);

        $this->assertSame(302, $response->getStatusCode());

        $fresh = $provider->fresh();
        $creds = $fresh->getDecryptedCredentials();
        $this->assertSame('https://translate-updated.zana.test', $creds['endpoint']);
        $this->assertSame(self::SEEDED_SECRET, $creds['api_key']);
    }
}
