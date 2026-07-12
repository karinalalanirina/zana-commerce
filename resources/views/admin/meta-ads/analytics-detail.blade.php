<x-layouts.admin :title="__('Admin · Per-campaign Meta Ads analytics')" admin-key="metaads" page="meta-ads-analytics-detail">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0 min-w-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/meta-ads') }}" class="hover:text-ink-900">{{ __('Meta Ads') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/meta-ads/analytics') }}" class="hover:text-ink-900">{{ __('Analytics') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span
                class="text-ink-900 normal-case tracking-normal truncate max-w-[280px]">{{ __('Meta CTWA — Summer sale') }}</span>
        </div>
        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-green/10 text-wa-deep border border-wa-green/30"><span
                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
            <a href="{{ url('/admin/meta-ads/1/edit') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                </svg>
                Edit
            </a>
            <button
                class="px-3.5 py-1.5 hairline border border-accent-coral/40 text-accent-coral rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Force pause') }}</button>
            <button
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                </svg>
                Export
            </button>
        </div>
    </header>

    <!-- Page body -->
    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <!-- Hero KPI -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-4 sm:px-6 py-5 border-b border-paper-200 flex flex-col lg:flex-row items-start justify-between gap-5">
                <div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500">
                        <span>{{ __('Per-campaign · Meta Ads') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span>{{ __('Apr 18 → Apr 27, 2026') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span class="font-mono">{{ __('FB ID 1203948…441') }}</span>
                    </div>
                    <h1 class="font-serif text-3xl sm:text-4xl lg:text-[40px] leading-none mt-2">{{ __('Meta CTWA —') }} <span
                            class="italic text-wa-deep">{{ __('Summer sale') }}</span></h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Workspace:') }} <b>Bloomly</b> · Owner:
                        <b>Vetrick R.</b> · Single campaign drill-down with spend, CTR, WhatsApp conversations, lead
                        quality, and revenue attribution.</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full lg:w-[520px]">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('ROAS') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">7.78x</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Spend') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$412</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Revenue') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$3.2k</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Leads') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">184</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('CPL') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$2.24</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Quality') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">{{ __('High') }}</div>
                    </div>
                </div>
            </div>
            <div class="px-4 sm:px-6 py-3 flex items-center gap-1 border-b border-paper-200 bg-white overflow-x-auto whitespace-nowrap" data-wa-tabs>
                <button data-wa-tab="overview"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold bg-wa-deep text-paper-0">{{ __('Overview') }}</button>
                <button data-wa-tab="ads"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Ads & sets') }}</button>
                <button data-wa-tab="audience"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Audience') }}</button>
                <button data-wa-tab="attribution"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Attribution') }}</button>
                <button data-wa-tab="events"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Events') }}</button>
                <div class="ml-auto shrink-0 flex items-center gap-2 text-[11px] text-ink-500 font-mono">
                    <span>{{ __('Synced 4s ago') }}</span>
                    <button class="w-7 h-7 rounded-full hover:bg-paper-50 grid place-items-center"
                        title="{{ __('Refresh') }}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.6">
                            <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                            <path d="M13 3v3h-3M3 13v-3h3" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- Mini KPI grid -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" data-wa-tab-panel="overview">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Impressions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">84.2k</div>
                <div class="text-[11px] text-ink-500 mt-2">+18.4% vs prev</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Reach') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">62.4k</div>
                <div class="text-[11px] text-ink-500 mt-2">1.35 freq</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Clicks') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">2,210</div>
                <div class="text-[11px] text-wa-deep mt-2">2.62% CTR</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('WA starts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">642</div>
                <div class="text-[11px] text-ink-500 mt-2">29.0% of clicks</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Qualified leads') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">184</div>
                <div class="text-[11px] text-ink-500 mt-2">28.6% of chats</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('CPC') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">$0.19</div>
                <div class="text-[11px] text-wa-deep mt-2">below $0.25 target</div>
            </div>
        </section>

        <!-- Trend + outcome -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Daily trend') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Spend, clicks, leads') }}</h2>
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
                <div id="chart-trend"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Outcome mix') }}
                </div>
                <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Click outcomes') }}</h2>
                <div id="chart-outcomes" class="mt-2"></div>
            </div>
        </section>

        <!-- Funnel + Placement + Recommendations -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5" data-wa-tab-panel="overview ads audience attribution">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Funnel') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('From impression to lead') }}</h2>
                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Impressions') }}</span><span class="font-mono">84,210</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep w-full"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Reach') }}</span><span class="font-mono">62,410 · 74.1%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep w-[74%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Clicks') }}</span><span class="font-mono">2,210 · 2.62%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-teal w-[44%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('WA starts') }}</span><span class="font-mono">642 · 29.0%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[29%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Qualified leads') }}</span><span class="font-mono">184 · 28.6%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral w-[18%]"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Placement') }}
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Spend by placement') }}</h2>
                <div id="chart-placement"></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Recommendations') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('Next action') }}</h2>
                <div class="space-y-3">
                    <div class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3">
                        <div class="text-[13px] font-semibold">{{ __('Scale winning ad set') }}</div>
                        <div class="text-[11.5px] text-ink-600 mt-1">Women 25-34 producing $1.88 CPL. Increase budget
                            by 20%.</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="text-[13px] font-semibold">{{ __('Pause low CTR creative') }}</div>
                        <div class="text-[11.5px] text-ink-600 mt-1">
                            {{ __('Static image B below 1.1% CTR for 48h.') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="text-[13px] font-semibold">{{ __('Retarget chat starters') }}</div>
                        <div class="text-[11.5px] text-ink-600 mt-1">458 chat starters didn't convert. Send template
                            follow-up.</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Ad set table + admin audit -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview ads audience events">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Ad set performance') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Budget, leads, ROAS') }}</h2>
                    </div>
                    <button
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Download CSV') }}</button>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[880px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3 w-[220px]">{{ __('Ad set') }}</th>
                            <th class="text-left px-3 py-3">{{ __('Audience') }}</th>
                            <th class="text-left px-3 py-3 w-[90px]">{{ __('Spend') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('CTR') }}</th>
                            <th class="text-left px-3 py-3 w-[90px]">{{ __('Leads') }}</th>
                            <th class="text-left px-3 py-3 w-[90px]">{{ __('CPL') }}</th>
                            <th class="text-left px-4 py-3 w-[90px]">{{ __('ROAS') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Broad sale - women') }}</div>
                                <div class="text-[10.5px] text-ink-500">2 active ads</div>
                            </td>
                            <td class="px-3 py-3">{{ __('Women 25-44') }}</td>
                            <td class="px-3 py-3 font-mono">$184.20</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">3.12%</td>
                            <td class="px-3 py-3 font-mono">98</td>
                            <td class="px-3 py-3 font-mono">$1.88</td>
                            <td class="px-4 py-3 font-mono text-wa-deep">9.4x</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Retargeting - site visitors') }}</div>
                                <div class="text-[10.5px] text-ink-500">1 active ad</div>
                            </td>
                            <td class="px-3 py-3">30 day visitors</td>
                            <td class="px-3 py-3 font-mono">$126.70</td>
                            <td class="px-3 py-3 font-mono">2.74%</td>
                            <td class="px-3 py-3 font-mono">56</td>
                            <td class="px-3 py-3 font-mono">$2.26</td>
                            <td class="px-4 py-3 font-mono text-wa-deep">8.1x</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Interest stack - fashion') }}</div>
                                <div class="text-[10.5px] text-ink-500">1 limited ad</div>
                            </td>
                            <td class="px-3 py-3">{{ __('Fashion shoppers') }}</td>
                            <td class="px-3 py-3 font-mono">$101.60</td>
                            <td class="px-3 py-3 font-mono">1.84%</td>
                            <td class="px-3 py-3 font-mono">30</td>
                            <td class="px-3 py-3 font-mono">$3.38</td>
                            <td class="px-4 py-3 font-mono">4.9x</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Admin audit trail') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Activity log') }}</h2>
                <ol class="space-y-2.5 text-[11.5px]">
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green mt-1.5 shrink-0"></span><span><b>Vetrick R.</b>
                            created campaign · <span class="text-ink-500">2026-04-18 09:14</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>{{ __('Auto-approved by policy bot ·') }}
                            <span class="text-ink-500">2026-04-18 09:15</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-accent-amber mt-1.5 shrink-0"></span><span><b>Meera
                                Shah</b> increased budget $20 → $25 · <span class="text-ink-500">2026-04-22
                                14:02</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-[#13478A] mt-1.5 shrink-0"></span><span>{{ __('ROAS hit 7x milestone ·') }}
                            <span class="text-ink-500">2026-04-24 18:21</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-teal mt-1.5 shrink-0"></span><span>{{ __('Insights synced ·') }}
                            <span class="text-ink-500">{{ __('just now') }}</span></span></li>
                </ol>
                <div class="mt-4 pt-4 border-t border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Admin actions') }}</div>
                    <div class="space-y-2">
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold flex items-center justify-between">{{ __('Issue refund') }}<span
                                class="text-ink-500 font-mono text-[11px]">$412.50</span></button>
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold flex items-center justify-between">{{ __('Transfer ownership') }}<span
                                class="text-ink-500">→</span></button>
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-accent-coral/30 bg-accent-coral/5 text-accent-coral text-[12.5px] font-semibold">{{ __('Force pause & review') }}</button>
                    </div>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
