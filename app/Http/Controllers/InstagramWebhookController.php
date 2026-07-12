<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramAutomation;
use App\Models\SystemSetting;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Platform webhook — ONE endpoint for everything.
 *   GET  /webhooks/instagram  → subscription verify (echo hub.challenge).
 *   POST /webhooks/instagram  → events (HMAC-verified), fanned out:
 *       - DM          → dm_keyword automations (auto-reply)
 *       - comment     → comment_to_dm automations (public reply + DM)
 *       - (mentions/story-reply hooks land here too — extend as needed)
 * Mirrors our WABA webhook (verify token + X-Hub-Signature-256).
 */
class InstagramWebhookController extends Controller
{
    /** GET — Meta subscription handshake. */
    public function verify(Request $request)
    {
        $expected = (string) SystemSetting::get('instagram_webhook_verify_token', '');
        // DIAGNOSTIC — proves Meta reached the endpoint during subscription setup.
        Log::info('[IG-HOOK] verify handshake', [
            'mode'        => $request->query('hub_mode'),
            'token_set'   => $expected !== '',
            'token_match' => $expected !== '' && hash_equals($expected, (string) $request->query('hub_verify_token')),
        ]);
        if ($request->query('hub_mode') === 'subscribe'
            && $expected !== ''
            && hash_equals($expected, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }
        return response('forbidden', 403);
    }

    /** POST — events. */
    public function handle(Request $request)
    {
        // DIAGNOSTIC — log EVERY inbound POST so you can confirm Meta is actually
        // calling the webhook. If you DM the account and DON'T see this line in
        // storage/logs/laravel.log, Meta never hit us → it's a subscription /
        // dev-mode-tester / reachability issue, NOT our code. (Body truncated.)
        Log::info('[IG-HOOK] POST received', [
            'has_sig' => $request->hasHeader('X-Hub-Signature-256'),
            'len'     => strlen($request->getContent()),
            'body'    => mb_substr($request->getContent(), 0, 1500),
        ]);

        // Signature check (sha256=HMAC of the raw body with the app secret).
        // FAIL CLOSED: the Meta/IG app secret is a platform setting that must
        // always be present. If it is genuinely unset we refuse to process the
        // (unverifiable) payload rather than trusting attacker-supplied entry.id
        // to drive DMs/comments/flows for a victim's connected account. The
        // Instagram-Login OAuth path exchanges tokens with instagram_ig_app_secret,
        // so a webhook may be signed by either app — accept a valid signature
        // under EITHER configured secret before refusing.
        $sig     = (string) $request->header('X-Hub-Signature-256', '');
        $secrets = array_values(array_unique(array_filter([
            (string) SystemSetting::get('instagram_app_secret', ''),
            (string) SystemSetting::get('instagram_ig_app_secret', ''),
        ])));
        if (empty($secrets)) {
            // No secret configured anywhere — cannot verify. Refuse to process
            // any state-changing effects. Ack 200 so Meta doesn't retry-storm,
            // but do NOT touch accounts/flows/AI.
            Log::warning('[IG-HOOK] instagram_app_secret NOT configured — refusing to process unverifiable webhook (set the Meta App Secret in Instagram settings)');
            return response('signature verification not configured', 200);
        }
        if ($sig === '') {
            Log::warning('[IG-HOOK] missing X-Hub-Signature-256 (secret is set)');
            return response('missing signature', 403);
        }
        $sigOk = false;
        foreach ($secrets as $secret) {
            if (hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secret), $sig)) {
                $sigOk = true;
                break;
            }
        }
        if (!$sigOk) {
            Log::warning('[IG-HOOK] bad signature');
            return response('bad signature', 403);
        }

        $body = $request->all();
        Log::info('[IG-HOOK] signature OK — parsing', [
            'object'  => (string) ($body['object'] ?? ''),
            'entries' => count((array) ($body['entry'] ?? [])),
        ]);
        foreach ((array) ($body['entry'] ?? []) as $ei => $entry) {
            $igId    = (string) ($entry['id'] ?? '');
            // Trace EXACTLY what shape this entry carries so a mismatch between
            // the messaging[] (FB-Login) vs changes[] (IG-Login) channel is obvious.
            Log::info('[IG-HOOK] entry ' . $ei, [
                'entry_id'      => $igId,
                'top_keys'      => array_keys((array) $entry),
                'messaging_ct'  => count((array) ($entry['messaging'] ?? [])),
                'changes_flds'  => array_map(fn ($c) => (string) ($c['field'] ?? '?'), (array) ($entry['changes'] ?? [])),
            ]);
            $account = $igId ? InstagramAccount::where('ig_user_id', $igId)->where('status', 'connected')->first() : null;
            if (!$account) {
                // DEEP DIAG — the #1 reason inbound "does nothing": entry.id (the
                // IG id Meta sends) doesn't match any CONNECTED account row. Dump
                // what we DO have so the mismatch (wrong id stored, or a
                // disconnected/duplicate row) is visible at a glance.
                $anyRow = $igId ? InstagramAccount::where('ig_user_id', $igId)->first() : null;
                Log::warning('[IG-HOOK] no CONNECTED account for ig_user_id — inbound dropped', [
                    'incoming_ig_id'    => $igId,
                    'row_with_that_id'  => $anyRow ? ['id' => $anyRow->id, 'status' => $anyRow->status, 'ws' => $anyRow->workspace_id] : null,
                    'connected_accounts'=> InstagramAccount::where('status', 'connected')
                        ->get(['id', 'ig_user_id', 'username', 'workspace_id'])
                        ->map(fn ($a) => [
                            'id'         => $a->id,
                            'ig_user_id' => (string) $a->ig_user_id,
                            'username'   => (string) $a->username,
                            'ws'         => $a->workspace_id,
                        ])->all(),
                    'all_accounts_ct'   => InstagramAccount::count(),
                ]);
                continue;
            }
            Log::info('[IG-HOOK] entry matched account', ['account' => $account->id, 'ig_user_id' => $igId, 'ws' => $account->workspace_id]);

            // DMs + postbacks + reactions + reads + referrals (messaging channel).
            // Web-verified shapes: reaction/read/referral are TOP-LEVEL keys on
            // the messaging entry; postback too (handled inside onDm).
            foreach ((array) ($entry['messaging'] ?? []) as $m) {
                if (isset($m['reaction'])) { $this->onReaction($account, $m); continue; }
                if (isset($m['read']))     { continue; } // messaging_seen — read receipt, no action
                if (isset($m['referral']) && !isset($m['message']) && !isset($m['postback'])) {
                    Log::info('[IG-HOOK] referral', ['account' => $account->id, 'ref' => $m['referral']['ref'] ?? '']);
                    continue;
                }
                $this->onDm($account, $m); // text, quick_reply, postback, story reply/mention
            }
            // Comments / live comments / mentions (the "changes" channel).
            foreach ((array) ($entry['changes'] ?? []) as $c) {
                $field = (string) ($c['field'] ?? '');
                // Instagram API w/ Instagram Login (the "no FB Page" path) delivers
                // DMs under changes[].field='messages' — NOT entry.messaging[] like
                // the Facebook/Messenger path. The value carries the same
                // {sender,recipient,message,postback} shape, so route it to onDm.
                if ($field === 'messages')                                $this->onDm($account, (array) ($c['value'] ?? []));
                elseif ($field === 'comments' || $field === 'live_comments') $this->onComment($account, (array) ($c['value'] ?? []));
                elseif ($field === 'mentions')                            $this->onMention($account, (array) ($c['value'] ?? []));
            }
        }
        return response('ok', 200); // always 200 so Meta doesn't retry-storm
    }

    /** Inbound DM → match dm_keyword automations and auto-reply. */
    private function onDm(InstagramAccount $account, array $m): void
    {
        $igsid = (string) ($m['sender']['id'] ?? '');
        // TRACE — proves onDm was reached + shows the exact payload shape so a
        // missing sender / unexpected field (why "nothing happens") is visible.
        Log::info('[IG-HOOK] onDm enter', [
            'account'  => $account->id,
            'from'     => $igsid,
            'keys'     => array_keys($m),
            'has_msg'  => isset($m['message']),
            'has_text' => isset($m['message']['text']),
            'has_pb'   => isset($m['postback']),
            'is_echo'  => !empty($m['message']['is_echo']),
        ]);
        // BUG FIX (web-verified): Button-Template / CTA / icebreaker taps arrive
        // as a TOP-LEVEL `postback` (siblings .payload/.title/.mid) — NOT inside
        // `message`. The old code only read message.text + message.quick_reply,
        // so every button tap was dropped. Treat a postback like a quick-reply:
        // its payload (fallback to the visible title) drives flow/keyword routing.
        $isPostback = isset($m['postback']);
        $text = $isPostback
            ? (string) ($m['postback']['payload'] ?? $m['postback']['title'] ?? '')
            : (string) ($m['message']['text'] ?? $m['message']['quick_reply']['payload'] ?? '');
        $mid = (string) ($m['message']['mid'] ?? $m['postback']['mid'] ?? '');
        // Ignore echoes of our own outbound messages (echoes carry message.is_echo).
        if (!empty($m['message']['is_echo']) || $igsid === '' || $igsid === $account->ig_user_id) {
            Log::info('[IG-HOOK] DM skipped', [
                'echo'      => !empty($m['message']['is_echo']),
                'no_sender' => $igsid === '',
                'self'      => $igsid !== '' && $igsid === $account->ig_user_id,
            ]);
            return;
        }

        Log::info('[IG-HOOK] ' . ($isPostback ? 'postback' : 'DM') . ' in', ['account' => $account->id, 'from' => $igsid, 'len' => strlen($text)]);
        \App\Models\InstagramMessage::log($account, $igsid, 'in', $text, $isPostback ? 'postback' : null, $mid);

        // A flow paused at a quick-reply / button node? This tap resumes it
        // from the matched branch — takes priority over fresh triggers.
        try {
            if (\App\Services\Instagram\IgFlowRunner::resumeFor($account, $igsid, $text)) return;
        } catch (\Throwable $e) { Log::warning('[IG-FLOW] resume failed: ' . $e->getMessage()); }

        // Story reply / story mention (both arrive as DMs, per Meta docs) →
        // story_reply automations.
        $isStory = !empty($m['message']['reply_to']['story']);
        foreach ((array) ($m['message']['attachments'] ?? []) as $att) {
            if (($att['type'] ?? '') === 'story_mention') $isStory = true;
        }
        if ($isStory) {
            foreach (InstagramAutomation::where('instagram_account_id', $account->id)
                ->where('type', 'story_reply')->where('is_active', true)->orderBy('id')->get() as $rule) {
                if (!$rule->matches($text)) continue;
                if ($rule->flow_id) {
                    if ($this->runFlow($account, (int) $rule->flow_id, ['igsid' => $igsid, 'text' => $text])) { $rule->increment('fired_count'); return; }
                    continue;
                }
                $r = (new InstagramService($account))->sendDm($igsid, (string) $rule->dm_message);
                if (!empty($r['ok'])) {
                    $rule->increment('fired_count');
                    \App\Models\InstagramMessage::log($account, $igsid, 'out', (string) $rule->dm_message, 'story', $r['mid'] ?? null);
                    return;
                }
            }
        }

        $rules = InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'dm_keyword')->where('is_active', true)->orderBy('id')->get();

        foreach ($rules as $rule) {
            if (!$rule->matches($text)) continue;
            $svc = new InstagramService($account);
            $r = $svc->sendDm($igsid, (string) $rule->dm_message);
            if (!empty($r['ok'])) {
                $rule->increment('fired_count');
                \App\Models\InstagramMessage::log($account, $igsid, 'out', (string) $rule->dm_message, 'keyword', $r['mid'] ?? null);
            } else Log::warning('[IG-HOOK] DM reply failed', ['rule' => $rule->id, 'err' => $r['error'] ?? '']);
            return; // first keyword match wins
        }

        // Flow automations — run a visual Instagram flow when matched.
        foreach (InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'flow')->where('is_active', true)->orderBy('id')->get() as $rule) {
            if (!$rule->matches($text)) continue;
            if ($this->runFlow($account, (int) $rule->flow_id, ['igsid' => $igsid, 'text' => $text])) {
                $rule->increment('fired_count');
                return;
            }
        }

        // No keyword/flow rule matched → AI agent (if one is configured).
        $this->aiReply($account, $igsid, $text);
    }

    /**
     * Inbound message reaction (message_reactions field). Web-verified shape:
     * top-level `reaction` with action=react|unreact; react carries reaction
     * (name, e.g. "love") + emoji. We log it onto the thread so the inbox shows
     * "❤️ reacted". (Subscribe the app/account to message_reactions.)
     */
    private function onReaction(InstagramAccount $account, array $m): void
    {
        $igsid = (string) ($m['sender']['id'] ?? '');
        if ($igsid === '' || $igsid === $account->ig_user_id) return;
        $reaction = (array) ($m['reaction'] ?? []);
        $action   = (string) ($reaction['action'] ?? '');
        $emoji    = (string) ($reaction['emoji'] ?? $reaction['reaction'] ?? '');
        Log::info('[IG-HOOK] reaction', ['account' => $account->id, 'from' => $igsid, 'action' => $action, 'reaction' => $emoji]);
        \App\Models\InstagramMessage::log(
            $account, $igsid, 'in',
            $action === 'unreact' ? 'removed reaction' : trim('reacted ' . $emoji),
            'reaction', (string) ($reaction['mid'] ?? '')
        );
    }

    /** Load a flow_type=instagram flow and run it through IgFlowRunner. */
    private function runFlow(InstagramAccount $account, int $flowId, array $ctx): bool
    {
        if ($flowId <= 0) return false;
        $flow = \App\Models\Flow::where('workspace_id', $account->workspace_id)->where('id', $flowId)->first();
        if (!$flow) return false;
        $data = $flow->decoded_flow_data;
        if (!is_array($data) || empty($data['nodes'])) return false;
        try { (new \App\Services\Instagram\IgFlowRunner($account))->run($data, $ctx, $flowId); }
        catch (\Throwable $e) { Log::warning('[IG-FLOW] run failed: ' . $e->getMessage()); return false; }
        return true;
    }

    /**
     * AI auto-reply: when an `ai_agent` automation exists, generate a reply
     * with the same AiAgentService + AI-Training knowledge base the chat /
     * call flows use, and DM it back. Honours the 24h messaging window (the
     * webhook only fires within it on inbound).
     */
    private function aiReply(InstagramAccount $account, string $igsid, string $text): void
    {
        $rule = InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'ai_agent')->where('is_active', true)->orderBy('id')->first();
        if (!$rule) return;

        $system = trim((string) $rule->dm_message) ?: 'You are a helpful Instagram assistant for this business. Reply briefly and warmly.';

        // Optional AI-Training knowledge base.
        $assistantId = (int) ($rule->meta_json['assistant_id'] ?? 0);
        if ($assistantId > 0) {
            $assistant = \App\Models\AiChatAssistant::where('workspace_id', $account->workspace_id)->where('id', $assistantId)->first();
            if ($assistant) {
                try {
                    $kb = app(\App\Services\AiChat\AiChatService::class)->contextFor($assistant);
                    if (trim($kb) !== '') $system .= "\n\n--- Knowledge base ---\n" . $kb . "\n--- End knowledge base ---";
                } catch (\Throwable $e) { /* best-effort */ }
            }
        }

        // Conversation memory — last few turns of this thread become context.
        $history = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
            ->where('igsid', $igsid)->orderByDesc('id')->limit(8)->get()->reverse();
        $transcript = '';
        foreach ($history as $h) {
            $who = $h->direction === 'in' ? 'Customer' : 'You';
            $transcript .= $who . ': ' . trim((string) $h->body) . "\n";
        }
        $userPrompt = $transcript !== ''
            ? "Conversation so far:\n{$transcript}\nCustomer just said: {$text}\nReply as You:"
            : $text;

        $model = (string) ($rule->meta_json['model'] ?? 'gpt-4o-mini');
        try {
            $reply = app(\App\Services\AiAgentService::class)->callProvider(
                provider:     \App\Services\Instagram\InstagramService::providerForModel($model),
                model:        $model,
                workspaceId:  (int) $account->workspace_id,
                systemPrompt: $system,
                userPrompt:   $userPrompt,
                maxTokens:    300,
                temperature:  0.6,
            );
        } catch (\Throwable $e) {
            Log::warning('[IG-HOOK] AI reply threw: ' . $e->getMessage());
            $reply = null;
        }
        if ($reply === null || trim($reply) === '') return;

        $r = (new InstagramService($account))->sendDm($igsid, trim($reply));
        if (!empty($r['ok'])) {
            $rule->increment('fired_count');
            \App\Models\InstagramMessage::log($account, $igsid, 'out', trim($reply), 'ai', $r['mid'] ?? null);
        }
    }

    /** Inbound comment → comment_to_dm automations (public reply + private DM). */
    private function onComment(InstagramAccount $account, array $value): void
    {
        // Comment-id key inconsistency (web-verified): the Examples page uses
        // `comment_id`, the Graph reference schema uses `id`. Accept both.
        $commentId = (string) ($value['comment_id'] ?? $value['id'] ?? '');
        $text      = (string) ($value['text'] ?? '');
        $postId    = (string) ($value['media']['id'] ?? '');
        $fromId    = (string) ($value['from']['id'] ?? '');
        // Skip our own comments.
        if ($commentId === '' || $fromId === $account->ig_user_id) return;

        Log::info('[IG-HOOK] comment in', ['account' => $account->id, 'post' => $postId, 'comment' => $commentId]);

        $rules = InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'comment_to_dm')->where('is_active', true)->orderBy('id')->get();

        foreach ($rules as $rule) {
            // Optional per-post scoping.
            if ($rule->post_id && $postId && $rule->post_id !== $postId) continue;
            if (!$rule->matches($text)) continue;

            $svc = new InstagramService($account);
            if ($rule->public_reply) $svc->replyComment($commentId, (string) $rule->public_reply);
            if ($rule->dm_message) {
                $r = $svc->privateReply($commentId, (string) $rule->dm_message);
                if (!empty($r['ok'])) {
                    $rule->increment('fired_count');
                    if ($fromId !== '') {
                        \App\Models\InstagramMessage::log($account, $fromId, 'in', $text, 'comment');
                        \App\Models\InstagramMessage::log($account, $fromId, 'out', (string) $rule->dm_message, 'comment', $r['mid'] ?? null);
                    }
                } else Log::warning('[IG-HOOK] private reply failed', ['rule' => $rule->id, 'err' => $r['error'] ?? '']);
            }
            return; // first match wins
        }

        // Flow automations on comments — run a visual flow (comment → DM sequence).
        foreach (InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'flow')->where('is_active', true)->orderBy('id')->get() as $rule) {
            if ($rule->post_id && $postId && $rule->post_id !== $postId) continue;
            if (!$rule->matches($text)) continue;
            if ($fromId && $this->runFlow($account, (int) $rule->flow_id, ['igsid' => $fromId, 'text' => $text, 'comment_id' => $commentId])) {
                $rule->increment('fired_count');
                return;
            }
        }
    }

    /**
     * Inbound @mention (the `mentions` webhook field). Payload carries
     * media_id and, for a comment mention, comment_id. We post a public
     * reply on the mention — DMing isn't possible here (no IGSID in payload).
     */
    private function onMention(InstagramAccount $account, array $value): void
    {
        $commentId = (string) ($value['comment_id'] ?? '');
        $mediaId   = (string) ($value['media_id'] ?? '');
        if ($commentId === '' && $mediaId === '') return;
        Log::info('[IG-HOOK] mention in', ['account' => $account->id, 'media' => $mediaId, 'comment' => $commentId]);

        $svc = new InstagramService($account);
        foreach (InstagramAutomation::where('instagram_account_id', $account->id)
            ->where('type', 'mention')->where('is_active', true)->orderBy('id')->get() as $rule) {
            $reply = trim((string) ($rule->public_reply ?: $rule->dm_message));
            if ($reply === '') continue;
            if ($commentId !== '') { $svc->replyComment($commentId, $reply); $rule->increment('fired_count'); return; }
            // Caption mention (no comment) → leave a comment on the media.
            if ($mediaId !== '') { $svc->commentOnMedia($mediaId, $reply); $rule->increment('fired_count'); return; }
        }
    }
}
