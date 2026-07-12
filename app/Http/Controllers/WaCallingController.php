<?php

namespace App\Http\Controllers;

use App\Models\WaCall;
use App\Models\WaCallPermission;
use App\Models\WaProviderConfig;
use App\Services\WaCalling\WaCallingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * User-facing endpoints for WhatsApp Cloud-API calling.
 *
 *   GET    /wa-calling/status                      — list workspace WABA numbers + state
 *   POST   /wa-calling/{id}/toggle                 — enable/disable calling on a WABA number
 *   POST   /wa-calling/calls/dial                  — start an outbound dial
 *   POST   /wa-calling/calls/{id}/accept           — operator answered (SDP answer in body)
 *   POST   /wa-calling/calls/{id}/pre-accept       — AI handoff buying time
 *   POST   /wa-calling/calls/{id}/reject           — decline an incoming call
 *   POST   /wa-calling/calls/{id}/terminate        — hang up an active call
 *   POST   /wa-calling/permission-request          — send a permission-request message
 *   GET    /wa-calling/pending                     — poll bridge for the team-inbox toast
 *
 * The webhook receiver (Meta → us) lives in WaCallingWebhookController.
 */
class WaCallingController extends Controller
{
    public function __construct(private readonly WaCallingService $svc) {}

    public function status(Request $request): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        $rows = WaProviderConfig::forWorkspace($wsId)
            ->where('provider', 'waba')
            ->get(['id', 'phone_number', 'display_label', 'status', 'calling_enabled', 'calling_enabled_at']);

        return response()->json([
            'ok'   => true,
            'rows' => $rows->map(fn ($r) => [
                'id'                 => $r->id,
                'phone_number'       => $r->phone_number,
                'display_label'      => $r->display_label,
                'connection_status'  => $r->status,
                'calling_enabled'    => (bool) $r->calling_enabled,
                'calling_enabled_at' => optional($r->calling_enabled_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $cfg = $this->cfg($request, $id);
        $data = $request->validate(['enable' => 'required|boolean']);

        try {
            $body = $data['enable'] ? $this->svc->enable($cfg) : $this->svc->disable($cfg);
            return response()->json([
                'ok'                 => true,
                'calling_enabled'    => $cfg->fresh()->calling_enabled,
                'calling_enabled_at' => optional($cfg->fresh()->calling_enabled_at)->toIso8601String(),
                'meta'               => $body,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /* ─────────────────── action endpoints (Phase 2/3) ────────────── */

    /**
     * Start a business-initiated call. Browser WebRTC peer generates
     * the SDP offer client-side; this endpoint forwards it to Meta
     * and returns the wa_calls row id so the browser knows what to
     * accept/reject/terminate against.
     */
    public function dial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'config_id'        => 'required|integer',
            'to'               => 'required|string|max:32',
            // 50 chars matches the JS guard — a real SDP starts with
            // v=0\r\no=- ... which is already ~30 chars before any media line.
            'sdp_offer'        => 'required|string|min:50',
            'contact_id'       => 'nullable|integer',
            'conversation_id'  => 'nullable|integer',
        ]);
        $cfg = $this->cfg($request, (int) $data['config_id']);

        try {
            $call = $this->svc->dialOutbound(
                $cfg, $data['to'], $data['sdp_offer'],
                $data['contact_id'] ?? null,
                $data['conversation_id'] ?? null,
            );
            return response()->json([
                'ok'           => true,
                'call_id'      => $call->id,
                'meta_call_id' => $call->meta_call_id,
                'correlation'  => $call->correlation_id,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Operator accepts an incoming call. Wrapped in a DB transaction
     * with lockForUpdate so concurrent accepts (two ops clicking
     * Accept on the same call) + the AI fallback timer can't trample
     * each other.
     *
     * Race scenarios this guards against:
     *   - Op-A accepts at t=8s, Op-B accepts at t=8.05s — Op-B gets 409
     *     and the JS surfaces "already accepted by another teammate".
     *   - AI fallback timer fires at t=15s while op-A's accept is mid-
     *     HTTP-to-Meta — the lockForUpdate makes the fallback wait, see
     *     status='connecting', and bail without overwriting handler_type.
     *
     * Required SDP body length matches the JS guard so a malformed call
     * (Meta sent connect without session.sdp) errors out before we POST.
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'sdp_answer' => ['required', 'string', 'min:50'],
        ]);
        $userId = (int) ($request->user()?->id ?? 0);
        $wsId   = (int) $request->user()->current_workspace_id;
        $sdpAnswer = $data['sdp_answer'];

        // Phase 1 (atomic) — claim the ringing slot. We return a sentinel
        // tuple from the closure so transient HTTP responses (404 / 409)
        // bubble out as proper JSON without needing to throw across the
        // transaction boundary — abort() inside a transaction throws
        // HttpResponseException, which doesn't match a Symfony
        // HttpException catch and would otherwise leak as a 500.
        $result = DB::transaction(function () use ($id, $wsId, $userId) {
            $row = WaCall::where('workspace_id', $wsId)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();
            if (!$row) {
                return ['status' => 'not_found'];
            }
            if ($row->status !== 'ringing') {
                return ['status' => 'conflict', 'state' => $row->status];
            }
            $row->forceFill([
                'status'          => 'connecting',
                'handler_type'    => 'operator',
                'handler_user_id' => $userId ?: null,
            ])->save();
            return ['status' => 'claimed', 'call' => $row];
        });

        if ($result['status'] === 'not_found') {
            return response()->json(['ok' => false, 'error' => 'call not found'], 404);
        }
        if ($result['status'] === 'conflict') {
            return response()->json([
                'ok'    => false,
                'error' => 'already accepted',
                'state' => $result['state'],
            ], 409);
        }
        $call = $result['call'];

        // Phase 1.5 — tell Node to close any AI bridge session it may
        // have opened for this call. The bridge starts immediately when
        // handleConnect fires, so by the time the operator clicks
        // Accept the bridge may have already POSTed pre_accept to Meta.
        // We close it BEFORE our own accept POST so we don't end up
        // racing two simultaneous accept actions against Meta.
        $this->closeNodeBridge($call->meta_call_id);

        // Phase 2 — POST to Meta. Outside the transaction so a slow
        // Graph API call doesn't lock other inbox/team-inbox queries.
        //
        // Meta's call-accept is a TWO-STEP handshake: pre_accept FIRST
        // (establishes the media connection), THEN accept. Sending accept
        // without a preceding pre_accept is rejected by the API and clips
        // the opening audio. The SDP answer must be byte-identical in both
        // steps, so we pass the same $sdpAnswer to each — exactly what the
        // Node AI bridge (node/services/wabaCallBridge.js) already does.
        try {
            $this->svc->preAccept($call, $sdpAnswer);
            // ~1s lets Meta bring the media path up before we accept, per
            // Meta's "flow media only after the 200 OK on accept" guidance.
            usleep(900_000);
            $body = $this->svc->acceptIncoming($call, $sdpAnswer);
            return response()->json(['ok' => true, 'meta' => $body]);
        } catch (\Throwable $e) {
            // Meta rejected (pre_accept or accept) — roll back the claim so
            // the fallback can still fire or another operator can retry.
            $call->forceFill([
                'status'          => 'ringing',
                'handler_type'    => null,
                'handler_user_id' => null,
            ])->save();
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Best-effort POST to Node /api/waba-call/terminate so an AI bridge
     * that's mid-session yields to the operator. Failures are swallowed
     * — the worst case is Node's accept races ours at Meta and one of
     * the two POSTs fails harmlessly.
     */
    private function closeNodeBridge(?string $metaCallId): void
    {
        if (!$metaCallId) return;
        $nodeUrl   = (string) \App\Models\SystemSetting::get('baileys_server_url', '');
        $nodeToken = node_token();
        if ($nodeUrl === '' || $nodeToken === '') return;
        try {
            \Illuminate\Support\Facades\Http::withHeaders(['X-Node-Token' => $nodeToken])
                ->timeout(3)
                ->acceptJson()
                ->post(rtrim($nodeUrl, '/') . '/api/waba-call/terminate', [
                    'meta_call_id' => $metaCallId,
                ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[WA-CALLING] node bridge close failed: ' . $e->getMessage());
        }
    }

    public function preAccept(Request $request, int $id): JsonResponse
    {
        $call = $this->call($request, $id);
        try { return response()->json(['ok' => true, 'meta' => $this->svc->preAccept($call)]); }
        catch (\Throwable $e) { return response()->json(['ok' => false, 'error' => $e->getMessage()], 422); }
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $call = $this->call($request, $id);
        $data = $request->validate(['reason' => 'nullable|string|max:64']);
        try { return response()->json(['ok' => true, 'meta' => $this->svc->reject($call, $data['reason'] ?? null)]); }
        catch (\Throwable $e) { return response()->json(['ok' => false, 'error' => $e->getMessage()], 422); }
    }

    public function terminate(Request $request, int $id): JsonResponse
    {
        $call = $this->call($request, $id);
        try { return response()->json(['ok' => true, 'meta' => $this->svc->terminate($call)]); }
        catch (\Throwable $e) { return response()->json(['ok' => false, 'error' => $e->getMessage()], 422); }
    }

    public function requestPermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'config_id' => 'required|integer',
            'to'        => 'required|string|max:32',
            'body'      => 'nullable|string|max:160',
        ]);
        $cfg = $this->cfg($request, (int) $data['config_id']);
        try {
            $body = $this->svc->requestPermission($cfg, $data['to'], $data['body'] ?? 'Can we give you a quick call?');
            return response()->json(['ok' => true, 'meta' => $body]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Poll bridge for the team-inbox incoming-call toast. Returns any
     * currently-ringing call rows in this workspace. The JS poll
     * already hits the inbox bootstrap every few seconds; this is the
     * equivalent for calls. No queue, no Reverb.
     */
    public function pending(Request $request): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        $calls = WaCall::query()
            ->where('workspace_id', $wsId)
            ->forCurrentEngine()
            ->where('status', 'ringing')
            ->where('started_at', '>=', now()->subMinutes(2))
            ->with('contact:id,name,first_name,mobile')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json([
            'ok'    => true,
            'calls' => $calls->map(fn ($c) => [
                'id'           => $c->id,
                'direction'    => $c->direction,
                'from_phone'   => $c->from_phone,
                'to_phone'     => $c->to_phone,
                'contact_id'   => $c->contact_id,
                'contact_name' => $c->contact?->name ?: ($c->contact?->first_name ?: null),
                'started_at'   => optional($c->started_at)->toIso8601String(),
                'meta_call_id' => $c->meta_call_id,
                'sdp_offer'    => $c->meta_payload['session']['sdp'] ?? null,
            ])->values(),
        ]);
    }

    /**
     * Dial-progress poll for a business-initiated (outbound) call.
     *
     * After the browser POSTs its SDP offer via /calls/dial, Meta rings
     * the customer. When they pick up, Meta delivers its SDP *answer* in
     * the call.connect webhook — WaCallingWebhookController::handleConnect
     * stamps the row status='active' and stashes the answer in
     * meta_payload. The dialing browser peer polls this endpoint until
     * the answer appears, then applies it (setRemoteDescription) so media
     * actually flows. Without this relay the call shows "connected" but
     * has no audio in either direction.
     *
     * Returns:
     *   status      — ringing | connecting | active | ended | failed
     *   sdp_answer  — Meta's answer SDP once the call is active, else null
     *   ended       — true once the call is terminal (customer declined,
     *                 hung up, or Meta cancelled) so the browser stops
     *                 polling and tears the panel down
     */
    public function dialProgress(Request $request, int $id): JsonResponse
    {
        $call = $this->call($request, $id);

        $session = is_array($call->meta_payload['session'] ?? null)
            ? $call->meta_payload['session']
            : [];
        $sdp     = (string) ($session['sdp'] ?? '');
        $sdpType = strtolower((string) ($session['sdp_type'] ?? ''));

        // Only hand back a genuine ANSWER. If the stored SDP is an offer
        // (shouldn't happen for outbound, but be defensive) don't feed it
        // to the dialing peer — that would break its have-local-offer state.
        $answer = ($call->status === 'active' && $sdp !== '' && $sdpType !== 'offer')
            ? $sdp
            : null;

        $ended = in_array($call->status, ['ended', 'failed'], true);

        return response()->json([
            'ok'         => true,
            'status'     => $call->status,
            'sdp_answer' => $answer,
            'ended'      => $ended,
            'end_reason' => $ended ? $call->end_reason : null,
        ]);
    }

    /**
     * Receive a browser-recorded call (operator calls are browser↔Meta
     * direct, so the server never sees the audio otherwise). Store the
     * mixed webm and attach it to the call-log row so it plays back under
     * /call-logs. The upload lands after the call ends, so it also un-nulls
     * whatever onTerminate stamped for a non-AI call.
     */
    public function uploadRecording(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'recording' => ['required', 'file', 'max:51200'], // ≤50 MB
        ]);
        $call = $this->call($request, $id);

        // Make sure there's a call-log row to attach to (onConnect normally
        // created it; create on the fly if a webhook was missed).
        $log = $call->ai_call_log_id ? \App\Models\AiCallLog::find($call->ai_call_log_id) : null;
        if (!$log) {
            $log = app(\App\Services\Calling\WaCallToAiLogBridge::class)->onConnect($call);
        }
        if (!$log) {
            return response()->json(['ok' => false, 'error' => 'call log unavailable'], 422);
        }

        try {
            $file = $request->file('recording');
            $path = $file->storeAs('call-recordings', "call-{$log->id}.webm", 'public');
            $url  = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

            $log->update(['recording_url_mixed' => $url]);
            $call->forceFill(['recording_path' => $path])->save();

            return response()->json(['ok' => true, 'url' => $url]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[WA-CALLING] recording upload failed: ' . $e->getMessage(), ['call' => $id]);
            return response()->json(['ok' => false, 'error' => 'could not store recording'], 500);
        }
    }

    /* ─────────────────────── internal helpers ────────────────────── */

    private function cfg(Request $request, int $id): WaProviderConfig
    {
        $cfg = WaProviderConfig::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        if ($cfg->provider !== 'waba') {
            abort(422, 'Calling is only available on WABA numbers.');
        }
        return $cfg;
    }

    private function call(Request $request, int $id): WaCall
    {
        $call = WaCall::where('workspace_id', $request->user()->current_workspace_id)->findOrFail($id);
        return $call;
    }
}
