<?php

namespace App\Services\Waba;

use App\Models\SystemSetting;
use App\Models\WaProviderConfig;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Meta Cloud `message_templates` HTTP wrapper.
 *
 * Single-purpose: every Graph call our template flow needs lives
 * here so that the controller / job / dispatcher never builds a
 * URL or sets a header themselves.
 *
 * Errors are raised as RuntimeException with a hint string the
 * caller can show inline; the raw Meta response is also attached
 * via getCode() (HTTP status) and an `$lastError` array on the
 * client for debugging.
 *
 *   $client = new TemplateClient($wabaCfg);
 *   $resp   = $client->submit($payload);   // ['id' => '...', 'status' => 'PENDING']
 *   $state  = $client->fetch($resp['id']); // poll
 */
class TemplateClient
{
    public array $lastError = [];

    private string $base;
    private string $token;
    private string $wabaId;
    private string $appId;

    public function __construct(public WaProviderConfig $cfg)
    {
        $creds = $cfg->creds();
        $meta  = is_array($cfg->meta_json) ? $cfg->meta_json : [];

        $version = (string) SystemSetting::get('waba_graph_api_version', 'v23.0');
        $this->base   = 'https://graph.facebook.com/' . ltrim($version, '/');
        $this->token  = (string) ($creds['access_token']      ?? '');
        $this->wabaId = (string) ($meta['waba_id']            ?? $creds['waba_id']        ?? '');
        $this->appId  = (string) ($creds['app_id']            ?? '');

        if ($this->token === '' || $this->wabaId === '') {
            throw new RuntimeException('WABA config is missing access_token or waba_id.');
        }
    }

    /**
     * POST /{WABA_ID}/message_templates
     *
     * @param  array  $payload  from TemplatePayloadBuilder::build()
     * @return array            ['id' => 'meta_template_id', 'status' => 'PENDING', 'category' => 'UTILITY']
     */
    public function submit(array $payload): array
    {
        $resp = $this->http()->post("{$this->base}/{$this->wabaId}/message_templates", $payload);
        $this->stash($resp, 'submit', $payload);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp), $resp->status());
        }

        $body = $resp->json();
        return [
            'id'       => (string) ($body['id']       ?? ''),
            'status'   => (string) ($body['status']   ?? 'PENDING'),
            'category' => (string) ($body['category'] ?? ''),
        ];
    }

    /** GET /{TEMPLATE_ID}?fields=… — single template state refresh. */
    public function fetch(string $metaTemplateId): array
    {
        $resp = $this->http()->get("{$this->base}/{$metaTemplateId}", [
            'fields' => 'id,name,status,category,language,quality_score,rejection_reason,components',
        ]);
        $this->stash($resp, 'fetch', ['id' => $metaTemplateId]);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp), $resp->status());
        }
        return (array) $resp->json();
    }

    /** GET /{WABA_ID}/message_templates — paginated list. */
    public function list(?string $after = null, int $limit = 200): array
    {
        $params = [
            // `components` + `parameter_format` are needed so the importer can
            // reconstruct the local row (header/body/footer/buttons) from a
            // template that was created directly in Meta Business Manager.
            'fields' => 'id,name,status,category,language,quality_score,rejection_reason,parameter_format,components',
            'limit'  => $limit,
        ];
        if ($after) $params['after'] = $after;

        $resp = $this->http()->get("{$this->base}/{$this->wabaId}/message_templates", $params);
        $this->stash($resp, 'list', $params);

        if (!$resp->successful()) {
            throw new RuntimeException($this->errorHint($resp), $resp->status());
        }
        return (array) $resp->json();
    }

    /** DELETE /{WABA_ID}/message_templates?name=… — Meta delete-by-name. */
    public function deleteByName(string $name): bool
    {
        $resp = $this->http()->delete("{$this->base}/{$this->wabaId}/message_templates", ['name' => $name]);
        $this->stash($resp, 'delete', ['name' => $name]);
        return $resp->successful();
    }

    /**
     * POST /{APP_ID}/uploads — start a resumable upload session.
     * Then POST /{UPLOAD_SESSION_ID} with the binary body. Returns
     * the `header_handle` (Meta's `h` field) used in template create
     * payloads as `example.header_handle[0]`.
     */
    public function uploadHeaderMedia(string $localPath, string $mime): string
    {
        if ($this->appId === '') {
            throw new RuntimeException('Cannot upload media — app_id missing from WABA credentials.');
        }
        if (!is_readable($localPath)) {
            throw new RuntimeException("File not readable: $localPath");
        }
        $bytes = filesize($localPath);

        // 1) Open session.
        $open = $this->http()->post("{$this->base}/{$this->appId}/uploads", [
            'file_length' => $bytes,
            'file_type'   => $mime,
            'file_name'   => basename($localPath),
        ]);
        $this->stash($open, 'upload_open', ['path' => $localPath, 'mime' => $mime]);
        if (!$open->successful()) {
            throw new RuntimeException($this->errorHint($open), $open->status());
        }
        $sessionId = (string) ($open->json('id') ?? '');
        if ($sessionId === '') {
            throw new RuntimeException('Meta did not return an upload session id.');
        }

        // 2) Upload bytes. Meta wants `OAuth {token}` (not `Bearer`) and
        // `file_offset: 0` on the binary POST.
        $upload = Http::withHeaders([
                'Authorization' => 'OAuth ' . $this->token,
                'file_offset'   => '0',
            ])
            ->withBody(file_get_contents($localPath), $mime)
            ->timeout(60)
            ->post("{$this->base}/{$sessionId}");
        $this->stash($upload, 'upload_body', ['session' => $sessionId]);

        if (!$upload->successful()) {
            throw new RuntimeException($this->errorHint($upload), $upload->status());
        }
        $handle = (string) ($upload->json('h') ?? '');
        if ($handle === '') {
            throw new RuntimeException('Meta upload finished but did not return a header_handle.');
        }
        return $handle;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function http()
    {
        return Http::withToken($this->token)->acceptJson()->timeout(30);
    }

    private function stash(Response $resp, string $op, array $context): void
    {
        $this->lastError = [
            'op'       => $op,
            'status'   => $resp->status(),
            'body'     => $resp->json() ?? $resp->body(),
            'context'  => $context,
        ];
    }

    /**
     * Translate Meta's typed error envelopes into a single
     * user-actionable sentence. Mirrors the DevicesController helper
     * but specialized for the template endpoint's common failures.
     */
    private function errorHint(Response $resp): string
    {
        $err     = (array) $resp->json('error', []);
        $code    = (int) ($err['code']          ?? 0);
        $sub     = (int) ($err['error_subcode'] ?? 0);
        $msg     = (string) ($err['message']    ?? 'Unknown Meta error.');

        // Token / permission issues
        if ($code === 190)                  return 'Meta token expired or invalid. Reconnect this WABA, then retry.';
        if ($code === 200 && $sub === 1349174) return 'Your Meta app is missing whatsapp_business_management permission. Regenerate the System User token with that scope.';

        // Template-specific known codes
        return match (true) {
            $code === 100   => "Bad parameter: $msg",
            $code === 132000 => "Template parameter mismatch: $msg",  // common on bad placeholder examples
            $code === 132001 => 'Template language not supported: ' . $msg,
            $code === 132005 => "Template not found by name. It may have been deleted or never approved.",
            $code === 132007 => "Template name already exists for a different language. Pick a different name.",
            $code === 192    => "Template exceeds your WABA's template quota. Delete old templates first.",
            default          => "Meta error $code" . ($sub ? "/$sub" : '') . ": $msg",
        };
    }
}
