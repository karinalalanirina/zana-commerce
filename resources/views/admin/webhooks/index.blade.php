<x-layouts.admin :title="__('Webhooks')" admin-key="webhooks">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Webhooks') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex items-end justify-between gap-4 flex-wrap">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Platform') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Webhook') }}
                    <span class="italic text-wa-deep">{{ __('delivery') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Outbound webhook health, failed deliveries, retry queue, and platform-level secret rotation.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Replay failed') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Rotate platform secret') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active endpoints') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">312</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 142 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Delivery (24h)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">98.4%</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('target 99%') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Failures (24h)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-coral">428</div>
                <div class="text-[11px] text-accent-coral mt-2">82 in retry</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Avg latency') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">182ms</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('p95 720ms') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Disabled (auto)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">5</div>
                <div class="text-[11px] text-accent-amber mt-2">10+ consecutive fails</div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Endpoints') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Outbound webhooks') }}</h2>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('All') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Failing') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Disabled') }}</button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] min-w-[760px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-5 py-2.5 w-[18px]"></th>
                        <th class="text-left px-3 py-2.5 font-medium">{{ __('Endpoint') }}</th>
                        <th class="text-left px-3 py-2.5 w-[150px] font-medium">{{ __('Workspace') }}</th>
                        <th class="text-left px-3 py-2.5 w-[120px] font-medium">{{ __('Events') }}</th>
                        <th class="text-right px-3 py-2.5 w-[90px] font-medium">{{ __('Success') }}</th>
                        <th class="text-right px-3 py-2.5 w-[80px] font-medium">{{ __('Fails') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[110px] font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-mono text-[11.5px]">https://hooks.bloomly.in/wa/messages</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('v2 · whsec_***f12') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Bloomly') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('message.*') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">99.6%</td>
                        <td class="px-3 py-3 text-right font-mono">3</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Logs') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-accent-amber"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-mono text-[11.5px]">https://api.quickshop.in/woo/cb</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('v2 · whsec_***a89') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('QuickShop') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('order.*') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-accent-amber">94.2%</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-amber">62</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Replay') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-accent-coral"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-mono text-[11.5px]">https://designhub.io/wh/contacts</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('disabled · 12 consecutive fails') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('DesignHub') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('contact.*') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-accent-coral">62.0%</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-coral">128</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Re-enable') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-mono text-[11.5px]">https://app.fitkart.in/api/wa</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('v2 · whsec_***c01') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('FitKart') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('message.*') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">99.9%</td>
                        <td class="px-3 py-3 text-right font-mono">1</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Logs') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-mono text-[11.5px]">https://northstar.com/api/inbox</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('v2 · whsec_***bb7') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Northstar') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('message.*') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">99.4%</td>
                        <td class="px-3 py-3 text-right font-mono">5</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Logs') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Last 24h failures') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Recent failed deliveries') }}</h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] min-w-[420px]">
                    <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-2.5 font-medium">{{ __('Endpoint') }}</th>
                            <th class="text-right px-3 py-2.5 w-[80px] font-medium">{{ __('Code') }}</th>
                            <th class="text-right pl-3 pr-5 py-2.5 w-[110px] font-medium">{{ __('When') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr>
                            <td class="px-4 py-2.5 font-mono text-[11.5px]">https://api.quickshop.in/woo/cb</td>
                            <td class="px-3 py-2.5 text-right font-mono text-accent-coral">429</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px] text-ink-500">12m ago</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 font-mono text-[11.5px]">https://designhub.io/wh/contacts</td>
                            <td class="px-3 py-2.5 text-right font-mono text-accent-coral">503</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px] text-ink-500">38m ago</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 font-mono text-[11.5px]">https://hooks.bloomly.in/wa/messages</td>
                            <td class="px-3 py-2.5 text-right font-mono text-accent-amber">{{ __('timeout') }}</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px] text-ink-500">1h ago</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 font-mono text-[11.5px]">https://api.quickshop.in/woo/cb</td>
                            <td class="px-3 py-2.5 text-right font-mono text-accent-coral">429</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px] text-ink-500">2h ago</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Retry policy') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Backoff & auto-disable') }}</h2>
                <div class="space-y-3 text-[12.5px]">
                    <label class="space-y-1.5"><span
                            class="text-[11.5px] font-semibold">{{ __('Max attempts') }}</span><input type="number"
                            value="8"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px]"></label>
                    <label class="space-y-1.5"><span
                            class="text-[11.5px] font-semibold">{{ __('Initial delay') }}</span><input value="30s"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono"></label>
                    <label class="space-y-1.5"><span
                            class="text-[11.5px] font-semibold">{{ __('Backoff factor') }}</span><input
                            value="2x"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono"></label>
                    <label class="space-y-1.5"><span
                            class="text-[11.5px] font-semibold">{{ __('Auto-disable after') }}</span><input
                            type="number" value="10"
                            class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px]"><span
                            class="text-[11px] text-ink-500">{{ __('consecutive failures') }}</span></label>
                </div>
                <button
                    class="mt-4 w-full rounded-full bg-wa-deep text-paper-0 px-4 py-2 text-[12px] font-semibold">{{ __('Save policy') }}</button>
            </div>
        </section>

    </main>

</x-layouts.admin>
