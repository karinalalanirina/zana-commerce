<x-layouts.admin :title="__('Admin · Per-WA-Campaign analytics')" admin-key="campaigns" page="campaigns-analytics">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/campaigns') }}" class="hover:text-ink-900">{{ __('Campaigns') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span
                class="text-ink-900 normal-case tracking-normal truncate max-w-[280px]">{{ __('New Year VIP drop · analytics') }}</span>
        </div>
        <div class="ml-auto flex items-center flex-wrap gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-ink-900 text-paper-0">{{ __('Completed') }}</span>
            <a href="{{ url('/admin/campaigns/create') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 3v10M3 8h10" />
                </svg>
                {{ __('Duplicate') }}
            </a>
            <button
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                </svg>
                {{ __('Export CSV') }}
            </button>
        </div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <!-- Hero -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-6 py-5 border-b border-paper-200 flex flex-col lg:flex-row items-start justify-between gap-5">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-500">
                        <span>{{ __('Per-campaign · WhatsApp broadcast') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span>{{ __('Sent Jan 24, 2026 · 09:00 IST') }}</span>
                        <span class="w-1 h-1 rounded-full bg-ink-500"></span>
                        <span class="font-mono">{{ __('CAM_NYV_4421') }}</span>
                    </div>
                    <h1 class="font-serif text-[30px] sm:text-[40px] leading-none mt-2">{{ __('New Year VIP') }} <span
                            class="italic text-wa-deep">{{ __('drop') }}</span></h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Workspace:') }} <b>Bloomly</b> · Owner:
                        <b>Vetrick R.</b> · Template: <span class="font-mono">{{ __('vip_coupon_v3') }}</span> ·
                        Audience: VIP customers (9,840) · Device: Sales (+91 98765 43210)</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full lg:w-[520px]">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Delivery') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">95.7%</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Sent') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">9,840</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Read rate') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">72.3%</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Replies') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">412</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('CTR') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">12.8%</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Cost') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$8.41</div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-3 flex items-center gap-1 border-b border-paper-200 bg-white overflow-x-auto" data-wa-tabs>
                <button data-wa-tab="overview"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold bg-wa-deep text-paper-0">{{ __('Overview') }}</button>
                <button data-wa-tab="recipients"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Recipients') }}</button>
                <button data-wa-tab="replies"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Replies') }}</button>
                <button data-wa-tab="failures"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Failures') }}</button>
                <button data-wa-tab="audit"
                    class="shrink-0 px-4 py-2 rounded-full text-[13px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Audit') }}</button>
            </div>
        </section>

        <!-- KPI mini-row -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" data-wa-tab-panel="overview">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Recipients') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">9,840</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('VIP segment') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Delivered') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">9,421</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('95.7% rate') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Read') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">6,812</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('72.3% read rate') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Replied') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">412</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('6.0% reply rate') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('CTA clicks') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2">1,260</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('12.8% CTR') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-5 shadow-card">
                <div class="text-[12px] text-ink-600">{{ __('Failed') }}</div>
                <div class="font-serif text-[34px] leading-none mt-2 text-accent-coral">48</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('0.49% failure') }}</div>
            </div>
        </section>

        <!-- Trend + Funnel -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Hourly send curve · Jan 24') }}</div>
                        <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Sent · Delivered · Read') }}</h2>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-ink-500">
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>{{ __('Sent') }}</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>{{ __('Delivered') }}</span>
                        <span class="flex items-center gap-1.5"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>{{ __('Read') }}</span>
                    </div>
                </div>
                <div id="chart-trend"></div>
            </div>
            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status mix') }}
                </div>
                <h2 class="font-serif text-[26px] leading-tight mt-1">{{ __('Outcome split') }}</h2>
                <div id="chart-status" class="mt-2"></div>
            </div>
        </section>

        <!-- Funnel + Reply types + Failure breakdown -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5" data-wa-tab-panel="overview replies failures">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Funnel') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1 mb-4">{{ __('Sent → Action') }}</h2>
                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Sent') }}</span><span class="font-mono">9,840</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep w-full"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Delivered') }}</span><span class="font-mono">9,421 · 95.7%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep w-[96%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Read') }}</span><span class="font-mono">6,812 · 72.3%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-teal w-[72%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Clicked') }}</span><span class="font-mono">1,260 · 18.5%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[19%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-[12px] mb-1">
                            <span>{{ __('Replied') }}</span><span class="font-mono">412 · 32.7%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral w-[7%]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Reply types') }}
                </div>
                <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('What people sent back') }}</h2>
                <div id="chart-replies" class="mt-2"></div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Failure reasons') }}</div>
                <h2 class="font-serif text-[24px] leading-tight mt-1 mb-3">{{ __('48 messages failed') }}</h2>
                <div class="space-y-2.5 text-[12px]">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Number not on WhatsApp') }}</span><span class="font-mono">28</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-coral w-[58%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Blocked by user') }}</span><span class="font-mono">12</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[25%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span>{{ __('Template variable missing') }}</span><span class="font-mono">6</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[12%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span>{{ __('Rate-limited') }}</span><span
                                class="font-mono">2</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-paper-300 w-[4%]"></div>
                        </div>
                    </div>
                </div>
                <a href="{{ url('/admin/campaigns') }}"
                    class="inline-flex items-center gap-1 mt-4 text-[12px] font-semibold text-wa-deep hover:underline">{{ __('View campaigns →') }}</a>
            </div>
        </section>

        <!-- Top recipients table + admin audit -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5" data-wa-tab-panel="overview recipients replies audit">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Top engaged recipients') }}</div>
                        <h2 class="font-serif text-[24px] leading-tight mt-1">{{ __('Who actually replied') }}</h2>
                    </div>
                    <button
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('View all') }}</button>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[840px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3 w-[220px]">{{ __('Contact') }}</th>
                            <th class="text-left px-3 py-3 w-[120px]">{{ __('Status') }}</th>
                            <th class="text-left px-3 py-3 w-[110px]">{{ __('Read at') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('Replied') }}</th>
                            <th class="text-left px-3 py-3 w-[100px]">{{ __('Clicked') }}</th>
                            <th class="text-left px-4 py-3">{{ __('Last reply') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Anika Verma') }}</div>
                                <div class="text-[10.5px] text-ink-500">+91 98214 11023</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Read · Replied') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">09:14</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">{{ __('Yes') }}</td>
                            <td class="px-3 py-3 font-mono">{{ __('Yes') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-700 truncate">"Yes, I want the coupon!"</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Karthik Iyer') }}</div>
                                <div class="text-[10.5px] text-ink-500">+91 90021 88341</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Read · Replied') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">09:16</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">{{ __('Yes') }}</td>
                            <td class="px-3 py-3 font-mono">{{ __('Yes') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-700 truncate">"How do I redeem?"</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Sneha Roy') }}</div>
                                <div class="text-[10.5px] text-ink-500">+91 89832 76651</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Read') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">09:22</td>
                            <td class="px-3 py-3 font-mono text-ink-500">No</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">{{ __('Yes') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">—</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Aditya Banerjee') }}</div>
                                <div class="text-[10.5px] text-ink-500">+91 78891 22087</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Read · Replied') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">09:31</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">{{ __('Yes') }}</td>
                            <td class="px-3 py-3 font-mono">{{ __('Yes') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-700 truncate">"STOP"</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3">
                                <div class="font-semibold truncate">{{ __('Riya Chatterjee') }}</div>
                                <div class="text-[10.5px] text-ink-500">+91 89991 78403</div>
                            </td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Read') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono">09:42</td>
                            <td class="px-3 py-3 font-mono text-ink-500">No</td>
                            <td class="px-3 py-3 font-mono text-wa-deep">{{ __('Yes') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">—</td>
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
                            created broadcast · <span class="text-ink-500">2026-01-23 18:14</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>{{ __('Template') }}
                            <span class="font-mono">{{ __('vip_coupon_v3') }}</span> auto-validated · <span
                                class="text-ink-500">2026-01-23 18:15</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-accent-amber mt-1.5 shrink-0"></span><span><b>Vetrick
                                R.</b> scheduled for 09:00 IST · <span class="text-ink-500">2026-01-23
                                18:18</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-teal mt-1.5 shrink-0"></span><span>{{ __('Queue started · 9,840 in queue ·') }}
                            <span class="text-ink-500">2026-01-24 09:00</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-[#13478A] mt-1.5 shrink-0"></span><span>{{ __('Queue completed · 48 failures ·') }}
                            <span class="text-ink-500">2026-01-24 09:48</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green mt-1.5 shrink-0"></span><span>{{ __('Insights synced ·') }}
                            <span class="text-ink-500">{{ __('just now') }}</span></span></li>
                </ol>
                <div class="mt-4 pt-4 border-t border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Admin actions') }}</div>
                    <div class="space-y-2">
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold flex items-center justify-between">{{ __('Resend to failed only') }}<span
                                class="text-ink-500 font-mono text-[11px]">48</span></button>
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold flex items-center justify-between">{{ __('Issue cost refund') }}<span
                                class="text-ink-500 font-mono text-[11px]">$8.41</span></button>
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-accent-coral/30 bg-accent-coral/5 text-accent-coral text-[12.5px] font-semibold">{{ __('Archive campaign') }}</button>
                    </div>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
