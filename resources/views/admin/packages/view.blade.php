<x-layouts.admin :title="__('Admin · Package · Pro')" admin-key="packages">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/packages') }}" class="hover:text-ink-900">{{ __('Packages') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Pro') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <span
                class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono inline-flex items-center gap-1.5"><span
                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active · 84 subscribers</span>
            <button
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Duplicate') }}</button>
            <a href="{{ url('/admin/packages/create') }}"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                </svg>
                Edit
            </a>
        </div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <!-- Hero -->
        <section class="bg-paper-0 border-2 border-wa-deep rounded-2xl p-6 shadow-card relative">
            <span
                class="absolute -top-3 left-6 px-2.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-semibold uppercase tracking-wider">{{ __('Most popular') }}</span>
            <div class="flex flex-col lg:flex-row items-start justify-between gap-6">
                <div class="min-w-0">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('plan_pro_v5 · created 2025-01-12') }}</div>
                    <h1 class="font-serif text-[28px] sm:text-[40px] leading-tight mt-1">{{ __('Pro') }}</h1>
                    <div class="mt-2 flex items-baseline gap-1.5"><span class="font-serif text-[36px]">$899</span><span
                            class="text-[13px] text-ink-500">/ month · USD</span></div>
                    <p class="text-[13px] text-ink-600 mt-3 max-w-2xl">
                        {{ __('For growing teams ready to scale outbound & inbox automation. 8M messages, full automation suite, priority support.') }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 w-full lg:w-[360px] shrink-0">
                    <div class="rounded-2xl bg-wa-bubble border border-wa-green/30 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Subscribers') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1 text-wa-deep">84</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('MRR') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$75.5k</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">
                            {{ __('Churn (30d)') }}</div>
                        <div class="font-serif text-[28px] leading-none mt-1">1.8%</div>
                    </div>
                    <div class="rounded-2xl bg-paper-50 border border-paper-200 px-4 py-3">
                        <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500">{{ __('Avg LTV') }}
                        </div>
                        <div class="font-serif text-[28px] leading-none mt-1">$14.2k</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Limits grid -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Messages') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('Volume caps') }}</h3>
                <dl class="text-[12.5px] space-y-2">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Monthly') }}</dt>
                        <dd class="font-mono">8,000,000</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Daily') }}</dt>
                        <dd class="font-mono">500,000</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Per-minute throttle') }}</dt>
                        <dd class="font-mono">80</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Free WA messages') }}</dt>
                        <dd class="font-mono">100,000</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Overage rate') }}</dt>
                        <dd class="font-mono">$0.0008</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Free CTWA clicks') }}</dt>
                        <dd class="font-mono">50,000</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Contacts & audience') }}</div>
                <h3 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('Database caps') }}</h3>
                <dl class="text-[12.5px] space-y-2">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Max contacts') }}</dt>
                        <dd class="font-mono">500,000</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Contact groups') }}</dt>
                        <dd class="font-mono">200</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Custom fields') }}</dt>
                        <dd class="font-mono">50</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('CSV import / day') }}</dt>
                        <dd class="font-mono">50,000</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Saved segments') }}</dt>
                        <dd class="font-mono">100</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Audience cap') }}</dt>
                        <dd class="font-mono">100,000</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Team & devices') }}
                </div>
                <h3 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('Seats') }}</h3>
                <dl class="text-[12.5px] space-y-2">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Max users') }}</dt>
                        <dd class="font-mono">50</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Max devices') }}</dt>
                        <dd class="font-mono">10</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Custom roles') }}</dt>
                        <dd class="font-mono">20</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Concurrent agents') }}</dt>
                        <dd class="font-mono">50</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Internal notes / msg') }}</dt>
                        <dd class="font-mono">100</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-600">{{ __('Audit retention') }}</dt>
                        <dd class="font-mono">90 days</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- Features matrix -->
        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Features & integrations') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __("What's included") }}</h2>
                </div>
                <span class="font-mono text-[11px] text-wa-deep">9 of 10 enabled</span>
            </div>
            <ul class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-2 text-[12.5px]">
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('Flow builder') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('REST API access') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('Shopify integration') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('WooCommerce integration') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('AI auto-reply') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('Click-to-WhatsApp ads') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('Custom domain') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('Custom invoice logo') }}</li>
                <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-wa-deep"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 8l3 3 7-7" />
                    </svg>{{ __('Priority support (2h SLA)') }}</li>
                <li class="flex items-center gap-2 text-ink-500"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>{{ __('SSO / SAML') }}</li>
                <li class="flex items-center gap-2 text-ink-500"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>{{ __('Audit log export') }}</li>
                <li class="flex items-center gap-2 text-ink-500"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>{{ __('Dedicated CSM') }}</li>
            </ul>
        </section>

        <!-- Subscribers + audit -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <div class="lg:col-span-8 bg-paper-0 border border-paper-200 rounded-2xl overflow-hidden shadow-card">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Subscribers · top 5') }}</div>
                        <h2 class="font-serif text-[20px] leading-tight mt-1">{{ __("Who's on this plan") }}</h2>
                    </div>
                    <a href="{{ url('/admin/workspaces') }}"
                        class="text-[12px] font-semibold text-wa-deep hover:underline">{{ __('View all 84 →') }}</a>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[660px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-3">{{ __('Workspace') }}</th>
                            <th class="text-left px-3 py-3 w-[140px]">{{ __('Owner') }}</th>
                            <th class="text-right px-3 py-3 w-[100px]">{{ __('MRR') }}</th>
                            <th class="text-left px-3 py-3 w-[120px]">{{ __('Subscribed') }}</th>
                            <th class="text-center px-4 py-3 w-[100px]">{{ __('Health') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-semibold">{{ __('Bloomly') }}</td>
                            <td class="px-3 py-3 text-[11.5px]">{{ __('Vetrick R.') }}</td>
                            <td class="px-3 py-3 text-right font-mono text-wa-deep">$899</td>
                            <td class="px-3 py-3 font-mono text-[10.5px]">2024-08-12</td>
                            <td class="px-4 py-3 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Good</span></td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-semibold">{{ __('FitKart') }}</td>
                            <td class="px-3 py-3 text-[11.5px]">{{ __('Meera S.') }}</td>
                            <td class="px-3 py-3 text-right font-mono text-wa-deep">$1,499</td>
                            <td class="px-3 py-3 font-mono text-[10.5px]">2024-09-04</td>
                            <td class="px-4 py-3 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Good</span></td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-semibold">{{ __('Lumina Beauty') }}</td>
                            <td class="px-3 py-3 text-[11.5px]">{{ __('Priya I.') }}</td>
                            <td class="px-3 py-3 text-right font-mono text-wa-deep">$799</td>
                            <td class="px-3 py-3 font-mono text-[10.5px]">2025-01-22</td>
                            <td class="px-4 py-3 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Good</span></td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-semibold">{{ __('DesignHub') }}</td>
                            <td class="px-3 py-3 text-[11.5px]">{{ __('Karthik N.') }}</td>
                            <td class="px-3 py-3 text-right font-mono text-wa-deep">$899</td>
                            <td class="px-3 py-3 font-mono text-[10.5px]">2025-04-11</td>
                            <td class="px-4 py-3 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent-amber/10 text-accent-amber text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>Watch</span></td>
                        </tr>
                        <tr class="hover:bg-paper-50/60">
                            <td class="px-4 py-3 font-semibold">{{ __('EcomLab') }}</td>
                            <td class="px-3 py-3 text-[11.5px]">{{ __('Riya M.') }}</td>
                            <td class="px-3 py-3 text-right font-mono text-wa-deep">$899</td>
                            <td class="px-3 py-3 font-mono text-[10.5px]">2025-08-20</td>
                            <td class="px-4 py-3 text-center"><span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Good</span></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="lg:col-span-4 bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Plan history') }}
                </div>
                <h2 class="font-serif text-[20px] leading-tight mt-1 mb-3">{{ __('Audit trail') }}</h2>
                <ol class="space-y-2.5 text-[11.5px]">
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green mt-1.5 shrink-0"></span><span>{{ __('Plan created · v1 ·') }}
                            <span class="text-ink-500">2024-12-04</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>Price raised
                            $799 → $899 · <span class="text-ink-500">2025-04-11</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>{{ __('Added "AI auto-reply" feature ·') }}
                            <span class="text-ink-500">2025-09-02</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>{{ __('Bumped daily cap 300k → 500k ·') }}
                            <span class="text-ink-500">2025-12-18</span></span></li>
                    <li class="flex gap-2"><span
                            class="w-1.5 h-1.5 rounded-full bg-accent-amber mt-1.5 shrink-0"></span><span>{{ __('Marked "Most popular" ·') }}
                            <span class="text-ink-500">2026-01-14</span></span></li>
                </ol>
                <div class="mt-4 pt-4 border-t border-paper-200">
                    <button
                        class="w-full text-left px-3 py-2 rounded-xl border border-accent-coral/40 bg-accent-coral/5 text-accent-coral text-[12.5px] font-semibold flex items-center justify-between">{{ __('Archive package') }}<span
                            class="text-[11px]">→</span></button>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
