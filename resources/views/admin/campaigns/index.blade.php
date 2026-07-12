<x-layouts.admin :title="__('Admin · WA Campaigns')" admin-key="campaigns">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Campaigns') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <!-- Page body (full-width, no left rail) -->
    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <!-- Heading -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Workspace · All clients') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('WhatsApp') }}
                    <span class="italic text-wa-deep">{{ __('campaigns') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Broadcast queues, templates, flows, schedule status, and delivery outcomes across every workspace on the platform.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                        <path d="M13 3v3h-3M3 13v-3h3" />
                    </svg>
                    {{ __('Refresh') }}
                </button>
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                    </svg>
                    {{ __('Export CSV') }}
                </button>
                <a href="{{ url('/admin/campaigns/create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    {{ __('New WA campaign') }}
                </a>
            </div>
        </div>

        <!-- KPI stats -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Sent (30d)') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">2.42M</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('+8.4% MoM') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Delivered') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">2.21M</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('91.3% delivery') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Read') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">1.55M</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('70.1% read rate') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Failed') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">17.6k</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('0.7% failure') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Processing') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">68k</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('42 active queues') }}</div>
            </div>
        </section>

        <!-- Filter row -->
        <div class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex items-center flex-wrap gap-1 shadow-card">
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0 active">
                {{ __('All') }} <span class="font-mono text-[11px] opacity-80">(8)</span></div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Recently created') }}</div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Processing') }}</div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Scheduled') }}</div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Failed') }}</div>
            <div class="flex-1"></div>
            <div class="flex items-center gap-1.5">
                <select
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option>{{ __('All workspaces') }}</option>
                    <option>{{ __('Bloomly') }}</option>
                    <option>{{ __('FitKart') }}</option>
                    <option>{{ __('Northstar Clinic') }}</option>
                    <option>{{ __('QuickBite') }}</option>
                </select>
                <div class="relative">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input placeholder="{{ __('Search campaigns…') }}"
                        class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-72 focus:outline-none focus:border-wa-deep" />
                </div>
            </div>
        </div>

        <!-- Campaign cards -->
        <section class="space-y-3">

            <!-- Campaign 1: Completed - Bloomly -->
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span
                            class="w-11 h-11 rounded-2xl bg-wa-bubble border border-wa-green/30 flex items-center justify-center font-semibold text-wa-deep">NY</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-[18px] font-semibold truncate">{{ __('New Year VIP drop') }}</h2>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-ink-900 text-paper-0 text-[10px] font-semibold">{{ __('Completed') }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Bloomly') }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Pro') }}</span>
                            </div>
                            <p class="text-[12px] text-ink-500 mt-1">
                                {{ __('Owner: Vetrick R. · Template message · Coupon quick replies · 9.8k recipients') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Jan 24, 2026') }}</div>
                        <div class="mt-2 flex items-center justify-end gap-1">
                            <a href="{{ url('/admin/campaigns/analytics') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-wa-bubble text-wa-deep grid place-items-center"
                                title="{{ __('Analytics') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M2 8s2.2-4 6-4 6 4 6 4-2.2 4-6 4-6-4-6-4Z" />
                                    <circle cx="8" cy="8" r="2" />
                                </svg>
                            </a>
                            <a href="{{ url('/admin/campaigns/create') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center"
                                title="{{ __('Edit') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                    <path d="M8.5 4.5 11.5 7.5" />
                                </svg>
                            </a>
                            <button type="button"
                                class="w-8 h-8 rounded-full border border-accent-coral/30 bg-paper-0 hover:bg-accent-coral/10 text-accent-coral grid place-items-center"
                                title="{{ __('Delete') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M3 4h10M6 4V2.8h4V4M5 6v7h6V6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Sent') }}</div>
                        <div class="font-semibold tabular-nums">9,840</div>
                    </div>
                    <div class="rounded-xl bg-wa-bubble/60 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Delivered') }}</div>
                        <div class="font-semibold tabular-nums">9,421</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Read') }}</div>
                        <div class="font-semibold tabular-nums">6,812</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Failed') }}</div>
                        <div class="font-semibold tabular-nums">48</div>
                    </div>
                </div>
            </div>

            <!-- Campaign 2: Processing - FitKart -->
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span
                            class="w-11 h-11 rounded-2xl bg-paper-50 border border-paper-200 flex items-center justify-center font-semibold text-ink-700">WS</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-[18px] font-semibold truncate">{{ __('Welcome series v3') }}</h2>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-wa-green/15 text-wa-deep border border-wa-green/30 text-[10px] font-semibold">{{ __('Processing') }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('FitKart') }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Pro') }}</span>
                            </div>
                            <p class="text-[12px] text-ink-500 mt-1">
                                {{ __('Owner: Meera Shah · Custom message + image · Imported leads queue') }}</p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Today 10:30') }}</div>
                        <div class="mt-2 flex items-center justify-end gap-1">
                            <button
                                class="px-3 py-1.5 rounded-full border border-accent-coral/40 text-accent-coral bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Force pause') }}</button>
                            <a href="{{ url('/admin/campaigns/analytics') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-wa-bubble text-wa-deep grid place-items-center"
                                title="{{ __('Analytics') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M2 8s2.2-4 6-4 6 4 6 4-2.2 4-6 4-6-4-6-4Z" />
                                    <circle cx="8" cy="8" r="2" />
                                </svg>
                            </a>
                            <a href="{{ url('/admin/campaigns/create') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center"
                                title="{{ __('Edit') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                    <path d="M8.5 4.5 11.5 7.5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Sent') }}</div>
                        <div class="font-semibold tabular-nums">2,400</div>
                    </div>
                    <div class="rounded-xl bg-wa-bubble/60 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Delivered') }}</div>
                        <div class="font-semibold tabular-nums">2,188</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Read') }}</div>
                        <div class="font-semibold tabular-nums">1,064</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Failed') }}</div>
                        <div class="font-semibold tabular-nums">21</div>
                    </div>
                </div>
            </div>

            <!-- Campaign 3: Scheduled - Northstar -->
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span
                            class="w-11 h-11 rounded-2xl bg-accent-amber/20 border border-accent-amber/30 flex items-center justify-center font-semibold text-ink-700">FL</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-[18px] font-semibold truncate">
                                    {{ __('Diabetes care · April reminder') }}</h2>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-accent-amber/15 text-ink-800 border border-accent-amber/30 text-[10px] font-semibold">{{ __('Scheduled') }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Northstar Clinic') }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Enterprise') }}</span>
                            </div>
                            <p class="text-[12px] text-ink-500 mt-1">
                                {{ __('Owner: Anya Menon · Flow builder · Segmented warm leads · 1.4k recipients') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Apr 29, 2026 · 09:00') }}</div>
                        <div class="mt-2 flex items-center justify-end gap-1">
                            <a href="{{ url('/admin/campaigns/analytics') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-wa-bubble text-wa-deep grid place-items-center"
                                title="{{ __('Preview') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M2 8s2.2-4 6-4 6 4 6 4-2.2 4-6 4-6-4-6-4Z" />
                                    <circle cx="8" cy="8" r="2" />
                                </svg>
                            </a>
                            <a href="{{ url('/admin/campaigns/create') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center"
                                title="{{ __('Edit') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                    <path d="M8.5 4.5 11.5 7.5" />
                                </svg>
                            </a>
                            <button type="button"
                                class="w-8 h-8 rounded-full border border-accent-coral/30 bg-paper-0 hover:bg-accent-coral/10 text-accent-coral grid place-items-center"
                                title="{{ __('Cancel schedule') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M4 4l8 8M12 4l-8 8" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-4">
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Recipients') }}</div>
                        <div class="font-semibold tabular-nums">1,450</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Device') }}</div>
                        <div class="font-semibold">{{ __('Main') }}</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('Type') }}</div>
                        <div class="font-semibold">{{ __('Flow') }}</div>
                    </div>
                    <div class="rounded-xl bg-paper-50 px-3 py-2">
                        <div class="text-[10px] text-ink-500">{{ __('ETA') }}</div>
                        <div class="font-semibold">09:00</div>
                    </div>
                </div>
            </div>

            <!-- Campaign 4: Failed - QuickBite (admin attention) -->
            <div class="bg-paper-0 border border-accent-coral/30 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span
                            class="w-11 h-11 rounded-2xl bg-accent-coral/10 border border-accent-coral/30 flex items-center justify-center font-semibold text-accent-coral">!</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-[18px] font-semibold truncate">{{ __('Flash deal — 50% off pizza') }}
                                </h2>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral border border-accent-coral/30 text-[10px] font-semibold">{{ __('Failed') }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-[#FFF4E0] text-[#7B5A14] text-[10px] font-semibold">{{ __('QuickBite') }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Starter') }}</span>
                            </div>
                            <p class="text-[12px] text-ink-500 mt-1">
                                {{ __('Owner: Ravi Tandon · Template not approved · Workspace daily cap reached') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Apr 26, 2026') }}</div>
                        <div class="mt-2 flex items-center justify-end gap-1">
                            <button
                                class="px-3 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Retry') }}</button>
                            <a href="{{ url('/admin/campaigns/create') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center"
                                title="{{ __('Edit') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                    <path d="M8.5 4.5 11.5 7.5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                <div
                    class="rounded-xl border border-accent-coral/30 bg-accent-coral/10 p-3 mt-3 flex items-start gap-2">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-coral mt-0.5" fill="none"
                        stroke="currentColor" stroke-width="1.7">
                        <circle cx="8" cy="8" r="6" />
                        <path d="M8 5v3.5M8 11h.01" />
                    </svg>
                    <div class="flex-1">
                        <div class="text-[12.5px] font-semibold text-accent-coral">
                            {{ __('Failure reason · Template "flash_pizza_v2" pending Meta approval') }}</div>
                        <div class="text-[11px] text-ink-700 mt-0.5">
                            {{ __('Workspace also exceeded daily message cap (8,000 / 8,000). Approve template or override cap as admin to retry.') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign 5: Recently created - Lumina -->
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span
                            class="w-11 h-11 rounded-2xl bg-wa-bubble border border-wa-green/30 flex items-center justify-center font-semibold text-wa-deep">SP</span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-[18px] font-semibold truncate">{{ __('Spring lipstick launch') }}</h2>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 border border-paper-200 text-[10px] font-semibold">{{ __('Draft') }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Lumina Beauty') }}</span>
                                <span
                                    class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Pro') }}</span>
                            </div>
                            <p class="text-[12px] text-ink-500 mt-1">
                                {{ __('Owner: Priya Iyer · Template campaign · 3.2k recipients · awaiting workspace approval') }}
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Created 2h ago') }}</div>
                        <div class="mt-2 flex items-center justify-end gap-1">
                            <a href="{{ url('/admin/campaigns/create') }}"
                                class="w-8 h-8 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 grid place-items-center"
                                title="{{ __('Edit') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M9.5 3.5 12.5 6.5 6 13H3v-3z" />
                                    <path d="M8.5 4.5 11.5 7.5" />
                                </svg>
                            </a>
                            <button type="button"
                                class="w-8 h-8 rounded-full border border-accent-coral/30 bg-paper-0 hover:bg-accent-coral/10 text-accent-coral grid place-items-center"
                                title="{{ __('Delete') }}">
                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M3 4h10M6 4V2.8h4V4M5 6v7h6V6" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <div class="text-[11px] text-ink-500 font-mono text-center">
            {{ __('Showing 5 of 8 campaigns · across all workspaces · last 30 days') }}
        </div>
    </main>

</x-layouts.admin>
