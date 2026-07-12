<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\App\TemplateController as AppTemplateController;
use App\Http\Requests\Api\V1\Template\StoreTemplateRequest;
use App\Http\Requests\Api\V1\Template\UpdateTemplateRequest;
use App\Http\Resources\Api\V1\TemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Templates — manage the workspace's WhatsApp message templates.
 *
 * Reuses the tested mobile-app pipeline (App\Http\Controllers\Api\App\
 * TemplateController → WaTemplate, workspace-scoped) and re-wraps every
 * result in the public { data } / { error } envelope.
 */
class TemplateController extends V1Controller
{
    /** GET /api/v1/templates — list the workspace's templates. */
    public function index(Request $request): JsonResponse
    {
        $internal = Request::create('/api/app/get-templates', 'GET');
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppTemplateController::class)->index($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('list_failed', $payload['message'] ?? 'Templates could not be listed.', 422);
        }

        $items = collect($payload['templates'] ?? [])
            ->map(fn ($t) => (new TemplateResource($t))->resolve())
            ->values();

        return $this->ok($items, ['count' => $items->count()]);
    }

    /** GET /api/v1/templates/categories — the Meta category list. */
    public function categories(): JsonResponse
    {
        $payload = app(AppTemplateController::class)->categories()->getData(true);

        return $this->ok($payload['categories'] ?? []);
    }

    /** POST /api/v1/templates — create a template. */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $internal = Request::create('/api/app/templates-store', 'POST', $this->forwardFields($request));
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppTemplateController::class)->store($internal)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail(
                'create_failed',
                $payload['message'] ?? 'Template could not be created.',
                422,
                (array) ($payload['errors'] ?? [])
            );
        }

        return $this->created((new TemplateResource($payload['data'] ?? []))->resolve());
    }

    /** GET /api/v1/templates/{id} — single template detail. */
    public function show(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/templates/' . $id, 'GET');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppTemplateController::class)->show($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Template not found.', 404);
        }

        return $this->ok((new TemplateResource($payload['data'] ?? []))->resolve());
    }

    /** PUT /api/v1/templates/{id} — update a template. */
    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        $internal = Request::create('/api/app/templates/' . $id, 'PUT', $this->forwardFields($request));
        $internal->setUserResolver(fn () => $request->user());

        $payload = app(AppTemplateController::class)->update($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            $status = isset($payload['errors']) ? 422 : 404;
            return $this->fail(
                $status === 422 ? 'update_failed' : 'not_found',
                $payload['message'] ?? 'Template could not be updated.',
                $status,
                (array) ($payload['errors'] ?? [])
            );
        }

        return $this->ok((new TemplateResource($payload['data'] ?? []))->resolve());
    }

    /**
     * The field set forwarded to the mobile-app controller for store + update.
     * Includes the LOCATION header (flat latitude/longitude/location_name/
     * location_address) so location templates are reachable via the public API
     * — the App controller's collectLocation() reads exactly these keys.
     *
     * Note: file attachments (image/video/document) can't be uploaded through
     * this internal sub-request and are intentionally out of scope here; create
     * media templates in the dashboard.
     */
    private function forwardFields(Request $request): array
    {
        // Forward ONLY the keys the caller actually sent. Using input() for
        // every field would turn an omitted field into an explicit null, which
        // on a partial update overwrites the stored value (e.g. nulling a
        // NOT-NULL `language` column). only() omits absent keys so the App
        // controller's "keep existing value" defaults apply.
        return $request->only([
            'template_name', 'template_type', 'category', 'header',
            'template_body', 'footer', 'language', 'buttons', 'quick_replies',
            'carousel_data',
            // LOCATION header pin — folded into header_location by the App layer.
            'latitude', 'longitude', 'location_name', 'location_address',
        ]);
    }

    /** DELETE /api/v1/templates/{id} — delete a template. */
    public function destroy(int $id): JsonResponse
    {
        $internal = Request::create('/api/app/templates/' . $id, 'DELETE');
        $internal->setUserResolver(fn () => request()->user());

        $payload = app(AppTemplateController::class)->destroy($internal, $id)->getData(true);

        if (($payload['success'] ?? false) !== true) {
            return $this->fail('not_found', $payload['message'] ?? 'Template not found.', 404);
        }

        return $this->ok(['deleted' => true, 'message' => $payload['message'] ?? 'Template deleted successfully']);
    }

    /**
     * POST /api/v1/templates/{id}/send — send one approved WhatsApp template to a
     * single recipient with your own variable values (WABA Cloud). This is the
     * single message + variables send that the list/broadcast endpoints don't cover.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            // Recipient phone in international format — digits or +E.164 (e.g. 919812345678). Required.
            'to'                 => 'required|string|max:32',
            // BODY variables, positional -> {{1}}, {{2}}, ... Send a list ["John","12345"]
            // or a 1-indexed map {"1":"John","2":"12345"}. Omit if the body has no variables.
            'body'               => 'nullable|array',
            'body.*'             => 'nullable|string|max:1024',
            // Value for a {{1}} in a TEXT header. Only for text-header templates.
            'header_text'        => 'nullable|string|max:1024',
            // Public https URL for an IMAGE / VIDEO / DOCUMENT header. Only for media-header templates.
            'header_media_url'   => 'nullable|url|max:2048',
            // Dynamic button parameters, one object per variable button, e.g. [{"index":0,"sub_type":"url","value":"ORDER123"}].
            'buttons'            => 'nullable|array',
            // Zero-based position of the button in the template.
            'buttons.*.index'    => 'nullable|integer|min:0',
            // Button kind: "url" (dynamic URL suffix) or "quick_reply" (payload).
            'buttons.*.sub_type' => 'nullable|string|max:32',
            // The dynamic value — URL suffix or quick-reply payload.
            'buttons.*.value'    => 'nullable|string|max:2048',
        ]);

        $tpl = \App\Models\WaTemplate::query()
            ->forCurrentWorkspace()
            ->whereKey($id)
            ->first();
        if (!$tpl) {
            return $this->fail('template_not_found', 'Template not found in this workspace.', 404);
        }

        // Body vars → positional list. Accept a list OR a 1-indexed map so both
        // {"body":["John","12345"]} and {"body":{"1":"John","2":"12345"}} work.
        $bodyIn = $data['body'] ?? [];
        if (array_is_list($bodyIn)) {
            $body = array_map('strval', $bodyIn);
        } else {
            ksort($bodyIn, SORT_NUMERIC);
            $body = array_values(array_map('strval', $bodyIn));
        }

        $vars = [];
        if (!empty($body))                     $vars['body']             = $body;
        if (!empty($data['header_text']))      $vars['header']           = (string) $data['header_text'];
        if (!empty($data['header_media_url'])) $vars['header_media_url'] = (string) $data['header_media_url'];
        if (!empty($data['buttons'])) {
            $vars['buttons'] = array_values(array_map(fn ($b) => [
                'index'    => (int) ($b['index'] ?? 0),
                'sub_type' => (string) ($b['sub_type'] ?? 'url'),
                'value'    => (string) ($b['value'] ?? ''),
            ], $data['buttons']));
        }

        $result = app(\App\Services\Waba\TemplateSender::class)->send($tpl, (string) $data['to'], $vars);

        // Auto-capture the recipient as a contact (dedup by phone hash).
        \App\Models\Contact::rememberPhone($this->workspaceId(), $request->user()?->id, (string) $data['to']);

        if (($result['ok'] ?? false) !== true) {
            return $this->fail(
                $result['code'] ?? 'send_failed',
                $result['error'] ?? 'Template send failed.',
                422,
                ['template_id' => $tpl->id]
            );
        }

        return $this->created([
            'wamid'         => $result['wamid'] ?? null,
            'status'        => 'sent',
            'template_id'   => $tpl->id,
            'template_name' => $tpl->template_name,
            'to'            => preg_replace('/\D+/', '', (string) $data['to']),
        ]);
    }
}
