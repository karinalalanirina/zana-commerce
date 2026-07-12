<x-layouts.admin :title="__('Templates')" admin-key="templates">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Templates') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Messaging') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Meta') }}
                    <span class="italic text-wa-deep">{{ __('templates') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Cross-workspace template approvals, the global library, and Meta-side rejection patterns.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Sync from Meta') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('New global template') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total templates') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">1,284</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 142 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Pending review') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">38</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('awaiting Meta') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Rejected (7d)') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-coral">21</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('need rewrite') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Approval rate') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">88%</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('last 30 days') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Global library') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">47</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('starter templates') }}</div>
            </div>
        </section>

        <section
            class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold">{{ __('Pending') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Approved') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Rejected') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Paused') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Global library') }}</button>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] min-w-[760px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-5 py-2.5 font-medium">{{ __('Template') }}</th>
                        <th class="text-left px-3 py-2.5 w-[160px] font-medium">{{ __('Workspace') }}</th>
                        <th class="text-left px-3 py-2.5 w-[110px] font-medium">{{ __('Category') }}</th>
                        <th class="text-left px-3 py-2.5 w-[90px] font-medium">{{ __('Lang') }}</th>
                        <th class="text-left px-3 py-2.5 w-[120px] font-medium">{{ __('Status') }}</th>
                        <th class="text-right px-3 py-2.5 w-[100px] font-medium">{{ __('Submitted') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[110px] font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('festive_drop_v3') }}</div>
                            <div class="text-[10.5px] text-ink-500">Marketing · "Hey @{{ 1 }}, our New
                                Year drop..."</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('Bloomly') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Growth') }}</div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Marketing') }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('en_US') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-accent-amber/10 text-accent-amber text-[10px] font-mono">{{ __('pending') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">2h ago</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Review') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('order_shipped_v2') }}</div>
                            <div class="text-[10.5px] text-ink-500">Utility · order #@{{ 1 }} shipped, ETA
                                @{{ 2 }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('QuickShop') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Starter') }}</div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Utility') }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('en_IN') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-accent-amber/10 text-accent-amber text-[10px] font-mono">{{ __('pending') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">3h ago</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Review') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('cart_recovery_pro') }}</div>
                            <div class="text-[10.5px] text-ink-500">
                                {{ __('Marketing · abandoned cart with promo code') }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('FitKart') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Pro') }}</div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Marketing') }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('en_US') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral text-[10px] font-mono">{{ __('rejected') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">1d ago</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Rewrite') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('otp_login') }}</div>
                            <div class="text-[10.5px] text-ink-500">Authentication · @{{ 1 }}
                                {{ __('is your code') }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('Lumina') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Pro') }}</div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Auth') }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('en_IN') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-mono">{{ __('approved') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">2d ago</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('appt_reminder') }}</div>
                            <div class="text-[10.5px] text-ink-500">Utility · @{{ 1 }} on
                                @{{ 2 }} at @{{ 3 }}</div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('Northstar Clinic') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Enterprise') }}</div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Utility') }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('en_IN') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-mono">{{ __('approved') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">3d ago</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('vip_promo_drop') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Marketing · 25% off VIP early access') }}
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('PixelPlay') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Pro') }}</div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Marketing') }}</span>
                        </td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('en_US') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral text-[10px] font-mono">{{ __('rejected') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono text-[11px] text-ink-500">3d ago</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Rewrite') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Top rejection reasons') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Last 30 days') }}</h2>
                <div class="space-y-3 text-[12px]">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span>{{ __('Promotional content in Utility category') }}</span><span
                                class="font-mono">38%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-accent-coral rounded-full" style="width:76%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span>{{ __('Missing variable formatting') }}</span><span class="font-mono">24%</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-accent-amber rounded-full" style="width:48%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span>{{ __('Generic / spam-like wording') }}</span><span class="font-mono">18%</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-accent-amber rounded-full" style="width:36%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('Unsupported language') }}</span><span
                                class="font-mono">12%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-paper-300 rounded-full" style="width:24%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('Other') }}</span><span
                                class="font-mono">8%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-paper-300 rounded-full" style="width:16%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Global library') }}
                </div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Starter templates') }}</h2>
                <div class="grid grid-cols-2 gap-3 text-[12px]">
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="font-semibold">{{ __('welcome_message') }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Used by 84% of new workspaces') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="font-semibold">{{ __('order_confirmation') }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Used by 76%') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="font-semibold">{{ __('otp_login') }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Used by 71%') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="font-semibold">{{ __('appt_reminder') }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Used by 64%') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="font-semibold">{{ __('cart_recovery') }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Used by 52%') }}</div>
                    </div>
                    <div class="rounded-xl border border-paper-200 p-3">
                        <div class="font-semibold">{{ __('feedback_survey') }}</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Used by 47%') }}</div>
                    </div>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
