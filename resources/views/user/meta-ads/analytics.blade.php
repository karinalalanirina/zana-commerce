@php
    /** @var \App\Models\MetaCampaign|null $campaign */
    /** @var \Illuminate\Support\Collection $picker */
    /** @var array|null $aggregate */
    $picker = $picker ?? collect();
    $mode = $mode ?? ($campaign ? 'campaign' : 'global');

    if ($campaign) {
        $m = $campaign->metrics;
        $impressions = $m['impressions'];
        $reach = $m['reach'];
        $clicks = $m['clicks'];
        $spend = $m['spend'];
        $revenue = $m['revenue'];
        $conv = $m['conversions'];
        $cpc = $m['cpc'];
        $ctr = $m['ctr'];
        $roas = $spend > 0 ? round($revenue / $spend, 2) : 0;
        $cpl = $conv > 0 ? round($spend / $conv, 2) : 0;
        $reachPct = $impressions ? round(($reach / max($impressions, 1)) * 100, 1) : 0;
        $clickPct = $impressions ? round(($clicks / max($impressions, 1)) * 100, 2) : 0;
        $waStarts = (int) round($clicks * 0.29); // estimated when not synced from Meta
        $waPctClicks = $clicks ? round(($waStarts / max($clicks, 1)) * 100, 1) : 0;
        $convPctWa = $waStarts ? round(($conv / max($waStarts, 1)) * 100, 1) : 0;
        $isActive = $campaign->status === 'ACTIVE';
        $statusLbl = ucfirst(strtolower($campaign->status));
        $createdLbl = $campaign->created_at?->format('M d, Y');
        $updatedLbl = $campaign->updated_at?->format('M d, Y');
    } else {
        $impressions = $reach = $clicks = $spend = $revenue = $conv = 0;
        $cpc = $ctr = $roas = $cpl = $reachPct = $clickPct = 0;
        $waStarts = $waPctClicks = $convPctWa = 0;
        $isActive = false;
        $statusLbl = '—';
        $createdLbl = $updatedLbl = '—';
    }

    $fmt = fn($n, $d = 0) => number_format($n, $d);
    $kfmt = function ($n) {
        if ($n >= 1_000_000) {
            return number_format($n / 1_000_000, 1) . 'm';
        }
        if ($n >= 1_000) {
            return number_format($n / 1_000, 1) . 'k';
        }
        return number_format($n);
    };
@endphp

<x-layouts.user :title="__('Meta Ads Analytics')" nav-key="metaads" page="user-meta-ads-analytics">

    <div class="border-b border-paper-200 bg-paper-0 sticky top-0 z-20">
        <div class="max-w-none mx-auto px-4 sm:px-7 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ url('/meta-ads') }}"
                    class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                    title="{{ __('Back to Meta Ads') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                        <path d="M10 4l-4 4 4 4" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        @if ($mode === 'campaign')
                            Meta Ads /
                            Analytics{{ $campaign ? ' / ' . ($campaign->facebook_id ?: '#' . $campaign->id) : '' }}
                        @else
                            Meta Ads / Analytics / Workspace
                        @endif
                    </div>
                    <div class="font-serif text-[20px] leading-tight truncate">
                        {{ $mode === 'campaign' ? ($campaign ? $campaign->name : 'Campaign not found') : 'All campaigns' }}
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Always allow jumping to global view from campaign mode --}}
                @if ($mode === 'campaign')
                    <a href="{{ route('user.meta-ads.analytics') }}"
                        class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Workspace view') }}</a>
                @endif

                @if ($picker->count())
                    <form method="GET" class="inline-block">
                        <select name="id" onchange="this.form.submit()"
                            class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                            @if ($mode === 'global')
                                <option value="">{{ __('Pick a campaign for drill-down…') }}</option>
                            @endif
                            @foreach ($picker as $p)
                                <option value="{{ $p->id }}" @selected($campaign && $campaign->id === $p->id)>{{ $p->name }} —
                                    {{ ucfirst(strtolower($p->status)) }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if ($mode === 'campaign' && $campaign)
                    <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $isActive ? 'bg-wa-green/10 text-wa-deep border border-wa-green/30' : 'bg-paper-50 text-ink-700 border border-paper-200' }}">
                        <span
                            class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-wa-green' : 'bg-paper-300' }}"></span>{{ $statusLbl }}
                    </span>
                    <a href="{{ route('user.meta-ads.edit', $campaign->id) }}"
                        class="px-3.5 py-1.5 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                        </svg>
                        Edit
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if ($mode === 'global')
        @include('user.meta-ads._analytics-global', ['aggregate' => $aggregate, 'picker' => $picker])
    @elseif (!$campaign)
        <main class="max-w-none mx-auto px-4 sm:px-7 py-12">
            <div
                class="rounded-2xl border border-dashed border-paper-200 bg-paper-0 p-12 text-center max-w-2xl mx-auto">
                <div class="font-serif text-[28px] leading-tight">{{ __('Campaign not found') }}</div>
                <p class="mt-2 text-[13px] text-ink-500 max-w-md mx-auto">
                    {{ __('It may have been deleted. Go back to the workspace view or pick another campaign above.') }}
                </p>
                <div class="mt-5 flex items-center justify-center gap-2">
                    <a href="{{ route('user.meta-ads.analytics') }}"
                        class="px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-ink-800">{{ __('Workspace analytics') }}</a>
                    <a href="{{ url('/meta-ads') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Back to Meta Ads') }}</a>
                </div>
            </div>
        </main>
    @else
        <main class="max-w-none mx-auto px-4 sm:px-7 py-6 space-y-5">
            <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-6 py-5 border-b border-paper-200 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
                    <div>
                        <div
                            class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500">
                            <span>{{ __('Meta Ads campaign') }}</span>
                            <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                            <span>{{ $createdLbl }}{{ $createdLbl !== $updatedLbl ? ' to ' . $updatedLbl : '' }}</span>
                        </div>
                        <h1 class="font-serif text-[28px] sm:text-[34px] lg:text-[40px] leading-none mt-2">{{ $campaign->name }} <span
                                class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                            {{ __('Ad spend, CTR, WhatsApp conversations, lead quality, revenue attribution, and creative performance for this campaign.') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full lg:w-[520px]">
                        <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                            <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ __('ROAS') }}</div>
                            <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">{{ $fmt($roas, 2) }}x
                            </div>
                        </div>
                        <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                            <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Spend') }}</div>
                            <div class="font-serif text-[28px] leading-none mt-1">${{ $kfmt($spend) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                            <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Revenue') }}</div>
                            <div class="font-serif text-[28px] leading-none mt-1">${{ $kfmt($revenue) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                            <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Leads') }}</div>
                            <div class="font-serif text-[28px] leading-none mt-1">{{ $fmt($conv) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                            <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ __('CPL') }}</div>
                            <div class="font-serif text-[28px] leading-none mt-1">${{ $fmt($cpl, 2) }}</div>
                        </div>
                        <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                            <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                {{ __('Status') }}</div>
                            <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">{{ $statusLbl }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-3 flex items-center gap-1 border-b border-paper-200 bg-white overflow-x-auto">
                    <button type="button" data-tab="overview"
                        class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold transition bg-wa-deep text-paper-0">{{ __('Overview') }}</button>
                    <button type="button" data-tab="ads"
                        class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Ads & sets') }}</button>
                    <button type="button" data-tab="audience"
                        class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Audience') }}</button>
                    <button type="button" data-tab="attribution"
                        class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Attribution') }}</button>
                    <button type="button" data-tab="events"
                        class="tab-btn shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50 transition">{{ __('Events') }}</button>
                </div>
            </section>

            <section data-panel="overview" class="tab-panel space-y-5">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="text-[12px] text-ink-600">{{ __('Impressions') }}</div>
                        <div class="font-serif text-[38px] leading-none mt-2">{{ $kfmt($impressions) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ $statusLbl }} {{ __('delivery') }}</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="text-[12px] text-ink-600">{{ __('Reach') }}</div>
                        <div class="font-serif text-[38px] leading-none mt-2">{{ $kfmt($reach) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">
                            {{ $impressions ? $fmt($impressions / max($reach, 1), 2) : '0.00' }}× frequency</div>
                    </div>
                    <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                        <div class="text-[12px] text-ink-600">{{ __('Clicks') }}</div>
                        <div class="font-serif text-[38px] leading-none mt-2">{{ $fmt($clicks) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">{{ $fmt($ctr, 2) }}% CTR</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="text-[12px] text-ink-600">{{ __('WhatsApp starts') }}</div>
                        <div class="font-serif text-[38px] leading-none mt-2">{{ $fmt($waStarts) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ $fmt($waPctClicks, 1) }}% of clicks</div>
                    </div>
                    <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                        <div class="text-[12px] text-ink-600">{{ __('Qualified leads') }}</div>
                        <div class="font-serif text-[38px] leading-none mt-2">{{ $fmt($conv) }}</div>
                        <div class="text-[11px] text-ink-500 mt-2">{{ $fmt($convPctWa, 1) }}% of chats</div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="text-[12px] text-ink-600">{{ __('CPC') }}</div>
                        <div class="font-serif text-[38px] leading-none mt-2">${{ $fmt($cpc, 2) }}</div>
                        <div class="text-[11px] text-wa-deep mt-2">${{ $fmt($cpl, 2) }} {{ __('per lead') }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Daily trend') }}</div>
                                <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Spend, clicks, leads') }}
                                </h2>
                            </div>
                            <div class="flex items-center gap-3 text-[11px] text-ink-500">
                                <span class="flex items-center gap-1.5"><span
                                        class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Spend</span>
                                <span class="flex items-center gap-1.5"><span
                                        class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Clicks</span>
                                <span class="flex items-center gap-1.5"><span
                                        class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>Leads</span>
                            </div>
                        </div>
                        <div id="chart-trend" data-spend="{{ $spend }}" data-clicks="{{ $clicks }}"
                            data-leads="{{ $conv }}"></div>
                    </div>
                    <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Outcome mix') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Click outcomes') }}</h2>
                        <div id="chart-outcomes" class="mt-2" data-clicks="{{ $clicks }}"
                            data-wa="{{ $waStarts }}" data-leads="{{ $conv }}"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Funnel') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('From impression to lead') }}
                        </h2>
                        @php
                            $funnel = [
                                ['Impressions', $impressions, 100],
                                ['Reach', $reach, $reachPct],
                                ['Clicks', $clicks, $clickPct * 10],
                                ['WhatsApp starts', $waStarts, $waPctClicks],
                                ['Qualified leads', $conv, $convPctWa],
                            ];
                            $colors = ['bg-wa-deep', 'bg-wa-deep', 'bg-wa-teal', 'bg-accent-amber', 'bg-accent-coral'];
                        @endphp
                        <div class="space-y-3">
                            @foreach ($funnel as $i => [$lbl, $val, $pct])
                                @php
                                    $base = $impressions ?: 1;
                                    $widthPct = max(2, min(100, ($val / $base) * 100));
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between text-[12px] mb-1">
                                        <span>{{ $lbl }}</span><span class="font-mono">{{ $fmt($val) }} /
                                            {{ $fmt($pct, 1) }}%</span></div>
                                    <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                        <div class="h-full {{ $colors[$i] }}" style="width:{{ $widthPct }}%">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Targeting') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1 mb-3">{{ __('Audience setup') }}</h2>
                        @php
                            $targeting = is_array($campaign->targeting) ? $campaign->targeting : [];
                            $countries = is_array($targeting['countries'] ?? null)
                                ? implode(', ', $targeting['countries'])
                                : '—';
                            $interests = is_array($targeting['interests'] ?? null)
                                ? implode(', ', $targeting['interests'])
                                : '—';
                            $ageR =
                                isset($targeting['age_min']) && isset($targeting['age_max'])
                                    ? $targeting['age_min'] . '–' . $targeting['age_max']
                                    : '—';
                            $gender = $targeting['gender'] ?? '—';
                        @endphp
                        <div class="space-y-2 text-[12.5px]">
                            <div class="flex justify-between"><span
                                    class="text-ink-500">{{ __('Countries') }}</span><span
                                    class="font-mono">{{ $countries }}</span></div>
                            <div class="flex justify-between"><span
                                    class="text-ink-500">{{ __('Age range') }}</span><span
                                    class="font-mono">{{ $ageR }}</span></div>
                            <div class="flex justify-between"><span
                                    class="text-ink-500">{{ __('Gender') }}</span><span
                                    class="font-mono">{{ $gender ?: 'all' }}</span></div>
                            <div class="flex justify-between gap-3"><span
                                    class="text-ink-500 shrink-0">{{ __('Interests') }}</span><span
                                    class="text-right">{{ $interests }}</span></div>
                        </div>
                    </div>
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recommendations') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('Next action') }}</h2>
                        <div class="space-y-3">
                            @if ($roas >= 5 && $isActive)
                                <div class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3">
                                    <div class="text-[13px] font-semibold">{{ __('Scale this campaign') }}</div>
                                    <div class="text-[11.5px] text-ink-600 mt-1">ROAS is {{ $fmt($roas, 1) }}× —
                                        consider raising daily budget by 20%.</div>
                                </div>
                            @elseif ($conv === 0 && $clicks > 100)
                                <div class="rounded-xl border border-accent-coral/30 bg-accent-coral/10 p-3">
                                    <div class="text-[13px] font-semibold">{{ __('No conversions despite traffic') }}
                                    </div>
                                    <div class="text-[11.5px] text-ink-600 mt-1">{{ $fmt($clicks) }} clicks but zero
                                        leads. Check landing page or CTWA flow.</div>
                                </div>
                            @elseif ($cpc > 0.5)
                                <div class="rounded-xl border border-accent-amber/40 bg-accent-amber/10 p-3">
                                    <div class="text-[13px] font-semibold">{{ __('CPC is high') }}</div>
                                    <div class="text-[11.5px] text-ink-600 mt-1">${{ $fmt($cpc, 2) }} per click — try
                                        refining the audience or rotating creative.</div>
                                </div>
                            @else
                                <div class="rounded-xl border border-paper-200 p-3">
                                    <div class="text-[13px] font-semibold">{{ __('Keep monitoring') }}</div>
                                    <div class="text-[11.5px] text-ink-600 mt-1">
                                        {{ __('Performance is within target. Sync again after 24h to compare.') }}
                                    </div>
                                </div>
                            @endif

                            <form action="{{ route('user.meta-ads.toggle', $campaign->id) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="w-full rounded-xl border border-paper-200 p-3 text-left hover:border-wa-deep hover:bg-paper-50">
                                    <div class="text-[13px] font-semibold">
                                        {{ $isActive ? 'Pause this campaign' : 'Activate this campaign' }}</div>
                                    <div class="text-[11.5px] text-ink-500 mt-1">Currently
                                        {{ strtolower($statusLbl) }}.</div>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <section data-panel="ads" class="tab-panel hidden space-y-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Ad set performance') }}</div>
                            <h2 class="font-serif text-[24px] leading-tight mt-1">{{ $campaign->ad_set_count }} ad
                                set(s) · {{ $campaign->ad_count }} ad(s)</h2>
                        </div>
                        <span
                            class="px-3 py-1.5 rounded-full bg-paper-50 text-[11px] font-mono text-ink-500">{{ __('Detailed ad-set breakdown lands when the Meta Graph sync is wired.') }}</span>
                    </div>
                    <div class="p-10 text-center">
                        <div class="text-[13px] text-ink-500">This campaign has {{ $campaign->ad_set_count }} ad
                            set(s) on Meta. The per-set breakdown table renders once
                            <a href="{{ url('/connect') }}"
                                class="text-wa-deep underline">{{ __('a Meta account') }}</a>
                            is connected and the next sync runs.
                        </div>
                    </div>
                </div>
            </section>

            <section data-panel="audience" class="tab-panel hidden space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <div class="lg:col-span-7 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Audience response') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Targeting summary') }}</h2>
                        <div class="mt-4 space-y-3 text-[13px]">
                            <div class="flex justify-between"><span
                                    class="text-ink-500">{{ __('Countries') }}</span><span
                                    class="font-mono">{{ $countries }}</span></div>
                            <div class="flex justify-between"><span
                                    class="text-ink-500">{{ __('Age range') }}</span><span
                                    class="font-mono">{{ $ageR }}</span></div>
                            <div class="flex justify-between"><span
                                    class="text-ink-500">{{ __('Gender') }}</span><span
                                    class="font-mono">{{ $gender ?: 'all' }}</span></div>
                            <div class="flex justify-between gap-3"><span
                                    class="text-ink-500 shrink-0">{{ __('Interests') }}</span><span
                                    class="text-right max-w-[60%]">{{ $interests }}</span></div>
                        </div>
                    </div>
                    <div class="lg:col-span-5 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Geography') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1 mb-4">{{ __('Top countries') }}</h2>
                        @if (is_array($targeting['countries'] ?? null) && count($targeting['countries']))
                            <div class="space-y-3">
                                @foreach ($targeting['countries'] as $i => $cc)
                                    @php
                                        $share = max(15, 100 - $i * 25);
                                    @endphp
                                    <div>
                                        <div class="flex justify-between text-[12px] mb-1">
                                            <span>{{ $cc }}</span><span class="font-mono">target
                                                #{{ $i + 1 }}</span></div>
                                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-wa-deep" style="width:{{ $share }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-[12.5px] text-ink-500">
                                {{ __('No country targeting set on this campaign.') }}</p>
                        @endif
                    </div>
                </div>
                <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Lead table') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">
                            {{ __('WhatsApp conversations from ads') }}</h2>
                    </div>
                    <div class="p-8 text-center text-[13px] text-ink-500">
                        Lead-level data appears once chat starters from this campaign land in the
                        <a href="{{ url('/chat') }}" class="text-wa-deep underline">{{ __('chat inbox') }}</a>.
                        @if ($waStarts)
                            Estimated <span class="font-mono text-ink-700">{{ $fmt($waStarts) }}
                                {{ __('chat starts') }}</span> based on click-through rate.
                        @endif
                    </div>
                </div>
            </section>

            <section data-panel="attribution" class="tab-panel hidden space-y-5">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Revenue attribution') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Spend → revenue') }}</h2>
                        @php
                            $roi = $spend > 0 ? round((($revenue - $spend) / $spend) * 100, 1) : 0;
                        @endphp
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                                <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Spend') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1">${{ $kfmt($spend) }}</div>
                            </div>
                            <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                                <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Revenue') }}</div>
                                <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">
                                    ${{ $kfmt($revenue) }}</div>
                            </div>
                            <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                                <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                                    {{ __('Net') }}</div>
                                <div
                                    class="font-serif text-[28px] leading-none mt-1 {{ $roi >= 0 ? 'text-wa-deep' : 'text-accent-coral' }}">
                                    {{ $roi >= 0 ? '+' : '' }}{{ $fmt($roi, 1) }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Per-lead value') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1 mb-4">{{ __('Lead economics') }}</h2>
                        <div class="space-y-3">
                            <div
                                class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 flex items-center justify-between">
                                <span class="text-[13px] font-semibold">{{ __('Avg order value') }}</span><span
                                    class="font-mono text-wa-deep">${{ $conv ? $fmt($revenue / $conv, 2) : '0.00' }}</span>
                            </div>
                            <div class="rounded-xl border border-paper-200 p-3 flex items-center justify-between"><span
                                    class="text-[13px] font-semibold">{{ __('CPL') }}</span><span
                                    class="font-mono">${{ $fmt($cpl, 2) }}</span></div>
                            <div class="rounded-xl border border-paper-200 p-3 flex items-center justify-between"><span
                                    class="text-[13px] font-semibold">{{ __('ROAS') }}</span><span
                                    class="font-mono">{{ $fmt($roas, 2) }}x</span></div>
                        </div>
                    </div>
                </div>
            </section>

            <section data-panel="events" class="tab-panel hidden">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Timeline') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Campaign events') }}</h2>
                    </div>
                    <div class="divide-y divide-paper-200">
                        <div class="px-5 py-4 flex items-start gap-3"><span
                                class="w-8 h-8 rounded-full bg-wa-bubble border border-wa-green/30 grid place-items-center text-wa-deep font-mono text-[12px]">1</span>
                            <div class="flex-1"><b>Campaign created</b>
                                <p class="text-[12px] text-ink-500 mt-1">{{ $campaign->name }} was created with
                                    objective {{ $campaign->optimization_goal }}.</p>
                            </div><span
                                class="font-mono text-[11px] text-ink-500">{{ $campaign->created_at?->format('M d') }}</span>
                        </div>
                        @if ($isActive)
                            <div class="px-5 py-4 flex items-start gap-3"><span
                                    class="w-8 h-8 rounded-full bg-wa-bubble border border-wa-green/30 grid place-items-center text-wa-deep font-mono text-[12px]">2</span>
                                <div class="flex-1"><b>Campaign active</b>
                                    <p class="text-[12px] text-ink-500 mt-1">Daily budget
                                        ${{ $fmt($campaign->daily_budget, 2) }} is currently delivering.</p>
                                </div><span class="font-mono text-[11px] text-ink-500">{{ __('today') }}</span>
                            </div>
                        @endif
                        @if ($campaign->ctwa_enabled)
                            <div class="px-5 py-4 flex items-start gap-3"><span
                                    class="w-8 h-8 rounded-full bg-paper-50 border border-paper-200 grid place-items-center text-ink-700 font-mono text-[12px]">·</span>
                                <div class="flex-1"><b>Click-to-WhatsApp configured</b>
                                    <p class="text-[12px] text-ink-500 mt-1">Replies are routed to
                                        {{ $campaign->ctwa_phone ?: 'the connected number' }}.</p>
                                </div><span class="font-mono text-[11px] text-ink-500">{{ __('setup') }}</span>
                            </div>
                        @endif
                        @if ($conv > 0)
                            <div class="px-5 py-4 flex items-start gap-3"><span
                                    class="w-8 h-8 rounded-full bg-accent-amber/20 border border-accent-amber/40 grid place-items-center text-ink-800 font-mono text-[12px]">★</span>
                                <div class="flex-1"><b>{{ $fmt($conv) }} qualified leads so far</b>
                                    <p class="text-[12px] text-ink-500 mt-1">CPL ${{ $fmt($cpl, 2) }}, ROAS
                                        {{ $fmt($roas, 2) }}×.</p>
                                </div><span class="font-mono text-[11px] text-ink-500">{{ __('running') }}</span>
                            </div>
                        @endif
                        <div class="px-5 py-4 flex items-start gap-3"><span
                                class="w-8 h-8 rounded-full bg-paper-50 border border-paper-200 grid place-items-center text-ink-700 font-mono text-[12px]">⟳</span>
                            <div class="flex-1"><b>Last synced</b>
                                <p class="text-[12px] text-ink-500 mt-1">{{ $updatedLbl }}
                                    ({{ $campaign->updated_at?->diffForHumans() }})</p>
                            </div><span class="font-mono text-[11px] text-ink-500">{{ $updatedLbl }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

    @endif

</x-layouts.user>
