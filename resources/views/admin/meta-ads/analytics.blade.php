<x-layouts.admin :title="__('Admin · Meta Ads analytics')" admin-key="metaads" page="meta-ads-analytics">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/meta-ads') }}" class="hover:text-ink-900">{{ __('Meta Ads') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Analytics') }}</span>
        </div>
        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <select
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option>{{ __('All workspaces') }}</option>
                <option selected>{{ __('Bloomly') }}</option>
                <option>{{ __('FitKart') }}</option>
                <option>{{ __('Northstar Clinic') }}</option>
                <option>{{ __('QuickBite') }}</option>
            </select>
            <select
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option>{{ __('Last 7 days') }}</option>
                <option selected>{{ __('Last 30 days') }}</option>
                <option>{{ __('Last 90 days') }}</option>
                <option>{{ __('Year to date') }}</option>
            </select>
            <button
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                </svg>
                Export CSV
            </button>
        </div>
    </header>

    <!-- Page heading + KPI hero -->
    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-4 sm:px-6 py-5 border-b border-paper-200 flex flex-col lg:flex-row items-start justify-between gap-5">
                <div>
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500">
                        <span>{{ __('Admin · Meta Ads analytics') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span>{{ __('Apr 1 → Apr 27, 2026') }}</span>
                    </div>
                    <h1 class="font-serif text-3xl sm:text-4xl lg:text-[40px] leading-none mt-2">{{ __('Meta Ads') }} <span
                            class="italic text-wa-deep">{{ __('analytics') }}</span></h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Cross-workspace ad spend, ROAS, lead quality, and creative performance across the entire :app platform. Drill down by workspace using the filter above.', ['app' => brand_name()]) }}
                    </p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full lg:w-[520px]">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Platform ROAS') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">6.42x</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Total spend') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">$182k</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Revenue') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$1.17M</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Leads') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">68.4k</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('CPL avg') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$2.66</div>
                    </div>
                    <div class="rounded-2xl bg-accent-amber/10 border border-accent-amber/40 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Flagged') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-accent-coral">14</div>
                    </div>
                </div>
            </div>
            <div class="px-4 sm:px-6 py-3 flex items-center gap-1 border-b border-paper-200 bg-white overflow-x-auto whitespace-nowrap" data-wa-tabs>
                <button data-wa-tab="overview"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold bg-wa-deep text-paper-0">{{ __('Overview') }}</button>
                <button data-wa-tab="workspaces"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Workspaces') }}</button>
                <button data-wa-tab="ads"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Ads & sets') }}</button>
                <button data-wa-tab="audience"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Audience') }}</button>
                <button data-wa-tab="attribution"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Attribution') }}</button>
                <button data-wa-tab="events"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Events') }}</button>
            </div>
        </section>

        <!-- KPI mini-row -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" data-wa-tab-panel="overview">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Impressions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">38.2M</div>
                <div class="text-[11px] text-ink-500 mt-2">+18.4% vs prev</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Reach') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">22.8M</div>
                <div class="text-[11px] text-ink-500 mt-2">1.67 freq</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Clicks') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">948k</div>
                <div class="text-[11px] text-wa-deep mt-2">2.48% CTR</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('WhatsApp starts') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">242k</div>
                <div class="text-[11px] text-ink-500 mt-2">25.5% of clicks</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Qualified leads') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">68.4k</div>
                <div class="text-[11px] text-ink-500 mt-2">28.3% of chats</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('CPC avg') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">$0.19</div>
                <div class="text-[11px] text-wa-deep mt-2">below $0.25 target</div>
            </div>
        </section>

        <!-- Trend chart + outcome donut -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Daily trend · all workspaces') }}</div>
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

        <!-- Top workspaces table + funnel + recommendations -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview workspaces attribution">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top spending workspaces') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Where the budget goes') }}</h2>
                    </div>
                    <button
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('View all 142') }}</button>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[800px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3 w-[200px]">{{ __('Workspace') }}</th>
                            <th class="text-left px-3 py-3 w-[110px]">{{ __('Plan') }}</th>
                            <th class="text-left px-3 py-3 w-[80px]">{{ __('Camps') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('Spend') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('Revenue') }}</th>
                            <th class="text-left px-3 py-3 w-[80px]">{{ __('ROAS') }}</th>
                            <th class="text-left px-4 py-3 w-[90px]">{{ __('Health') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Bloomly') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('Retail India') }}</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Pro') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">28</td>
                            <td class="px-3 py-3 font-mono">$28,420</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">$214,800</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">7.6x</td>
                            <td class="px-4 py-3 text-wa-deep font-mono">{{ __('Good') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('FitKart') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('D2C fitness') }}</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('Pro') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">19</td>
                            <td class="px-3 py-3 font-mono">$22,100</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">$148,200</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">6.7x</td>
                            <td class="px-4 py-3 text-wa-deep font-mono">{{ __('Good') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Northstar Clinic') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('Healthcare') }}</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Enterprise') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">12</td>
                            <td class="px-3 py-3 font-mono">$18,640</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">$92,800</td>
                            <td class="px-3 py-3 font-mono">5.0x</td>
                            <td class="px-4 py-3 text-accent-amber font-mono">{{ __('Watch') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('QuickBite') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('Food delivery') }}</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-[#FFF4E0] text-[#7B5A14] text-[10px] font-semibold">{{ __('Starter') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">8</td>
                            <td class="px-3 py-3 font-mono">$9,440</td>
                            <td class="px-3 py-3 font-mono">$32,180</td>
                            <td class="px-3 py-3 font-mono">3.4x</td>
                            <td class="px-4 py-3 text-accent-coral font-mono">{{ __('Risk') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Lumina Beauty') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('D2C beauty') }}</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Pro') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">11</td>
                            <td class="px-3 py-3 font-mono">$8,210</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">$58,400</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">7.1x</td>
                            <td class="px-4 py-3 text-wa-deep font-mono">{{ __('Good') }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 space-y-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Platform funnel') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Impression → lead') }}</h2>
                    <div class="space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Impressions') }}</span><span class="font-mono">38.2M</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep w-full"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Reach') }}</span><span class="font-mono">22.8M · 59.7%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-deep w-[60%]"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Clicks') }}</span><span class="font-mono">948k · 2.48%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-wa-teal w-[40%]"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('WA starts') }}</span><span class="font-mono">242k · 25.5%</span></div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-accent-amber w-[26%]"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] mb-1">
                                <span>{{ __('Qualified leads') }}</span><span class="font-mono">68.4k · 28.3%</span>
                            </div>
                            <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                                <div class="h-full bg-accent-coral w-[18%]"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Admin recommendations') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Action queue') }}</h2>
                    <div class="space-y-2.5">
                        <div class="rounded-xl border border-accent-coral/30 bg-accent-coral/5 p-3">
                            <div class="text-[12.5px] font-semibold text-accent-coral">14 ads pending policy review
                            </div>
                            <div class="text-[11px] text-ink-700 mt-0.5">{{ __('Triage in') }} <a
                                    href="{{ url('/admin/meta-ads') }}"
                                    class="text-wa-deep underline">{{ __('Campaigns › Pending review') }}</a>.</div>
                        </div>
                        <div class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3">
                            <div class="text-[12.5px] font-semibold">{{ __('QuickBite ROAS dropped 3.4x') }}</div>
                            <div class="text-[11px] text-ink-700 mt-0.5">
                                {{ __('Reach out to Ravi Tandon — they may need creative help.') }}</div>
                        </div>
                        <div class="rounded-xl border border-paper-200 p-3">
                            <div class="text-[12.5px] font-semibold">7 workspaces near plan cap</div>
                            <div class="text-[11px] text-ink-700 mt-0.5">Send upgrade prompts before they hit the daily
                                $500 ceiling.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Spend by placement + objective breakdown -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview ads audience events">
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Spend by placement') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Across Meta surfaces') }}</h2>
                <div id="chart-placement" class="mt-2"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Objective breakdown') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Where workspaces invest') }}</h2>
                <div id="chart-objective" class="mt-2"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Policy violations · 30d') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-3">{{ __('Reasons for rejection') }}</h2>
                <div class="space-y-2.5 text-[12px]">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Misleading claims') }}</span><span class="font-mono">11</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral w-[78%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Restricted content') }}</span><span class="font-mono">4</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[28%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span>Image text > 20%</span><span
                                class="font-mono">2</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[14%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Landing page mismatch') }}</span><span class="font-mono">1</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-paper-300 w-[8%]"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
