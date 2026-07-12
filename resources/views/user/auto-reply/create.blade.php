@php
    use App\Services\WorkspaceEngine;

    $devices = $devices ?? collect();
    $senders = $senders ?? collect();
    $templates = $templates ?? collect();
    $flows = $flows ?? collect();
    $wsId = (int) (auth()->user()->current_workspace_id ?? 0);

    // Edit mode: AutoReplyController::create() loads $row when the
    // URL has ?id=N. The blade still renders the same defaults the
    // "new" path uses; the JS reads window.WA_AUTOREPLY_ROW on init
    // and re-applies the row's values (name, keyword chips, device
// selection, match method, similarity, reply type, cooldown,
// timeout, contents). On submit the JS detects row.id and
// PATCHes /auto-reply/{id} instead of POSTing /auto-reply.
$row = $row ?? null;
$isEdit = (bool) $row;
$editPayload = $row
    ? [
        'id' => $row->id,
        'name' => (string) ($row->keyword ?? ''), // legacy: keyword field doubles as rule label
        'keywords' => array_values(array_filter(array_map('trim', explode(',', (string) $row->keyword)))),
        'device_id' => $row->device_id,
        // Composite sender key the unified picker uses to pre-tick the
        // saved sender on edit: re-derive from the row's provider (the
        // CHOSEN engine, stamped at save) + device_id. Falls back to the
        // workspace default engine for legacy rows with no provider.
        'sender_key' => (($row->provider ?: WorkspaceEngine::for($wsId)) . ':' . $row->device_id),
        'matching_method' => $row->matching_method ?? 'fuzzy',
        'fuzzy_similarity' => $row->fuzzy_similarity ?? 80,
        'cooldown' => $row->cooldown,
        'timeout' => $row->timeout,
        'reply_type' => $row->reply_type ?? 'custom',
        'flow_id' => $row->flow_id,
        'target_contact_id' => $row->target_contact_id,
        'target_catalog_id' => $row->target_catalog_id,
        'message_type' => $row->message_type ?? 'text',
        'contents' => $row->relationLoaded('contents')
            ? $row->contents
                ->map(
                    fn($c) => [
                        'content_type' => $c->content_type,
                        'content' => (string) $c->content,
                        'template_id' => $c->template_id,
                        ],
                    )
                    ->values()
                : [],
        ]
        : null;

    // Pre-tick the saved sender on edit (server-side, in addition to the
    // JS hydration). Wrapped in old() so a failed re-render keeps the
    // operator's last selection.
    $senderSelected = old('sender', $isEdit ? (!empty($selectedSenderKeys) ? $selectedSenderKeys : [$editPayload['sender_key']]) : []);
@endphp

<x-layouts.user :title="$isEdit ? __('Edit Auto Reply') : __('New Auto Reply')" nav-key="more" page="user-auto-reply-create">

    <!-- Sticky toolbar -->
    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/auto-reply') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Auto Reply') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">Auto Reply /
                        {{ $isEdit ? 'Edit · #' . $row->id : 'New' }}</div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $isEdit ? 'Edit auto' : 'Create new auto' }} <span
                            class="italic text-wa-deep">{{ __('reply') }}</span></div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 font-mono">{{ __('Draft / unsaved') }}</span>
                <button type="button"
                    class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            </div>
        </div>
    </div>

    <section class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">
        <form id="autoreplyForm" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">

            <!-- Form card -->
            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                <!-- Stepper indicator -->
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40 overflow-x-auto">
                    <div class="flex items-center min-w-[520px]" id="stepper">
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="1">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-wa-deep text-wa-deep ring-4 ring-wa-deep/10">1</span>
                            <span
                                class="lab text-[11.5px] font-semibold whitespace-nowrap text-wa-deep">{{ __('Trigger') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="2">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">2</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Schedule') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Reply type') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="4">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">4</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Compose') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="5">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">5</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Review') }}</span>
                        </div>
                    </div>
                </div>

                <!-- step body -->
                <div class="p-5">

                    <!-- Step 1: Trigger -->
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Trigger') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="rule-name">{{ __('Rule name') }} <span
                                        class="text-accent-coral">*</span></label>
                                <input id="rule-name" type="text"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 leading-snug focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('e.g. Pricing inquiries') }}" required>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("Internal label only — customers don't see this.") }}</div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5">
                                    <span>{{ $senders->count() > 1 ? __('Senders') : __('Sender') }} <span
                                            class="text-accent-coral">*</span></span>
                                    @if ($senders->count() > 1)
                                        <span
                                            class="font-mono text-[10px] text-wa-deep normal-case tracking-normal">{{ __('Multi-sender — tick all that apply') }}</span>
                                    @endif
                                </label>
                                {{-- Multi-engine: every connected sender across all enabled
 engines (Unofficial API + WABA + Twilio). Each ticked box
 becomes its OWN KeywordReply row on submit — the server fans
 out via sender[] composite keys (engine:id) and stamps each
 row's provider = the chosen engine (see
 AutoReplyController::store). Single-engine workspaces see the
 same flat list the legacy device picker showed. --}}
                                <x-sender-picker :senders="$senders" name="sender" :multiple="true"
                                    :selected="$senderSelected" />
                                @if ($senders->count() > 1)
                                    <div class="text-[10.5px] text-ink-500 mt-1">
                                        {{ __('Each ticked sender gets its own row — toggle / delete each independently from the list later.') }}
                                    </div>
                                @endif
                            </div>
                        </div>


                        <div class="mb-4">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5">{{ __('Keywords') }}
                                <span class="text-accent-coral">*</span></label>
                            <div id="kw-list"
                                class="flex flex-wrap gap-1.5 p-2 border border-paper-200 rounded-lg bg-paper-50/40 min-h-[44px]">
                                <span
                                    class="kw-chip inline-flex items-center gap-1.5 bg-paper-0 border border-paper-200 pl-2.5 pr-1 py-1 rounded-full text-[11.5px]">hi
                                    <button type="button"
                                        class="w-[18px] h-[18px] rounded-full bg-paper-100 text-ink-600 grid place-items-center hover:bg-accent-coral hover:text-white"
                                        onclick="this.parentElement.remove()">&times;</button></span>
                                <span
                                    class="kw-chip inline-flex items-center gap-1.5 bg-paper-0 border border-paper-200 pl-2.5 pr-1 py-1 rounded-full text-[11.5px]">{{ __('hello') }}
                                    <button type="button"
                                        class="w-[18px] h-[18px] rounded-full bg-paper-100 text-ink-600 grid place-items-center hover:bg-accent-coral hover:text-white"
                                        onclick="this.parentElement.remove()">&times;</button></span>
                                <span
                                    class="kw-chip inline-flex items-center gap-1.5 bg-paper-0 border border-paper-200 pl-2.5 pr-1 py-1 rounded-full text-[11.5px]">{{ __('hey') }}
                                    <button type="button"
                                        class="w-[18px] h-[18px] rounded-full bg-paper-100 text-ink-600 grid place-items-center hover:bg-accent-coral hover:text-white"
                                        onclick="this.parentElement.remove()">&times;</button></span>
                                <input id="kw-input" type="text"
                                    class="bg-transparent outline-none flex-1 min-w-[120px] text-[12.5px]"
                                    placeholder="{{ __('Type and press Enter…') }}">
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Press') }} <span
                                    class="font-mono">{{ __('Enter') }}</span> or <span class="font-mono">,</span>
                                to add. Group similar phrases in one rule.</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Match method') }}</label>
                                <div id="seg-mm" class="inline-flex p-[3px] bg-paper-50 rounded-full gap-0.5">
                                    <button type="button"
                                        class="seg-btn px-3 py-1 rounded-full text-[11.5px] font-semibold bg-wa-deep text-paper-0"
                                        data-mm="fuzzy">{{ __('Fuzzy') }}</button>
                                    <button type="button"
                                        class="seg-btn px-3 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100"
                                        data-mm="exact">{{ __('Exact') }}</button>
                                    <button type="button"
                                        class="seg-btn px-3 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100"
                                        data-mm="contains">{{ __('Contains') }}</button>
                                    <button type="button"
                                        class="seg-btn px-3 py-1 rounded-full text-[11.5px] font-semibold text-ink-600 hover:bg-paper-100"
                                        data-mm="regex">{{ __('Regex') }}</button>
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-2">
                                    {{ __('Fuzzy catches typos like "pricng" → "pricing".') }}</div>
                                <p id="regex-hint" class="hidden text-[10.5px] text-ink-500 mt-1 leading-snug">
                                    {{ __('Regex: the keyword field is one case-insensitive pattern (no / / delimiters needed), e.g.') }}
                                    <code class="font-mono bg-paper-50 px-1 rounded">(?:price|cost|quote)</code></p>
                            </div>
                            <div id="fuzzy-row">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-1.5"
                                    for="fuzzy">{{ __('Similarity') }} <span id="fuzzy-val"
                                        class="font-mono text-wa-deep">80%</span></label>
                                <input id="fuzzy" type="range" min="0" max="100" value="80"
                                    step="1" class="w-full accent-wa-deep"
                                    oninput="document.getElementById('fuzzy-val').textContent=this.value+'%'">
                                <div class="flex justify-between text-[10px] font-mono text-ink-500 mt-1">
                                    <span>{{ __('loose') }}</span><span>{{ __('strict') }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Schedule -->
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Schedule & limits') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('optional') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="cooldown">{{ __('Cooldown') }}</label>
                                <div class="flex items-center gap-2">
                                    <input id="cooldown" type="number" min="0" placeholder="60"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <span class="text-[11px] font-mono text-ink-500">{{ __('sec') }}</span>
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __("Don't re-trigger for the same user within this window.") }}</div>
                            </div>
                            <div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="timeout">{{ __('Reply delay') }}</label>
                                <div class="flex items-center gap-2">
                                    <input id="timeout" type="number" min="0" placeholder="2"
                                        class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] text-ink-900 focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <span class="text-[11px] font-mono text-ink-500">{{ __('sec') }}</span>
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">
                                    {{ __('Adds a tiny delay so it feels human.') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Reply type -->
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Reply type') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="rt-tile cursor-pointer border rounded-2xl p-4 transition border-wa-deep bg-[#F0F8F6]"
                                data-rt="custom">
                                <div class="flex items-start justify-between mb-3">
                                    <span class="w-10 h-10 rounded-xl bg-wa-mint text-wa-deep grid place-items-center">
                                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M3 5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H8l-3 2v-2a2 2 0 0 1-2-2z" />
                                        </svg>
                                    </span>
                                    <span
                                        class="rt-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-100">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Custom reply') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __('Write text or attach an image, document, video, or template.') }}</p>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Text') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Media') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Template') }}</span>
                                </div>
                            </label>

                            <label
                                class="rt-tile cursor-pointer border rounded-2xl p-4 transition border-paper-200 bg-white hover:border-wa-deep"
                                data-rt="flow">
                                <div class="flex items-start justify-between mb-3">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-[#D9E5F2] text-[#13478A] grid place-items-center">
                                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <circle cx="3.5" cy="8" r="1.8" />
                                            <circle cx="12.5" cy="3.5" r="1.8" />
                                            <circle cx="12.5" cy="12.5" r="1.8" />
                                            <path d="M5 7l6-3M5 9l6 3" />
                                        </svg>
                                    </span>
                                    <span
                                        class="rt-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Run a flow') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __('Hand off to a published flow with branching logic and forms.') }}</p>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Multi-step') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Conditional') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Interactive') }}</span>
                                </div>
                            </label>

                            {{-- #19 — Share contact card --}}
                            <label
                                class="rt-tile cursor-pointer border rounded-2xl p-4 transition border-paper-200 bg-white hover:border-wa-deep"
                                data-rt="share_contact">
                                <div class="flex items-start justify-between mb-3">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-[#FFE4D6] text-[#A1431F] grid place-items-center">
                                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <circle cx="8" cy="5.5" r="2.5" />
                                            <path d="M3 14c0-3 2.5-5 5-5s5 2 5 5" />
                                        </svg>
                                    </span>
                                    <span
                                        class="rt-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Share a contact') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __("Reply with a saved contact's vCard so the customer can tap to call/save.") }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('vCard') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Tap to call') }}</span>
                                </div>
                            </label>

                            {{-- #20 — Send catalog --}}
                            <label
                                class="rt-tile cursor-pointer border rounded-2xl p-4 transition border-paper-200 bg-white hover:border-wa-deep"
                                data-rt="send_catalog">
                                <div class="flex items-start justify-between mb-3">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-wa-bubble text-wa-deep grid place-items-center">
                                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 5h10l-1 9H4z" />
                                            <path d="M5 5a3 3 0 1 1 6 0" />
                                        </svg>
                                    </span>
                                    <span
                                        class="rt-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Send a catalog') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __('Push your WhatsApp product list when a customer types "menu" or "catalog".') }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Catalog') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('MPM') }}</span>
                                </div>
                            </label>

                            {{-- #23 — Request location --}}
                            <label
                                class="rt-tile cursor-pointer border rounded-2xl p-4 transition border-paper-200 bg-white hover:border-wa-deep"
                                data-rt="request_location">
                                <div class="flex items-start justify-between mb-3">
                                    <span
                                        class="w-10 h-10 rounded-xl bg-[#FFF4E0] text-[#7B5A14] grid place-items-center">
                                        <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M8 1.5C5.5 1.5 3.5 3.4 3.5 5.7c0 3 4.5 8.8 4.5 8.8s4.5-5.8 4.5-8.8C12.5 3.4 10.5 1.5 8 1.5z" />
                                            <circle cx="8" cy="6" r="1.5" />
                                        </svg>
                                    </span>
                                    <span
                                        class="rt-check w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center transition opacity-0">
                                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                            stroke="currentColor" stroke-width="2.4">
                                            <path d="M3 8l3 3 7-8" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="font-serif text-[18px] leading-tight">{{ __('Request location') }}</div>
                                <p class="mt-1.5 text-[12px] text-ink-500 leading-snug">
                                    {{ __('Send the WhatsApp "share your location" prompt — great for delivery / pickup flows.') }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-1">
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('Location') }}</span>
                                    <span
                                        class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-paper-50 text-ink-700">{{ __('No payload') }}</span>
                                </div>
                            </label>
                        </div>

                        {{-- Conditional target pickers — JS toggles the right block
 based on the active reply-type tile. --}}
                        <div id="rt-target-share-contact"
                            class="rt-target hidden mt-4 p-4 rounded-xl border border-paper-200 bg-paper-50/40">
                            <label
                                class="block text-[11.5px] font-semibold text-ink-700 mb-1.5">{{ __('Contact to share') }}</label>
                            <select id="rt-target-contact"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="">— pick a contact —</option>
                                @foreach (\App\Models\Contact::query()->forCurrentWorkspace()->orderBy('name')->limit(500)->get(['id', 'name', 'first_name', 'last_name', 'mobile']) as $ct)
                                    <option value="{{ $ct->id }}">
                                        {{ $ct->name ?: trim(($ct->first_name ?? '') . ' ' . ($ct->last_name ?? '')) ?: mask_phone($ct->mobile) }}
                                        · {{ mask_phone($ct->mobile) }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-ink-500 mt-1.5">
                                {{ __("The customer receives this contact's vCard. They can tap to call or save.") }}
                            </p>
                        </div>

                        <div id="rt-target-send-catalog"
                            class="rt-target hidden mt-4 p-4 rounded-xl border border-paper-200 bg-paper-50/40">
                            <label
                                class="block text-[11.5px] font-semibold text-ink-700 mb-1.5">{{ __('Catalog to send') }}</label>
                            <select id="rt-target-catalog"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                                <option value="">— pick a catalog —</option>
                                @php $wsId = auth()->user()?->current_workspace_id; @endphp
                                @foreach ($wsId
        ? \App\Models\WaCatalog::where('workspace_id', $wsId)->orderBy('catalog_name')->get(['id', 'catalog_name', 'provider'])
        : collect() as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->catalog_name ?: '#' . $cat->id }} ·
                                        {{ strtoupper($cat->provider) }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-ink-500 mt-1.5">
                                {{ __("Customers see a Product List (MPM) bubble. Tap → opens Meta's in-WhatsApp catalog.") }}
                            </p>
                        </div>

                        <div id="rt-target-request-location"
                            class="rt-target hidden mt-4 p-4 rounded-xl border border-paper-200 bg-paper-50/40">
                            <p class="text-[12.5px] text-ink-700">
                                {{ __("No additional config needed — the bot sends WhatsApp's native location-request prompt. The customer's reply will arrive with lat/lng on the message bubble.") }}
                            </p>
                        </div>
                    </div>

                    <!-- Step 4: Compose -->
                    <div class="step-pane hidden" data-step="4">

                        <!-- Custom branch -->
                        <div id="branch-custom">
                            <div class="flex items-center gap-2.5 mb-4">
                                <span
                                    class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                                <span
                                    class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Compose reply') }}</span>
                                <span class="font-mono text-[10px] text-ink-500">{{ __('tabbed') }}</span>
                            </div>

                            <div id="ftabs"
                                class="flex items-center gap-1 mb-4 bg-paper-50/60 rounded-lg p-1 w-fit max-w-full overflow-x-auto">
                                <button type="button"
                                    class="ftab inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-[12px] font-medium bg-wa-deep text-paper-0 shrink-0 whitespace-nowrap"
                                    data-tab="text">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <path d="M3 4h10M3 8h10M3 12h7" />
                                    </svg>{{ __('Text') }}
                                </button>
                                <button type="button"
                                    class="ftab inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-[12px] font-medium text-ink-600 hover:bg-paper-100 shrink-0 whitespace-nowrap"
                                    data-tab="image">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                        <circle cx="6" cy="7" r="1.2" />
                                        <path d="m3 11 3-3 4 4 3-3 0 4" />
                                    </svg>{{ __('Image') }}
                                </button>
                                <button type="button"
                                    class="ftab inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-[12px] font-medium text-ink-600 hover:bg-paper-100 shrink-0 whitespace-nowrap"
                                    data-tab="document">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <path d="M4 2h6l3 3v9H4z" />
                                        <path d="M10 2v3h3" />
                                    </svg>{{ __('Document') }}
                                </button>
                                <button type="button"
                                    class="ftab inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-[12px] font-medium text-ink-600 hover:bg-paper-100 shrink-0 whitespace-nowrap"
                                    data-tab="video">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <rect x="2" y="3" width="9" height="10" rx="1.5" />
                                        <path d="m11 6 3-1v6l-3-1z" />
                                    </svg>{{ __('Video') }}
                                </button>
                                <button type="button"
                                    class="ftab inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-[12px] font-medium text-ink-600 hover:bg-paper-100 shrink-0 whitespace-nowrap"
                                    data-tab="template">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                        stroke="currentColor" stroke-width="1.6">
                                        <rect x="2.5" y="2.5" width="11" height="11" rx="1.5" />
                                        <path d="M2.5 6h11M6 13.5V6" />
                                    </svg>{{ __('Template') }}
                                </button>
                            </div>

                            <!-- Text panel -->
                            <div class="fpanel" data-panel="text">
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block"
                                    for="reply-text">{{ __('Reply text') }}</label>
                                <div class="border border-paper-200 rounded-lg overflow-hidden">
                                    <div
                                        class="flex items-center gap-1 px-2 py-1.5 border-b border-paper-200 bg-paper-50">
                                        <button type="button"
                                            class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] font-bold">B</button>
                                        <button type="button"
                                            class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] italic">I</button>
                                        <button type="button"
                                            class="w-7 h-7 rounded hover:bg-white text-ink-700 inline-flex items-center justify-center text-[11px] line-through">S</button>
                                        <span class="w-px h-4 bg-paper-200 mx-1"></span>
                                        <button type="button"
                                            class="px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] font-mono inline-flex items-center gap-1">@{{ var }}</button>
                                        <button type="button"
                                            class="px-2 h-7 rounded hover:bg-white text-ink-700 text-[10.5px] inline-flex items-center gap-1">{{ __('emoji') }}</button>
                                        <span class="ml-auto text-[10px] font-mono text-ink-500"><span
                                                id="char-count">0</span> / 1024</span>
                                    </div>
                                    <textarea id="reply-text" rows="6" class="w-full px-3 py-2.5 text-[12.5px] focus:outline-none resize-none"
                                        placeholder="Hi @{{ name }}! Thanks for reaching out…"
                                        oninput="document.getElementById('char-count').textContent=this.value.length;document.getElementById('pp-body').textContent=this.value||'your message will appear here…'">Hi @{{ name }}! Thanks for reaching out — happy to help.</textarea>
                                </div>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Use') }} <span
                                        class="font-mono">*bold*</span> · <span class="font-mono">_italic_</span> ·
                                    <span class="font-mono">~strike~</span> · <span
                                        class="font-mono">@{{ name }}</span> for the contact's name.</div>
                            </div>

                            <!-- Image panel -->
                            <div class="fpanel hidden" data-panel="image">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Image') }}</label>
                                <label for="image-file" data-ar-drop="image"
                                    class="block border-2 border-dashed border-paper-200 rounded-lg p-6 text-center hover:border-wa-deep transition cursor-pointer">
                                    <span
                                        class="w-10 h-10 rounded-full bg-paper-50 inline-flex items-center justify-center mb-2">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                            <circle cx="6" cy="7" r="1.2" />
                                            <path d="m3 11 3-3 4 4 3-3 0 4" />
                                        </svg>
                                    </span>
                                    <div class="text-[13px] font-semibold">{{ __('Drop image or') }} <span
                                            class="text-wa-deep">{{ __('browse') }}</span></div>
                                    <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                        {{ __('JPG · PNG · WEBP / max 5 MB') }}</div>
                                </label>
                                <input type="file" id="image-file" data-ar-file="image" class="hidden"
                                    accept="image/jpeg,image/png,image/webp">
                                <div id="image-fname" data-ar-fname="image"
                                    class="hidden mt-1.5 text-[11px] font-mono text-wa-deep break-all"></div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block mt-3"
                                    for="image-cap">{{ __('Caption') }}</label>
                                <textarea id="image-cap" rows="3"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Optional caption shown under the image…') }}"></textarea>
                            </div>

                            <!-- Document panel -->
                            <div class="fpanel hidden" data-panel="document">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Document') }}</label>
                                <label for="document-file" data-ar-drop="document"
                                    class="block border-2 border-dashed border-paper-200 rounded-lg p-6 text-center hover:border-wa-deep transition cursor-pointer">
                                    <span
                                        class="w-10 h-10 rounded-full bg-paper-50 inline-flex items-center justify-center mb-2">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M4 2h6l3 3v9H4z" />
                                            <path d="M10 2v3h3" />
                                        </svg>
                                    </span>
                                    <div class="text-[13px] font-semibold">{{ __('Drop file or') }} <span
                                            class="text-wa-deep">{{ __('browse') }}</span></div>
                                    <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                        {{ __('PDF · DOCX · XLSX / max 100 MB') }}</div>
                                </label>
                                <input type="file" id="document-file" data-ar-file="document" class="hidden"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                                <div id="document-fname" data-ar-fname="document"
                                    class="hidden mt-1.5 text-[11px] font-mono text-wa-deep break-all"></div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block mt-3">{{ __('Filename shown to recipient') }}</label>
                                <input type="text"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('catalogue-2026.pdf') }}">
                            </div>

                            <!-- Video panel -->
                            <div class="fpanel hidden" data-panel="video">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Video') }}</label>
                                <label for="video-file" data-ar-drop="video"
                                    class="block border-2 border-dashed border-paper-200 rounded-lg p-6 text-center hover:border-wa-deep transition cursor-pointer">
                                    <span
                                        class="w-10 h-10 rounded-full bg-paper-50 inline-flex items-center justify-center mb-2">
                                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="3" width="9" height="10" rx="1.5" />
                                            <path d="m11 6 3-1v6l-3-1z" />
                                        </svg>
                                    </span>
                                    <div class="text-[13px] font-semibold">{{ __('Drop video or') }} <span
                                            class="text-wa-deep">{{ __('browse') }}</span></div>
                                    <div class="text-[10.5px] text-ink-500 font-mono mt-1">
                                        {{ __('MP4 · 3GP / max 16 MB · ≤ 30 s') }}</div>
                                </label>
                                <input type="file" id="video-file" data-ar-file="video" class="hidden"
                                    accept="video/mp4,video/3gpp">
                                <div id="video-fname" data-ar-fname="video"
                                    class="hidden mt-1.5 text-[11px] font-mono text-wa-deep break-all"></div>
                                <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block mt-3"
                                    for="video-cap">{{ __('Caption') }}</label>
                                <textarea id="video-cap" rows="3"
                                    class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] resize-none focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Optional caption…') }}"></textarea>
                            </div>

                            <!-- Template panel -->
                            <div class="fpanel hidden" data-panel="template">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Choose approved template') }}</label>
                                {{-- Real approved templates ($templates from the controller),
                                     each a selectable chip. The Compose-step JS reads the chosen
                                     one from [data-tpl-id].border-wa-deep and submits its id as
                                     contents[0][template_id]. Previously this was a hardcoded
                                     2-template mockup with no data-tpl-id, so nothing was ever
                                     selected → template auto-replies saved with template_id=null
                                     and sent nothing. --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" data-tpl-grid>
                                    @forelse ($templates as $t)
                                        @php
                                            // Composed preview text (header + body + footer) — same
                                            // shape renderTemplateReply() flattens for the actual send,
                                            // so the live bubble matches what the customer receives.
                                            $tplPreview = trim(
                                                ($t->header ? $t->header . "\n\n" : '')
                                                . (string) $t->template_body
                                                . ($t->footer ? "\n\n" . $t->footer : '')
                                            );
                                        @endphp
                                        <button type="button" data-tpl-id="{{ $t->id }}"
                                            data-tpl-body="{{ $tplPreview }}"
                                            data-provider-config="{{ $t->provider_config_id }}"
                                            class="text-left border border-paper-200 rounded-lg p-3 hover:border-wa-deep cursor-pointer transition">
                                            <div class="flex items-start justify-between">
                                                <span
                                                    class="font-mono text-[10.5px] text-ink-500">{{ $t->template_name }}@if (($t->provider ?? null) && method_exists($t, 'engineKey') && $t->engineKey() === 'waba') · {{ $t->provider->display_label ?: $t->provider->phone_number }}@endif</span>
                                                <span
                                                    class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-wa-mint text-wa-deep">{{ __('APPROVED') }}</span>
                                            </div>
                                            <div class="text-[12px] mt-2 text-ink-700 line-clamp-3">{{ \Illuminate\Support\Str::limit((string) $t->template_body, 120) }}</div>
                                            @if ($t->category)
                                                <div class="mt-2 text-[10px] font-mono text-ink-500">{{ strtolower((string) $t->category) }}</div>
                                            @endif
                                        </button>
                                    @empty
                                        <div
                                            class="sm:col-span-2 border border-dashed border-paper-200 rounded-lg p-4 text-[12px] text-ink-500">
                                            {{ __('No approved templates yet.') }}
                                            <a href="{{ url('/templates') }}"
                                                class="text-wa-deep font-semibold hover:underline">{{ __('Create one') }}</a>
                                        </div>
                                    @endforelse
                                </div>
                                <a href="{{ url('/templates') }}"
                                    class="mt-3 inline-flex items-center gap-1 text-[12px] text-wa-deep font-semibold hover:underline">{{ __('Browse all templates') }}
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="1.7">
                                        <path d="M6 3l5 5-5 5" />
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <!-- Flow branch -->
                        <div id="branch-flow" class="hidden">
                            <div class="flex items-center gap-2.5 mb-4">
                                <span
                                    class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                                <span
                                    class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Choose flow') }}</span>
                                <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                            </div>

                            <div class="relative mb-3">
                                <svg viewBox="0 0 16 16"
                                    class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                                    fill="none" stroke="currentColor" stroke-width="1.6">
                                    <circle cx="7" cy="7" r="5" />
                                    <path d="m11 11 3 3" />
                                </svg>
                                <input id="ar-flow-search" type="search"
                                    placeholder="{{ __('Search flows by name…') }}"
                                    class="w-full pl-9 pr-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>

                            @if ($flows->isEmpty())
                                <div
                                    class="border border-dashed border-paper-300 rounded-lg p-8 text-center text-[12.5px] text-ink-500 bg-paper-50">
                                    No active flows yet. Build one in the
                                    <a href="{{ url('/flows/builder') }}"
                                        class="text-wa-deep font-semibold hover:underline">{{ __('Flow Builder') }}</a>
                                    and come back here.
                                </div>
                            @else
                                <div id="ar-flow-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach ($flows as $f)
                                        {{-- data-flow MUST be set (not data-flow-id) — the existing
 form-submit JS in user-auto-reply-create.js reads
 `#branch-flow [data-flow].border-wa-deep` to pull
 the selected id. The radio is kept for native
 form-data fallback if anything submits raw. --}}
                                        <label data-flow="{{ $f->id }}"
                                            data-flow-name="{{ Str::lower((string) $f->flow_name) }}"
                                            class="ar-flow-card border border-paper-200 rounded-lg p-4 hover:border-wa-deep cursor-pointer block">
                                            <input type="radio" name="flow_id" value="{{ $f->id }}"
                                                class="hidden ar-flow-radio" />
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-[13px] truncate">
                                                        {{ $f->flow_name ?: 'Flow #' . $f->id }}</div>
                                                    <div class="font-mono text-[10.5px] text-ink-500 mt-0.5">
                                                        #{{ $f->id }} · updated
                                                        {{ optional($f->updated_at)->format('M d') ?: '—' }}
                                                    </div>
                                                </div>
                                                <span
                                                    class="text-[9px] font-mono px-1.5 py-0.5 rounded shrink-0 {{ $f->is_published ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500' }}">
                                                    {{ $f->is_published ? 'PUBLISHED' : 'DRAFT' }}
                                                </span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            <div
                                class="mt-3 text-[12px] text-ink-500 bg-paper-50 border border-paper-200 rounded-lg p-3 leading-snug">
                                <span class="font-semibold text-ink-700">{{ __('Note:') }}</span> Active flows from
                                this workspace are listed above. Build a new one in <a
                                    href="{{ url('/flows/builder') }}"
                                    class="text-wa-deep font-semibold hover:underline">{{ __('Flow Builder') }}</a>.
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Review -->
                    <div class="step-pane hidden" data-step="5">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Review & activate') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('final') }}</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                    {{ __('Trigger') }}</div>
                                <dl class="space-y-1.5 text-[12px]">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Rule name') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-name">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Device') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-dev">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Keywords') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-kw">3</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Match') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-mm">{{ __('Fuzzy 80%') }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="border border-paper-200 rounded-lg p-3 bg-paper-50/40">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                    {{ __('Schedule') }}</div>
                                <dl class="space-y-1.5 text-[12px]">
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Cooldown') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-cd">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Delay') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-td">—</dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-ink-500">{{ __('Reply type') }}</dt>
                                        <dd class="font-mono text-ink-900" id="rv-rt">{{ __('Custom · Text') }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between bg-paper-50/60 border border-paper-200 rounded-lg p-3">
                            <div>
                                <div class="text-[13px] font-semibold">{{ __('Activate immediately') }}</div>
                                <div class="text-[11px] text-ink-500">
                                    {{ __('Rule starts matching incoming messages as soon as you save.') }}</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" checked>
                                <span
                                    class="w-10 h-[22px] rounded-full bg-paper-200 peer-checked:bg-wa-deep transition-colors"></span>
                                <span
                                    class="absolute top-0.5 left-0.5 w-[18px] h-[18px] rounded-full bg-paper-0 shadow transition-transform peer-checked:translate-x-[18px]"></span>
                            </label>
                        </div>
                    </div>

                </div><!-- /step body -->

                <!-- Step nav -->
                <div class="px-5 py-4 flex items-center justify-between bg-paper-50/40 border-t border-paper-200">
                    <button type="button" id="btn-prev"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium text-ink-700 inline-flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M10 4l-4 4 4 4" />
                        </svg>
                        Previous
                    </button>
                    <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span
                            id="cur-step">1</span> of 5</div>
                    <button type="button" id="btn-next"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                        Next
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M6 4l4 4-4 4" />
                        </svg>
                    </button>
                    <button type="submit" id="btn-submit"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold hidden items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M2 8l5 5 7-9" />
                        </svg>
                        Activate rule
                    </button>
                </div>

            </div>

            <!-- Right preview rail -->
            <aside class="space-y-4 xl:sticky xl:top-[78px] xl:self-start">
                <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-4 py-3 border-b border-paper-200 flex items-center justify-between">
                        <span
                            class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Live preview') }}</span>
                        <span class="font-mono text-[10px] text-wa-deep">{{ __('WhatsApp') }}</span>
                    </div>
                    <div class="p-4 bg-paper-50/40">
                        <div
                            class="mx-auto w-[260px] rounded-[24px] border-[6px] border-ink-900 overflow-hidden bg-paper-0 shadow-soft">
                            <div class="bg-wa-deep px-3 py-2 flex items-center gap-2">
                                <span
                                    class="w-7 h-7 rounded-full bg-wa-teal text-paper-0 grid place-items-center text-[11px] font-semibold">B</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12px] text-paper-0 font-semibold leading-tight truncate">
                                        {{ __('Brand Inc.') }}</div>
                                    <div class="text-[9.5px] text-paper-0/70 leading-tight">
                                        {{ __('online · auto reply') }}</div>
                                </div>
                            </div>
                            <div
                                class="p-3 bg-wa-chat min-h-[260px] [background-image:radial-gradient(rgba(7,94,84,0.06)_1px,transparent_1px)] bg-[length:14px_14px]">
                                <div
                                    class="bg-wa-mint rounded-[7px] rounded-tr-[2px] px-2.5 py-1.5 max-w-[78%] ml-auto shadow-[0_1px_1px_rgba(0,0,0,0.06)] mb-1.5 text-[12px] leading-snug">
                                    hi
                                    <div class="text-[9px] text-ink-500 text-right mt-1 font-mono">14:07</div>
                                </div>
                                <div
                                    class="bg-paper-0 rounded-[7px] rounded-tl-[2px] px-2.5 py-1.5 max-w-[88%] shadow-[0_1px_1px_rgba(0,0,0,0.06)] text-[12px] leading-snug">
                                    <div id="pp-body">Hi @{{ name }}! Thanks for reaching out — happy to
                                        help.</div>
                                    <div class="text-[9px] text-ink-500 text-right mt-1 font-mono">14:07</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </aside>

        </form>
    </section>

    {{-- Edit-mode payload. Empty/null when creating a fresh rule.
 The page-module JS reads this to pre-fill the multi-step
 form and switch the submit handler from POST to PATCH. --}}
    <script>
        window.WA_AUTOREPLY_ROW = @json($editPayload);
    </script>

    {{-- Step-4 flow picker: search-by-name filter + visual selection state.
 The radio inside each card carries the flow_id the form submits;
 this script only handles UX (hide/show, highlight, edit-mode pre-select). --}}
    <script>
        (function() {
            const search = document.getElementById('ar-flow-search');
            const list = document.getElementById('ar-flow-list');
            if (!list) return;
            const cards = Array.from(list.querySelectorAll('.ar-flow-card'));

            // Filter
            if (search) {
                search.addEventListener('input', () => {
                    const q = (search.value || '').trim().toLowerCase();
                    cards.forEach(c => {
                        const hit = !q || (c.dataset.flowName || '').includes(q);
                        c.style.display = hit ? '' : 'none';
                    });
                });
            }

            // Selection highlight
            const paint = () => cards.forEach(c => {
                const on = c.querySelector('.ar-flow-radio')?.checked;
                c.classList.toggle('border-wa-deep', !!on);
                c.classList.toggle('bg-wa-mint/20', !!on);
                c.classList.toggle('ring-2', !!on);
                c.classList.toggle('ring-wa-deep/30', !!on);
            });
            cards.forEach(c => c.addEventListener('click', () => {
                cards.forEach(x => {
                    const r = x.querySelector('.ar-flow-radio');
                    if (r) r.checked = false;
                });
                const r = c.querySelector('.ar-flow-radio');
                if (r) r.checked = true;
                paint();
            }));

            // Edit-mode pre-select from the controller-injected row.
            const preId = window.WA_AUTOREPLY_ROW?.flow_id;
            if (preId) {
                const card = cards.find(c => String(c.dataset.flow) === String(preId));
                if (card) {
                    const r = card.querySelector('.ar-flow-radio');
                    if (r) r.checked = true;
                }
            }
            paint();
        })();
    </script>

</x-layouts.user>
