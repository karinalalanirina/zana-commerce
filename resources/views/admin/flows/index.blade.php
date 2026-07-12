<x-layouts.admin :title="__('Flows')" admin-key="flows">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Flows') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Messaging') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Conversation') }}
                    <span class="italic text-wa-deep">{{ __('flows') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Workspace-built flows + the global flow library. Audit health, error rate, and traffic.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Export library') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('New global flow') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active flows') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">428</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 142 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Conversations (24h)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">28.4k</div>
                <div class="text-[11px] text-wa-deep mt-2">94% completed</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Error rate') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">2.1%</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('target < 1%') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Global library') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">22</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('starter flows') }}</div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Workspace flows') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Top-trafficked & failing') }}</h2>
                </div>
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('All') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Failing') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('High volume') }}</button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] min-w-[720px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-5 py-2.5 font-medium">{{ __('Flow') }}</th>
                        <th class="text-left px-3 py-2.5 w-[150px] font-medium">{{ __('Workspace') }}</th>
                        <th class="text-right px-3 py-2.5 w-[100px] font-medium">{{ __('Runs (24h)') }}</th>
                        <th class="text-right px-3 py-2.5 w-[110px] font-medium">{{ __('Completion') }}</th>
                        <th class="text-right px-3 py-2.5 w-[80px] font-medium">{{ __('Errors') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[100px] font-medium">{{ __('Open') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('VIP early access funnel') }}</div>
                            <div class="text-[10.5px] text-ink-500">9 nodes · trigger: keyword "vip"</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Bloomly') }}</td>
                        <td class="px-3 py-3 text-right font-mono">4,820</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">96%</td>
                        <td class="px-3 py-3 text-right font-mono">12</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Cart recovery sequence') }}</div>
                            <div class="text-[10.5px] text-ink-500">7 nodes · trigger: webhook abandoned_checkout</div>
                        </td>
                        <td class="px-3 py-3">{{ __('FitKart') }}</td>
                        <td class="px-3 py-3 text-right font-mono">2,914</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-amber">86%</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-amber">82</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Audit') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Appointment reminder + reschedule') }}</div>
                            <div class="text-[10.5px] text-ink-500">12 nodes · trigger: cron 24h before</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Northstar') }}</td>
                        <td class="px-3 py-3 text-right font-mono">1,604</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">98%</td>
                        <td class="px-3 py-3 text-right font-mono">3</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Order status lookup') }}</div>
                            <div class="text-[10.5px] text-ink-500">5 nodes · trigger: keyword "order"</div>
                        </td>
                        <td class="px-3 py-3">{{ __('QuickShop') }}</td>
                        <td class="px-3 py-3 text-right font-mono">3,182</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-coral">71%</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-coral">412</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Audit') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Lead qualification') }}</div>
                            <div class="text-[10.5px] text-ink-500">8 nodes · trigger: form fill</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Lumina') }}</td>
                        <td class="px-3 py-3 text-right font-mono">912</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">94%</td>
                        <td class="px-3 py-3 text-right font-mono">8</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Global flow library') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">
                        {{ __('Starter flows shipped to every workspace') }}</h2>
                </div>
                <button
                    class="rounded-full bg-wa-deep text-paper-0 px-3 py-1.5 text-[11.5px] font-semibold">{{ __('New library entry') }}</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div class="rounded-2xl border border-paper-200 p-4">
                    <h3 class="font-semibold text-[14px]">{{ __('Welcome & opt-in') }}</h3>
                    <p class="text-[12px] text-ink-600 mt-2">
                        {{ __('First-touch handshake, opt-in confirmation, simple menu.') }}</p>
                    <div class="mt-3 text-[11px] text-ink-500 font-mono">{{ __('Used by 92%') }}</div>
                </div>
                <div class="rounded-2xl border border-paper-200 p-4">
                    <h3 class="font-semibold text-[14px]">{{ __('Order status lookup') }}</h3>
                    <p class="text-[12px] text-ink-600 mt-2">
                        {{ __('Customer asks "where is my order" → look up Shopify/Woo and reply.') }}</p>
                    <div class="mt-3 text-[11px] text-ink-500 font-mono">{{ __('Used by 78%') }}</div>
                </div>
                <div class="rounded-2xl border border-paper-200 p-4">
                    <h3 class="font-semibold text-[14px]">{{ __('Cart recovery') }}</h3>
                    <p class="text-[12px] text-ink-600 mt-2">
                        {{ __('Webhook → 24h drip with discount code on the third message.') }}</p>
                    <div class="mt-3 text-[11px] text-ink-500 font-mono">{{ __('Used by 64%') }}</div>
                </div>
                <div class="rounded-2xl border border-paper-200 p-4">
                    <h3 class="font-semibold text-[14px]">{{ __('Appointment reminder') }}</h3>
                    <p class="text-[12px] text-ink-600 mt-2">24h + 1h reminders with reschedule branch.</p>
                    <div class="mt-3 text-[11px] text-ink-500 font-mono">{{ __('Used by 41%') }}</div>
                </div>
                <div class="rounded-2xl border border-paper-200 p-4">
                    <h3 class="font-semibold text-[14px]">{{ __('Lead qualification') }}</h3>
                    <p class="text-[12px] text-ink-600 mt-2">
                        {{ __('Score by answers, route hot leads to a human agent.') }}</p>
                    <div class="mt-3 text-[11px] text-ink-500 font-mono">{{ __('Used by 38%') }}</div>
                </div>
                <div class="rounded-2xl border border-paper-200 p-4">
                    <h3 class="font-semibold text-[14px]">{{ __('Feedback survey') }}</h3>
                    <p class="text-[12px] text-ink-600 mt-2">
                        {{ __('CSAT 1–5 with branch into a free-text follow-up below 4.') }}</p>
                    <div class="mt-3 text-[11px] text-ink-500 font-mono">{{ __('Used by 35%') }}</div>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
