<x-layouts.admin :title="__('Admin · Device analytics')" admin-key="devices" page="devices-detail">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/devices') }}" class="hover:text-ink-900">{{ __('Devices') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span
                class="text-ink-900 normal-case tracking-normal truncate max-w-[280px]">{{ __('Sales line · Bloomly') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected</span>
            <select
                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium focus:outline-none focus:border-wa-deep">
                <option>{{ __('Last 24 hours') }}</option>
                <option selected>{{ __('Last 7 days') }}</option>
                <option>{{ __('Last 30 days') }}</option>
            </select>
            <button
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                </svg>
                Refresh session
            </button>
            <button
                class="px-3.5 py-1.5 hairline border border-accent-coral/40 text-accent-coral rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Force disconnect') }}</button>
            <button
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                </svg>
                Export
            </button>
        </div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <!-- Device header card -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                <div class="flex items-start gap-4 min-w-0">
                    <span class="w-12 h-12 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0"><svg
                            viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                            <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                            <circle cx="8" cy="11.5" r="0.8" />
                        </svg></span>
                    <div class="min-w-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Device #DEV_4421') }}</div>
                        <h1 class="font-serif text-[28px] leading-tight tracking-[-0.01em] mt-0.5">{{ __('Sales') }}
                            <span class="italic text-wa-deep">{{ __('line') }}</span></h1>
                        <div class="mt-1 font-mono text-[13px] text-ink-700">+91 98765 43210</div>
                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px] font-semibold">{{ __('Bloomly') }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('Pro plan') }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('India · Asia/Kolkata') }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('iPhone 14 · iOS 17.3') }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('Owner: Vetrick R.') }}</span>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('Paired 184 days ago') }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Re-pair QR') }}</button>
                    <button
                        class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-1.5"><svg
                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>{{ __('Transfer') }}</button>
                </div>
            </div>
        </section>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (7d)') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">+18%</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">21,884</span><span
                        class="text-[11px] text-ink-500">{{ __('outgoing') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Delivered') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">{{ __('healthy') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">99.4%</span><span
                        class="text-[11px] text-ink-500">21,752 ok</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Read rate') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">+4% vs avg</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">68.2%</span><span
                        class="text-[11px] text-ink-500">14,839 read</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Failed') }}</span><span
                        class="text-[10px] text-accent-coral font-mono">0.6%</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">132</span><span
                        class="text-[11px] text-ink-500">{{ __('retry queue') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Throughput') }}</span><span
                        class="text-[10px] text-accent-amber font-mono">{{ __('limit 80/min') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[28px] leading-none">62/min</span><span
                        class="text-[11px] text-ink-500">{{ __('peak today') }}</span></div>
            </div>
        </section>

        <!-- Volume + status -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Activity') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Sent vs failed (7d)') }}</h3>
                    </div>
                    <div class="flex items-center gap-1 text-[11px] font-mono text-ink-500">
                        <button class="px-2.5 py-1 rounded-full bg-wa-deep text-paper-0">{{ __('Volume') }}</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">{{ __('Throughput') }}</button>
                        <button class="px-2.5 py-1 rounded-full hover:bg-paper-100">{{ __('Latency') }}</button>
                    </div>
                </div>
                <div id="chart-volume" class="h-[260px]"></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Status mix') }}
                </div>
                <h3 class="font-serif text-[22px] leading-tight mt-0.5 mb-3">{{ __('Delivery breakdown') }}</h3>
                <div id="chart-status" class="h-[200px]"></div>
                <div class="mt-3 space-y-1.5 text-[12px]">
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-deep"></span>Read</span><span
                            class="font-mono text-ink-700">14,839</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-wa-teal"></span>Delivered</span><span
                            class="font-mono text-ink-700">6,913</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-amber"></span>Pending</span><span
                            class="font-mono text-ink-700">0</span></div>
                    <div class="flex items-center justify-between"><span class="flex items-center gap-2"><span
                                class="w-2.5 h-2.5 rounded-full bg-accent-coral"></span>Failed</span><span
                            class="font-mono text-ink-700">132</span></div>
                </div>
            </div>
        </section>

        <!-- Hour heatmap + uptime -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('When it fires') }}</div>
                        <h3 class="font-serif text-[22px] leading-tight mt-0.5">{{ __('Hour-of-day heatmap') }}</h3>
                    </div>
                    <span class="text-[11px] font-mono text-ink-500">{{ __('last 7 days · IST') }}</span>
                </div>
                <div id="chart-heatmap" class="h-[260px]"></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Uptime & sessions') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-0.5 mb-3">{{ __('Connection health') }}</h3>
                <div class="space-y-3 text-[12px]">
                    <div>
                        <div class="flex items-center justify-between mb-1"><span
                                class="text-ink-500">{{ __('Online (7d)') }}</span><span
                                class="font-mono text-ink-900">99.92%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-deep w-[99.92%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span
                                class="text-ink-500">{{ __('QR re-pairs') }}</span><span
                                class="font-mono text-ink-900">2 in 30d</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-wa-teal w-[10%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span
                                class="text-ink-500">{{ __('Avg latency') }}</span><span
                                class="font-mono text-ink-900">142 ms</span></div>
                        <div class="h-2 bg-paper-100 rounded-full overflow-hidden">
                            <div class="h-full bg-accent-amber w-[28%]"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span
                                class="text-ink-500">{{ __('Last disconnect') }}</span><span
                                class="font-mono text-ink-900">12 days ago</span></div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1"><span
                                class="text-ink-500">{{ __('Battery') }}</span><span
                                class="font-mono text-wa-deep">82% · charging</span></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recent events table + Admin audit -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Recent events') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Session log') }}</h2>
                    </div>
                    <button
                        class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-semibold">{{ __('Export') }}</button>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[560px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3 w-[140px]">{{ __('When') }}</th>
                            <th class="text-left px-3 py-3 w-[130px]">{{ __('Event') }}</th>
                            <th class="text-left px-3 py-3">{{ __('Detail') }}</th>
                            <th class="text-left px-4 py-3 w-[100px]">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11px]">{{ __('just now') }}</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono">{{ __('heartbeat') }}</span>
                            </td>
                            <td class="px-3 py-3 text-ink-700">{{ __('OK · 142ms latency') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">{{ __('system') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11px]">2 min ago</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px] font-mono">{{ __('message-sent') }}</span>
                            </td>
                            <td class="px-3 py-3 text-ink-700">
                                {{ __('Burst of 312 messages · campaign CAM_NYV_4421') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">{{ __('Vetrick R.') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11px]">14 min ago</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px] font-mono">{{ __('qr-scan') }}</span>
                            </td>
                            <td class="px-3 py-3 text-ink-700">{{ __('Session refreshed by owner') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">{{ __('Vetrick R.') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11px]">3 hours ago</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber text-[10.5px] font-mono">{{ __('throttle') }}</span>
                            </td>
                            <td class="px-3 py-3 text-ink-700">{{ __('Hit 80/min ceiling for 3 minutes') }}</td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">{{ __('system') }}</td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-mono text-[11px]">12 days ago</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral text-[10.5px] font-mono">{{ __('disconnect') }}</span>
                            </td>
                            <td class="px-3 py-3 text-ink-700">{{ __('Phone offline for 6 minutes (battery 4%)') }}
                            </td>
                            <td class="px-4 py-3 text-[11px] text-ink-500">{{ __('device') }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 min-w-0 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Admin audit') }}
                </div>
                <h2 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('Action log') }}</h2>
                <ol class="space-y-2.5 text-[11.5px]">
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green mt-1.5 shrink-0"></span><span><b>Vetrick R.</b>
                            paired this device · <span class="text-ink-500">2025-10-26 14:14</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>{{ __('Auto-renewed session token ·') }}
                            <span class="text-ink-500">2025-11-09 14:14</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-accent-amber mt-1.5 shrink-0"></span><span><b>Sahil
                                K.</b> increased throughput cap to 80/min · <span class="text-ink-500">2026-01-18
                                11:02</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-teal mt-1.5 shrink-0"></span><span>{{ __('Insights synced ·') }}
                            <span class="text-ink-500">{{ __('just now') }}</span></span></li>
                </ol>
                <div class="mt-4 pt-4 border-t border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('Admin actions') }}</div>
                    <div class="space-y-2">
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold flex items-center justify-between">{{ __('Lift throughput cap') }}<span
                                class="text-ink-500 font-mono text-[11px]">80 → 120/min</span></button>
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-paper-200 hover:bg-paper-50 text-[12.5px] font-semibold flex items-center justify-between">{{ __('Issue refund') }}<span
                                class="text-ink-500 font-mono text-[11px]">{{ __('downtime') }}</span></button>
                        <button
                            class="w-full text-left px-3 py-2 rounded-xl border border-accent-coral/30 bg-accent-coral/5 text-accent-coral text-[12.5px] font-semibold">{{ __('Quarantine device') }}</button>
                    </div>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
