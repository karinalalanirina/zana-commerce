<x-layouts.user :title="__('Flow Builder')" nav-key="flows" page="user-flows-index">
    @php
        $flows = $flows ?? collect();
        $statusCounts = $statusCounts ?? ['all' => 0, 'live' => 0, 'paused' => 0, 'draft' => 0];
        $categoryCounts = $categoryCounts ?? ['all' => 0];
        $currentStatus = $currentStatus ?? 'all';
        $currentCategory = $currentCategory ?? 'all';
        $currentQuery = $currentQuery ?? '';
    @endphp
    <div data-fl-state data-fl-status="{{ $currentStatus }}" data-fl-category="{{ $currentCategory }}"
        data-fl-search="{{ $currentQuery }}"
        data-fl-page="{{ method_exists($flows, 'currentPage') ? $flows->currentPage() : 1 }}">

        <!-- ========== TOP BAR ========== -->


        <!-- ========== BODY ========== -->
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
            <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">

                <!-- ===== LEFT RAIL ===== -->
                @php
                    $libraryEntries = [
                        'all' => ['label' => 'All flows', 'icon' => 'M3 4h10M3 8h10M3 12h6'],
                        'cart' => ['label' => 'Cart abandonment', 'icon' => 'M3 5h8l1 6H4z'],
                        'welcome' => [
                            'label' => 'Welcome',
                            'icon' => 'M3 11s2-1 5-1 5 1 5 1M5 6.5h.01M11 6.5h.01M8 9.5s1 .8 0 1.5',
                        ],
                        'post-purchase' => ['label' => 'Post-purchase', 'icon' => 'M4 13V7l4-4 4 4v6M3 13h10'],
                        're-engagement' => [
                            'label' => 'Re-engagement',
                            'icon' => 'M11 4H5a3 3 0 0 0 0 6h6a3 3 0 0 1 0 6H5M5 4l-2 2 2 2M11 16l2-2-2-2',
                        ],
                        'event' => ['label' => 'Special events', 'icon' => 'M2 3h12v11H2zM2 6h12M5 1v3M11 1v3'],
                        'lead' => [
                            'label' => 'Lead nurture',
                            'icon' => 'M5 6a3 3 0 1 0 0-0.01M2 14c0-3 3-4 6-4s6 1 6 4',
                        ],
                    ];
                @endphp
                <aside class="lg:sticky lg:top-6 self-start space-y-3">
                    <x-side-tip>
                        {{ __('Flows automate replies the moment a customer messages a connected number. Start from a Library preset or build your own — every active flow triggers across all paired devices.') }}
                    </x-side-tip>

                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div
                            class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Library') }}</div>
                        @foreach ($libraryEntries as $key => $entry)
                            @php $active = $currentCategory === $key; @endphp
                            <button type="button" data-fl-filter="category" data-fl-value="{{ $key }}"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                                <span class="flex items-center gap-2">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path d="{{ $entry['icon'] }}" />
                                    </svg>
                                    {{ $entry['label'] }}
                                </span>
                                <span class="mono font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}"
                                    data-fl-cat-count="{{ $key }}">{{ $categoryCounts[$key] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 shadow-card">
                        <div
                            class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 px-3 pt-2 pb-1.5">
                            {{ __('Status') }}</div>
                        @foreach ([
        'live' => ['label' => 'Live', 'dot' => 'bg-wa-green'],
        'paused' => ['label' => 'Paused', 'dot' => 'bg-[#5B3D8A]'],
        'draft' => ['label' => 'Draft', 'dot' => 'bg-paper-200'],
    ] as $key => $entry)
                            @php $active = $currentStatus === $key; @endphp
                            <button type="button" data-fl-filter="status" data-fl-value="{{ $key }}"
                                class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-[13px] {{ $active ? 'bg-wa-deep text-paper-0 font-medium' : 'text-ink-700 hover:bg-paper-50' }}">
                                <span class="flex items-center gap-2"><span
                                        class="w-2 h-2 rounded-full {{ $entry['dot'] }}"></span>{{ $entry['label'] }}</span>
                                <span class="mono font-mono text-[11px] {{ $active ? 'opacity-90' : 'text-ink-500' }}"
                                    data-fl-status-count="{{ $key }}">{{ $statusCounts[$key] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div
                        class="hairline border border-paper-200 rounded-2xl bg-wa-bubble/40 p-3 text-[11px] text-ink-700 leading-relaxed">
                        <div class="font-semibold text-ink-900 mb-1 flex items-center gap-1.5">
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor">
                                <circle cx="8" cy="8" r="6" />
                            </svg>
                            {{ __('Tip') }}
                        </div>
                        {{ __("Start with a Welcome flow first — it converts ~3× better than re-engagement, and you'll learn what your audience replies to.") }}
                    </div>
                </aside>

                <!-- ===== MAIN COLUMN ===== -->
                <main>
                    <!-- Heading -->
                    <div class="flex items-end justify-between mb-5 flex-wrap gap-3">
                        <div>
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                                {{ __('Workspace') }} · {{ auth()->user()?->currentWorkspace?->name ?: __('Workspace') }}</div>
                            <h1
                                class="serif font-serif font-normal tracking-[-0.01em] text-[32px] sm:text-[38px] lg:text-[44px] leading-[1.05] tracking-tight">
                                {{ __('Automated') }} <span class="italic text-wa-deep">{{ __('flows') }}</span>.
                            </h1>
                            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                                {{ __('Build reusable WhatsApp workflows — triggered by events, branched by replies, and measured end-to-end.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Opens the builder with the "Generate with AI" modal already
                                 open — the builder reads ?ai=1 on mount (useState regex
                                 /[?&]ai(_prompt)?=/). Was a dead <button> with no handler. --}}
                            <a href="{{ url('/flows/builder?ai=1') }}"
                                class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="currentColor">
                                    <path d="M8 1l1.5 4L14 6.5 9.5 8 8 12 6.5 8 2 6.5 6.5 5z" />
                                </svg>
                                {{ __('Use AI prompt') }}
                            </a>
                            <a href="{{ url('/flows/builder') }}"
                                class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M8 3v10M3 8h10" />
                                </svg>
                                {{ __('Create blank flow') }}
                            </a>
                            {{-- Call flow: same builder, ?type=call swaps the palette to
                                 the AI-voice nodes (Answer/Listen/AI Respond/Hang up). --}}
                            <a href="{{ url('/flows/builder?type=call') }}"
                                class="px-4 py-2 rounded-full border border-wa-deep text-wa-deep hover:bg-wa-deep/5 text-[12px] font-semibold flex items-center gap-2">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path d="M3 4c0 5 4 9 9 9l1.5-2.5-3-1.5-1 1A7 7 0 0 1 6 6l1-1L5.5 2 3 3.5z" />
                                </svg>
                                {{ __('New Call Flow') }}
                            </a>
                            {{-- Import a flow from an exported .json — auto-submits on file pick. --}}
                            <form method="POST" action="{{ route('user.flows.import') }}" enctype="multipart/form-data" class="inline">
                                @csrf
                                <label class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2 cursor-pointer"
                                    title="{{ __('Import a flow from a .json file') }}">
                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M8 2v7M5 6l3 3 3-3" /><path d="M2.5 11v1.5a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1V11" />
                                    </svg>
                                    {{ __('Import') }}
                                    <input type="file" name="file" accept="application/json,.json" class="hidden" onchange="this.form.submit()" />
                                </label>
                            </form>
                        </div>
                    </div>

                    @if ($errors->has('file'))
                        <div class="mb-4 rounded-xl border border-accent-coral/40 bg-accent-coral/10 px-4 py-2.5 text-[12px] text-[#A1431F]">{{ $errors->first('file') }}</div>
                    @endif

                    <!-- Stat row -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                        <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Active flows') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="live">
                                {{ $statusCounts['live'] }}</div>
                            <div class="text-[11px] text-wa-deep mt-2">{{ __('running now') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Total flows') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="all">
                                {{ $statusCounts['all'] }}</div>
                            <div class="text-[11px] text-wa-deep mt-2">{{ __('in this workspace') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Drafts') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="draft">
                                {{ $statusCounts['draft'] }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('unpublished') }}</div>
                        </div>
                        <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                            <div class="text-[11px] text-ink-600 font-medium">{{ __('Paused') }}</div>
                            <div class="font-serif text-[34px] leading-none mt-1" data-fl-stat="paused">
                                {{ $statusCounts['paused'] }}</div>
                            <div class="text-[11px] text-ink-500 mt-2">{{ __('temporarily off') }}</div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="hairline-b border-b border-paper-200 flex items-center gap-x-6 gap-y-2 flex-wrap px-2 mb-5">
                        @foreach (['all' => 'All flows', 'live' => 'Active', 'paused' => 'Paused', 'draft' => 'Drafts'] as $key => $label)
                            <button type="button" data-fl-filter="status" data-fl-value="{{ $key }}"
                                class="tab-line inline-flex items-center gap-2 py-3.5 text-[14px] cursor-pointer bg-transparent border-0 border-b-2 transition whitespace-nowrap {{ $currentStatus === $key ? 'text-wa-deep font-semibold border-wa-deep' : 'text-ink-600 hover:text-ink-900 border-transparent' }}">
                                {{ $label }} <span
                                    class="count text-[10px] px-1.5 py-px rounded-full bg-paper-100 text-ink-600 font-mono"
                                    data-fl-status-count="{{ $key }}">{{ $statusCounts[$key] ?? 0 }}</span>
                            </button>
                        @endforeach
                        <div class="flex-1"></div>
                        <div class="relative w-full sm:w-auto">
                            <svg viewBox="0 0 16 16"
                                class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none"
                                stroke="currentColor" stroke-width="1.5">
                                <circle cx="7" cy="7" r="5" />
                                <path d="m11 11 3 3" />
                            </svg>
                            <input id="fl-search" type="search" value="{{ $currentQuery }}"
                                placeholder="{{ __('Search flows...') }}"
                                class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full sm:w-64 focus:outline-none focus:border-wa-deep" />
                        </div>
                    </div>

                    <!-- ===== FEATURED FLOW (most-used) ===== -->
                    <div id="fl-featured">
                        @if (!empty($featured))
                            @include('user.flows._featured', ['featured' => $featured])
                        @endif
                    </div>

                    {{-- ===== START FROM A TEMPLATE (admin-curated) ===== --}}
                    @if (isset($flowTemplates) && count($flowTemplates))
                        @php
                            $tplTypeBadge = [
                                'chat' => ['Chat', 'bg-wa-mint text-wa-deep'],
                                'call' => ['Call', 'bg-accent-amber/15 text-[#7B5A14]'],
                            ];
                        @endphp
                        <section class="mb-6">
                            <div class="mb-3">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Starter templates') }}</div>
                                <h2 class="font-serif text-[20px] leading-tight">{{ __('Start from a template') }}</h2>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                @foreach ($flowTemplates as $tpl)
                                    @php $tb = $tplTypeBadge[$tpl->flow_type] ?? ['—', 'bg-paper-100 text-ink-500']; @endphp
                                    <div class="bg-paper-0 border border-paper-200 rounded-[14px] p-4 shadow-card flex flex-col hover:border-wa-deep hover:shadow-soft transition">
                                        <div class="flex items-start justify-between gap-2 mb-1.5">
                                            <span class="font-semibold text-[13px] text-ink-900 break-words">{{ $tpl->name }}</span>
                                            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $tb[1] }}">{{ $tb[0] }}</span>
                                        </div>
                                        <p class="text-[12px] text-ink-500 leading-snug flex-1">{{ $tpl->description ?: __('A ready-made flow you can customise.') }}</p>
                                        <div class="flex items-center justify-between gap-2 mt-3">
                                            <span class="font-mono text-[10.5px] text-ink-500">{{ $tpl->node_count }} {{ __('steps') }}@if ($tpl->category) · {{ $tpl->category }}@endif</span>
                                            <form method="POST" action="{{ route('user.flows.templates.clone', $tpl->id) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold hover:bg-wa-teal inline-flex items-center gap-1.5">
                                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="5" width="8" height="8" rx="1.5" /><path d="M3 10.5H2.5a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1H9a1 1 0 0 1 1 1v.5" /></svg>
                                                    {{ __('Use template') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <!-- ===== FLOW GRID ===== -->
                    <div id="fl-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @include('user.flows._cards', ['flows' => $flows])
                    </div>
                    <div id="fl-pagination">
                        @include('user.partials.pagination', [
                            'paginator' => $flows,
                            'dataAttr' => 'data-fl-page',
                            'label' => 'flows',
                        ])
                    </div>

                    <!-- ===== HELP / FAQ ===== -->
                    <div class="mt-7 grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help · 01') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('What is an automated workflow?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('A sequence of messages, waits, and decision branches that starts from a trigger like signup, cart abandonment, or purchase completion.') }}
                            </p>
                        </div>
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help · 02') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('How do I optimize my flows?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('Keep the opening step short, branch by intent early, and measure drop-off at each node so you know where conversion slips.') }}
                            </p>
                        </div>
                        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                                {{ __('Help · 03') }}</div>
                            <div class="serif font-serif font-normal tracking-[-0.01em] text-[20px] mb-1">
                                {{ __('Which flows should I launch first?') }}</div>
                            <p class="text-[12.5px] text-ink-600 leading-relaxed">
                                {{ __('For most stores: welcome → cart recovery → post-purchase review. That covers acquisition, conversion, and retention with minimum overhead.') }}
                            </p>
                        </div>
                    </div>

                    <!-- footer -->
                    <div id="fl-results-footer"
                        class="mt-6 text-[11px] text-ink-500 mono font-mono text-center {{ (method_exists($flows, 'total') ? $flows->total() : $statusCounts['all'] ?? 0) > 0 ? '' : 'hidden' }}">
                        Showing <span data-fl-shown>{{ $flows->count() }}</span> of <span
                            data-fl-total>{{ method_exists($flows, 'total') ? number_format($flows->total()) : number_format($statusCounts['all']) }}</span>
                        filtered flows
                    </div>

                </main>
            </div>
        </div>
    </div>

</x-layouts.user>
