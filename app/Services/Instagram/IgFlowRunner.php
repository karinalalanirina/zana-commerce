<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\InstagramFlowSession;
use App\Models\InstagramMessage;
use Illuminate\Support\Facades\Log;

/**
 * Executes a visual Instagram flow (flow_type 'instagram') against inbound
 * events. Reads the RAW builder JSON (nodes {id,type,data}, edges
 * {source,sourceHandle,target}). Linear walk from the Trigger down the
 * primary output edge; send-nodes go through InstagramService.
 *
 * Quick-reply / Button nodes PAUSE the walk and persist an
 * instagram_flow_sessions row; the next inbound DM (the tap) resumes from
 * the matched branch — so multi-turn button flows work.
 */
class IgFlowRunner
{
    /** @var array<string,array> id => node */
    private array $nodes = [];
    /** @var array<int,array> */
    private array $edges = [];
    private array $vars = [];
    private int $flowId = 0;

    public function __construct(private InstagramAccount $account) {}

    private function load(array $flow, int $flowId = 0): void
    {
        $this->nodes = [];
        foreach (($flow['nodes'] ?? []) as $n) {
            if (!empty($n['id'])) $this->nodes[$n['id']] = $n;
        }
        $this->edges = $flow['edges'] ?? [];
        $this->flowId = $flowId;
    }

    /** Fresh run from the Trigger. @param array $ctx {igsid, text, comment_id?} */
    public function run(array $flow, array $ctx, int $flowId = 0): void
    {
        $this->load($flow, $flowId);
        $this->vars = [
            'text'       => (string) ($ctx['text'] ?? ''),
            'igsid'      => (string) ($ctx['igsid'] ?? ''),
            'comment_id' => (string) ($ctx['comment_id'] ?? ''),
        ];
        $this->clearSession((string) ($ctx['igsid'] ?? ''));

        $start = $this->entryNode();
        if (!$start) { Log::info('[IG-FLOW] no trigger node'); return; }
        $this->walk($this->next($start['id'], 'out'), (string) ($ctx['igsid'] ?? ''));
    }

    /**
     * Resume a paused flow when a tap (quick-reply payload / postback) arrives.
     * @return bool true if a session was found and resumed.
     */
    public static function resumeFor(InstagramAccount $account, string $igsid, string $text): bool
    {
        $sess = InstagramFlowSession::where('instagram_account_id', $account->id)->where('igsid', $igsid)->first();
        if (!$sess) return false;
        if (!$sess->isLive()) { $sess->delete(); return false; }

        $flow = \App\Models\Flow::where('workspace_id', $account->workspace_id)->where('id', $sess->flow_id)->first();
        if (!$flow) { $sess->delete(); return false; }
        $data = $flow->decoded_flow_data;
        if (!is_array($data) || empty($data['nodes'])) { $sess->delete(); return false; }

        $r = new self($account);
        $r->load($data, (int) $sess->flow_id);
        $r->vars = is_array($sess->vars) ? $sess->vars : [];
        $r->vars['text']  = $text;
        $r->vars['igsid'] = $igsid;

        $paused = $r->nodes[$sess->node_id] ?? null;
        if (!$paused) { $sess->delete(); return false; }

        // Decide which branch the tap takes.
        $port = 'out';
        if (($paused['type'] ?? '') === 'ig_quick') {
            $opts = (array) (($paused['data'] ?? [])['options'] ?? []);
            $idx = null;
            $t = mb_strtolower(trim($text));
            foreach ($opts as $i => $o) {
                $p = mb_strtolower(trim((string) ($o['payload'] ?? '')));
                $ti = mb_strtolower(trim((string) ($o['title'] ?? '')));
                if (($p !== '' && $t === $p) || ($ti !== '' && $t === $ti)) { $idx = $i; break; }
            }
            if ($idx === null) { $sess->delete(); return false; } // no match → let normal handling run
            $port = 'p' . $idx;
        }

        $sess->delete(); // consumed
        $r->walk($r->next($sess->node_id, $port), $igsid);
        return true;
    }

    /** The walk loop. Pauses (saves a session) at quick/button nodes. */
    private function walk(?string $current, string $igsid): void
    {
        $svc = new InstagramService($this->account);
        $guard = 0;

        while ($current && $guard++ < 50) {
            $node = $this->nodes[$current] ?? null;
            if (!$node) break;
            $type = (string) ($node['type'] ?? '');
            $d    = (array) ($node['data'] ?? []);
            $port = 'out';

            switch ($type) {
                case 'ig_send_dm':
                    if ($igsid) {
                        $body = $this->subst($d['text'] ?? '');
                        $rr = $svc->sendDm($igsid, $body);
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $body, 'flow', $rr['mid'] ?? null);
                    }
                    break;

                case 'ig_quick':
                    if ($igsid) {
                        $qBody = $this->subst($d['text'] ?? '');
                        $rr = $svc->sendQuickReplies($igsid, $qBody, (array) ($d['options'] ?? []));
                        // Mirror to the Instaflow inbox so the quick-reply prompt
                        // shows as a sent bubble (matches ig_send_dm / ig_ai).
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $qBody, 'flow', $rr['mid'] ?? null);
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the tap

                case 'ig_buttons':
                    if ($igsid) {
                        $bBody = $this->subst($d['text'] ?? '');
                        $rr = $svc->sendButtonTemplate($igsid, $bBody, (array) ($d['buttons'] ?? []));
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $bBody, 'flow', $rr['mid'] ?? null);
                        $this->saveSession($igsid, $current);
                    }
                    return; // pause for the tap

                case 'ig_reply_comment':
                    if (!empty($this->vars['comment_id'])) $svc->replyComment($this->vars['comment_id'], $this->subst($d['message'] ?? ''));
                    break;

                case 'ig_ai':
                    $reply = $this->aiReply($d);
                    if ($reply !== '' && $igsid) {
                        $rr = $svc->sendDm($igsid, $reply);
                        if (!empty($rr['ok'])) InstagramMessage::log($this->account, $igsid, 'out', $reply, 'ai', $rr['mid'] ?? null);
                    }
                    if (!empty($d['save'])) $this->vars[$d['save']] = $reply;
                    break;

                case 'condition':
                    $port = $this->evalCondition($d) ? 'yes' : 'no';
                    break;

                case 'delay':
                    break; // cannot block the webhook — skip the wait

                case 'end':
                    return;
            }

            $current = $this->next($current, $port);
        }
    }

    private function saveSession(string $igsid, string $nodeId): void
    {
        if (!$igsid || !$this->flowId) return;
        InstagramFlowSession::updateOrCreate(
            ['instagram_account_id' => $this->account->id, 'igsid' => $igsid],
            [
                'workspace_id' => $this->account->workspace_id,
                'flow_id'      => $this->flowId,
                'node_id'      => $nodeId,
                'vars'         => $this->vars,
                'expires_at'   => now()->addHours(24),
            ]
        );
    }

    private function clearSession(string $igsid): void
    {
        if ($igsid) InstagramFlowSession::where('instagram_account_id', $this->account->id)->where('igsid', $igsid)->delete();
    }

    private function entryNode(): ?array
    {
        foreach ($this->nodes as $n) {
            if (($n['type'] ?? '') === 'trigger') return $n;
        }
        return null;
    }

    /** Follow the edge leaving $nodeId on $port (falls back to any out edge). */
    private function next(string $nodeId, string $port): ?string
    {
        $any = null;
        foreach ($this->edges as $e) {
            if (($e['source'] ?? null) !== $nodeId) continue;
            $any = $any ?? (string) ($e['target'] ?? '');
            $h = (string) ($e['sourceHandle'] ?? 'out');
            if ($h === $port) return (string) ($e['target'] ?? '');
        }
        return $port === 'out' ? $any : null;
    }

    private function subst(string $s): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) {
            return (string) ($this->vars[$m[1]] ?? '');
        }, $s) ?? $s;
    }

    private function evalCondition(array $d): bool
    {
        $left  = $this->subst((string) ($d['variable'] ?? $d['left'] ?? '{{text}}'));
        $right = $this->subst((string) ($d['value'] ?? $d['right'] ?? ''));
        $op    = (string) ($d['operator'] ?? $d['op'] ?? 'contains');
        $l = mb_strtolower(trim($left)); $r = mb_strtolower(trim($right));
        return match ($op) {
            'equals', '=', '==' => $l === $r,
            'not_equals', '!='  => $l !== $r,
            'starts_with'       => str_starts_with($l, $r),
            default             => $r === '' ? true : str_contains($l, $r),
        };
    }

    private function aiReply(array $d): string
    {
        $system = trim((string) ($d['prompt'] ?? '')) ?: 'You are a helpful Instagram assistant. Reply briefly.';
        $assistantId = (int) ($d['assistant'] ?? 0);
        if ($assistantId > 0) {
            $a = \App\Models\AiChatAssistant::where('workspace_id', $this->account->workspace_id)->where('id', $assistantId)->first();
            if ($a) {
                try { $kb = app(\App\Services\AiChat\AiChatService::class)->contextFor($a); if (trim($kb) !== '') $system .= "\n\n--- Knowledge base ---\n" . $kb; }
                catch (\Throwable $e) {}
            }
        }
        $model = (string) ($d['model'] ?? 'gpt-4o-mini');
        try {
            $reply = app(\App\Services\AiAgentService::class)->callProvider(
                provider: InstagramService::providerForModel($model),
                model: $model,
                workspaceId: (int) $this->account->workspace_id,
                systemPrompt: $system,
                userPrompt: $this->subst('{{text}}'),
                maxTokens: 300,
                temperature: 0.6,
            );
            return trim((string) $reply);
        } catch (\Throwable $e) {
            Log::warning('[IG-FLOW] AI node failed: ' . $e->getMessage());
            return '';
        }
    }
}
