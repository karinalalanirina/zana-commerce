<x-layouts.admin :title="__('Auto-replies')" admin-key="autoreplies">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Auto-replies') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Messaging') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Auto') }}
                    <span class="italic text-wa-deep">{{ __('replies') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __("Default auto-replies that ship with new workspaces and platform-wide audit of what's enabled.") }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Push defaults') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('New default') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Auto-replies') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">2,184</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 142 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Triggers (24h)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">42.6k</div>
                <div class="text-[11px] text-wa-deep mt-2">98% delivered</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Default templates') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">12</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('shipped on signup') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Stale (no triggers 30d)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">128</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('candidates to archive') }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Default auto-replies') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Shipped to every new workspace') }}
                    </h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-5 py-2.5 w-[20px]"></th>
                            <th class="text-left px-3 py-2.5 font-medium">{{ __('Trigger') }}</th>
                            <th class="text-left px-3 py-2.5 font-medium">{{ __('Reply preview') }}</th>
                            <th class="text-right px-3 py-2.5 w-[100px] font-medium">{{ __('Adoption') }}</th>
                            <th class="text-right pl-3 pr-5 py-2.5 w-[90px] font-medium">{{ __('Edit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr>
                            <td class="px-5 py-3"><label class="toggle"><input type="checkbox" checked /><span
                                        class="track"></span><span class="thumb"></span></label></td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-[11px]">{{ __('welcome') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('first message ever') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px] text-ink-700 truncate">Hi @{{ name }}! Welcome
                                — text "menu" to see options.</td>
                            <td class="px-3 py-3 text-right font-mono text-wa-deep">92%</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3"><label class="toggle"><input type="checkbox" checked /><span
                                        class="track"></span><span class="thumb"></span></label></td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-[11px]">{{ __('away') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('outside business hours') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px] text-ink-700 truncate">
                                {{ __("Thanks! We're away — back at 9 AM IST. We'll reply first thing.") }}</td>
                            <td class="px-3 py-3 text-right font-mono">81%</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3"><label class="toggle"><input type="checkbox" checked /><span
                                        class="track"></span><span class="thumb"></span></label></td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-[11px]">{{ __('menu') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('keyword "menu"') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px] text-ink-700 truncate">
                                {{ __('Reply with: 1) Order status, 2) Talk to human, 3) Catalog') }}</td>
                            <td class="px-3 py-3 text-right font-mono">76%</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3"><label class="toggle"><input type="checkbox" /><span
                                        class="track"></span><span class="thumb"></span></label></td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-[11px]">{{ __('unsubscribe') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('keyword "stop"') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px] text-ink-700 truncate">
                                {{ __("You've been removed from broadcast. Any other help?") }}</td>
                            <td class="px-3 py-3 text-right font-mono">68%</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3"><label class="toggle"><input type="checkbox" checked /><span
                                        class="track"></span><span class="thumb"></span></label></td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-[11px]">{{ __('human') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('keyword "human" or "agent"') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px] text-ink-700 truncate">
                                {{ __('Connecting you with our team — average wait 2 min.') }}</td>
                            <td class="px-3 py-3 text-right font-mono">62%</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3"><label class="toggle"><input type="checkbox" /><span
                                        class="track"></span><span class="thumb"></span></label></td>
                            <td class="px-3 py-3">
                                <div class="font-mono text-[11px]">{{ __('order_status') }}</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('keyword "order"') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11.5px] text-ink-700 truncate">
                                {{ __("Share your order ID and we'll check status.") }}</td>
                            <td class="px-3 py-3 text-right font-mono">54%</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Edit') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Top trigger keywords') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Most-fired (24h)') }}</h2>
                <div class="space-y-3 text-[12px]">
                    <div>
                        <div class="flex justify-between mb-1"><span class="font-mono">"hi"</span><span
                                class="font-mono">12,840</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-deep rounded-full" style="width:90%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="font-mono">"menu"</span><span
                                class="font-mono">8,512</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-deep rounded-full" style="width:62%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="font-mono">"order"</span><span
                                class="font-mono">6,240</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-teal rounded-full" style="width:48%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="font-mono">"human"</span><span
                                class="font-mono">3,108</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-teal rounded-full" style="width:24%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="font-mono">"stop"</span><span
                                class="font-mono">1,420</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-accent-coral rounded-full" style="width:12%"></div>
                        </div>
                    </div>
                </div>
            </div>

        </section>

    </main>

</x-layouts.admin>
