<?php

namespace Tests\Feature;

use App\Http\Controllers\DevicesController;
use App\Http\Controllers\MessageHistoryController;
use App\Http\Controllers\SettingsTabsController;
use App\Http\Controllers\TeamInboxController;
use App\Services\UnifiedMessageStream;
use App\Services\WabaHealthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Concerns\BuildsZanaSecuritySchema;
use Tests\TestCase;

class TenantIsolationFocusedTest extends TestCase
{
    use BuildsZanaSecuritySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildZanaSecuritySchema();
    }

    private function request(string $method = 'GET', array $query = [], array $payload = []): Request
    {
        $uri = $query ? ('/?' . http_build_query($query)) : '/';
        return Request::create($uri, $method, $payload);
    }

    public function test_team_inbox_show_only_loads_conversations_from_current_workspace(): void
    {
        $zuri = $this->makeWorkspaceUser();
        $nairobi = $this->makeWorkspaceUser();

        $zuriConversation = $this->makeWorkspaceConversation($zuri, [
            'raw_jid' => '254700000101@s.whatsapp.net',
            'alt_jid' => '254700000101@c.us',
        ]);
        $this->makeInboxMessage($zuriConversation, ['body' => 'Zuri workspace message']);

        $nairobiConversation = $this->makeWorkspaceConversation($nairobi, [
            'raw_jid' => '254700000202@s.whatsapp.net',
            'alt_jid' => '254700000202@c.us',
        ]);
        $this->makeInboxMessage($nairobiConversation, ['body' => 'Nairobi workspace message']);

        $this->actingAs($zuri);
        $ownRequest = $this->request('GET', ['before' => 999999]);
        $ownRequest->setUserResolver(fn () => $zuri);
        $ownResponse = app(TeamInboxController::class)->show($ownRequest, $zuriConversation->id);
        $ownPayload = json_decode($ownResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $ownResponse->getStatusCode());
        $this->assertTrue($ownPayload['has_more'] === false);
        $this->assertCount(1, $ownPayload['messages']);
        $this->assertStringContainsString('Zuri workspace message', (string) ($ownPayload['messages'][0]['body'] ?? ''));

        $this->expectException(ModelNotFoundException::class);
        $crossRequest = $this->request('GET', ['before' => 999999]);
        $crossRequest->setUserResolver(fn () => $zuri);
        app(TeamInboxController::class)->show($crossRequest, $nairobiConversation->id);
    }

    public function test_workspace_exports_only_include_rows_from_current_workspace(): void
    {
        $zuri = $this->makeWorkspaceUser();
        $nairobi = $this->makeWorkspaceUser();

        $zuriContact = $this->makeWorkspaceContact($zuri, 'Zuri Customer', '+254700001001');
        $nairobiContact = $this->makeWorkspaceContact($nairobi, 'Nairobi Customer', '+254700002002');

        $zuriConversation = $this->makeWorkspaceConversation($zuri, [
            'title' => 'Zuri order thread',
            'channel' => 'whatsapp-zuri',
            'raw_jid' => '254700001001@s.whatsapp.net',
        ]);
        $nairobiConversation = $this->makeWorkspaceConversation($nairobi, [
            'title' => 'Nairobi order thread',
            'channel' => 'whatsapp-nairobi',
            'raw_jid' => '254700002002@s.whatsapp.net',
        ]);

        $zuriMessage = $this->makeInboxMessage($zuriConversation, ['body' => 'Zuri order follow-up']);
        $nairobiMessage = $this->makeInboxMessage($nairobiConversation, ['body' => 'Nairobi order follow-up']);

        $controller = app(SettingsTabsController::class);
        $this->actingAs($zuri);

        $contactsRequest = $this->request('GET');
        $contactsRequest->setUserResolver(fn () => $zuri);
        $conversationsRequest = $this->request('GET');
        $conversationsRequest->setUserResolver(fn () => $zuri);
        $messagesRequest = $this->request('GET');
        $messagesRequest->setUserResolver(fn () => $zuri);

        $contactsCsv = $this->streamedContent($controller->exportData($contactsRequest, 'contacts'));
        $conversationsCsv = $this->streamedContent($controller->exportData($conversationsRequest, 'conversations'));
        $messagesCsv = $this->streamedContent($controller->exportData($messagesRequest, 'messages'));

        $this->assertStringContainsString('Zuri Customer', $contactsCsv);
        $this->assertStringNotContainsString('Nairobi Customer', $contactsCsv);

        $this->assertStringContainsString('whatsapp-zuri', $conversationsCsv);
        $this->assertStringNotContainsString('whatsapp-nairobi', $conversationsCsv);

        $this->assertStringContainsString($zuriMessage->id . ',' . $zuriMessage->conversation_id . ',in,', $messagesCsv);
        $this->assertStringNotContainsString($nairobiMessage->id . ',' . $nairobiMessage->conversation_id . ',in,', $messagesCsv);
    }

    public function test_message_history_export_uses_current_workspace_scope(): void
    {
        $zuri = $this->makeWorkspaceUser();

        $stream = $this->mock(UnifiedMessageStream::class, function ($mock) use ($zuri) {
            $mock->shouldReceive('paginate')
                ->once()
                ->withArgs(function (array $filters) use ($zuri) {
                    return ($filters['workspace_id'] ?? null) === $zuri->current_workspace_id
                        && ($filters['page'] ?? null) === 1
                        && ($filters['per_page'] ?? null) === 10000;
                })
                ->andReturn(new LengthAwarePaginator([], 0, 10000, 1));
        });

        $this->actingAs($zuri);
        $request = $this->request('GET');
        $request->setUserResolver(fn () => $zuri);

        $response = (new MessageHistoryController($stream))->export($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_waba_health_json_is_scoped_to_current_workspace_config(): void
    {
        $zuri = $this->makeWorkspaceUser();
        $nairobi = $this->makeWorkspaceUser();

        $zuriConfig = $this->makeWabaConfig($zuri, ['display_label' => 'Zuri official WABA']);
        $nairobiConfig = $this->makeWabaConfig($nairobi, ['display_label' => 'Nairobi official WABA']);

        $this->mock(WabaHealthService::class, function ($mock) use ($zuriConfig) {
            $mock->shouldReceive('fetch')
                ->once()
                ->withArgs(fn ($cfg) => (int) $cfg->id === (int) $zuriConfig->id)
                ->andReturn([
                    'ok' => true,
                    'status' => 'connected',
                    'quality' => 'GREEN',
                ]);
        });

        $this->actingAs($zuri);
        $ownResponse = app(DevicesController::class)->wabaHealthJson($zuriConfig->id);
        $ownPayload = json_decode($ownResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $ownResponse->getStatusCode());
        $this->assertTrue((bool) ($ownPayload['ok'] ?? false));
        $this->assertSame('connected', $ownPayload['status'] ?? null);

        $this->expectException(ModelNotFoundException::class);
        app(DevicesController::class)->wabaHealthJson($nairobiConfig->id);
    }

    private function streamedContent($response): string
    {
        ob_start();
        $response->sendContent();
        return (string) ob_get_clean();
    }
}
