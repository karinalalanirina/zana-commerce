<x-layouts.admin :title="__('Admin · Meta Ads campaigns')" admin-key="metaads">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Meta Ads') }}</span>
        </div>
        <div class="relative hidden md:block flex-1 max-w-[480px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search campaigns, workspaces, owners…') }}" />
            <kbd
                class="absolute right-3 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-paper-0 border border-paper-200 text-[10px] font-mono text-ink-500">⌘K</kbd>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <!-- Page body -->
    <main class="px-4 sm:px-6 lg:px-7 py-7">
        <!-- Heading -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-5">
            <div>
                <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Workspace · All clients') }}</div>
                <h1 class="serif font-serif font-normal tracking-[-0.01em] text-3xl sm:text-4xl lg:text-[40px] leading-[1.0] tracking-tight">
                    {{ __('Meta Ads') }} <span class="italic text-wa-deep">{{ __('campaigns') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">386 campaigns across 142 workspaces. Auto-syncs every 5 min ·
                    Last sync <b>just now</b>.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10" />
                        <path d="M13 3v3h-3M3 13v-3h3" />
                    </svg>
                    Sync all
                </button>
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                    </svg>
                    Export CSV
                </button>
                <a href="{{ url('/admin/meta-ads/analytics') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                    </svg>
                    Analytics
                </a>
                <a href="{{ url('/admin/meta-ads/keys') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M6 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-1.5L13 12M11 10l-1.5 1.5" />
                    </svg>
                    {{ __('Keys') }}
                </a>
                <a href="{{ url('/admin/meta-ads/create') }}"
                    class="px-4 py-2 rounded-full bg-ink-900 text-paper-0 text-[12px] font-semibold hover:bg-ink-800 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Create campaign
                </a>
            </div>
        </div>

        <!-- Stat row -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total campaigns') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">386</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 142 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">214</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('running now') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total spend (30d)') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">$182k</div>
                <div class="text-[11px] text-ink-500 mt-2">+8.4% MoM</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Pending review') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1 text-accent-coral">14</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('action required') }}</div>
            </div>
        </div>

        <!-- Filters / search bar -->
        <div
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card mb-3">
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0 active">
                {{ __('All') }} <span class="mono font-mono text-[11px] opacity-80">(386)</span></div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Today') }}</div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('High spenders') }}</div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Flagged') }}</div>
            <div class="flex-1"></div>
            <div class="flex items-center gap-1.5">
                <button
                    class="hairline border border-paper-200 rounded-full w-8 h-8 flex items-center justify-center bg-paper-0 hover:bg-paper-50"
                    title="{{ __('Sort') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <path d="M3 4h10M5 8h6M7 12h2" />
                    </svg>
                </button>
                <button
                    class="hairline border border-paper-200 rounded-full w-8 h-8 flex items-center justify-center bg-paper-0 hover:bg-paper-50"
                    title="{{ __('Columns') }}">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.6">
                        <rect x="2" y="3" width="3" height="10" rx="0.5" />
                        <rect x="6.5" y="3" width="3" height="10" rx="0.5" />
                        <rect x="11" y="3" width="3" height="10" rx="0.5" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Campaign cards (admin POV — workspace badge + admin actions) -->
        <div class="space-y-3">

            <!-- Campaign 1: ACTIVE - Bloomly -->
            <div
                class="camp-card bg-white border border-paper-200 rounded-2xl px-5 py-[18px] transition hover:border-wa-deep/25 hover:shadow-soft">
                <div class="flex flex-wrap items-start gap-3 mb-3">
                    <span
                        class="w-9 h-9 rounded-full bg-wa-bubble text-wa-deep flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[15px] font-semibold">{{ __('Meta CTWA — Summer sale') }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Bloomly') }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Pro') }}</span>
                        </div>
                        <div class="text-[12px] text-ink-500 mt-0.5">Owner: Vetrick R. · Messages · Budget: $25.00/day
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0 ml-auto">
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-green/10 text-wa-deep border border-wa-green/30"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
                        <button
                            class="hairline border border-accent-coral/40 text-accent-coral rounded-full px-3 py-1.5 text-[12px] font-medium bg-paper-0 hover:bg-accent-coral/10 flex items-center gap-1.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <circle cx="6" cy="6" r="5" />
                                <path d="M3.5 3.5l5 5" />
                            </svg>
                            Force pause
                        </button>
                        <a href="{{ url('/admin/meta-ads/analytics/1') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-wa-bubble text-wa-deep flex items-center justify-center"
                            title="{{ __('Analytics') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                            </svg>
                        </a>
                        <a href="{{ url('/admin/meta-ads/1/edit') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                            title="{{ __('Edit') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 11.5V13h1.5L11.8 5.7l-1.5-1.5L3 11.5Z" />
                                <path d="M9.4 5.1l1.5 1.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-3">
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Spend') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">$412.50</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Impressions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">84,210</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Clicks') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">2,210</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Reach') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">62,410</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Conversions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">184</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CTR') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">2.62%</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CPC') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">$0.19</div>
                    </div>
                    <div class="metric bg-wa-bubble border border-wa-deep/20 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-wa-deep uppercase">
                            {{ __('Revenue') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 text-wa-deep">$3,210.00
                        </div>
                    </div>
                </div>

                <div
                    class="hairline-t border-t border-paper-200 pt-3 flex flex-wrap items-center gap-x-[18px] gap-y-2 text-[11.5px] text-ink-500 font-mono [&_svg]:text-ink-600">
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="12" height="11" rx="1" />
                            <path d="M2 6h12M5 1.5v3M11 1.5v3" />
                        </svg>{{ __('Created: 2026-04-18') }}</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="9" height="9" rx="1" />
                            <rect x="5" y="6" width="9" height="9" rx="1" />
                        </svg>2 Ad Set(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>3 Ad(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 5l-2 2a2.83 2.83 0 0 0 4 4l1-1M9 11l2-2a2.83 2.83 0 0 0-4-4l-1 1" />
                        </svg>{{ __('FB ID: 1203948…441') }}</span>
                    <span class="ml-auto inline-flex items-center gap-1.5 text-wa-deep"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Policy: clean</span>
                </div>
            </div>

            <!-- Campaign 2: PENDING REVIEW - QuickBite -->
            <div
                class="camp-card bg-white border border-accent-coral/30 rounded-2xl px-5 py-[18px] transition hover:border-accent-coral hover:shadow-soft">
                <div class="flex flex-wrap items-start gap-3 mb-3">
                    <span
                        class="w-9 h-9 rounded-full bg-accent-coral/10 text-accent-coral flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M8 1l7 13H1z" />
                            <path d="M8 6v3M8 11.5h.01" />
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[15px] font-semibold">{{ __('Flash deal — 50% off pizza') }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full bg-[#FFF4E0] text-[#7B5A14] text-[10px] font-semibold">{{ __('QuickBite') }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Starter') }}</span>
                        </div>
                        <div class="text-[12px] text-ink-500 mt-0.5">Owner: Ravi Tandon · Conversions · Budget:
                            $40.00/day</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0 ml-auto">
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent-coral/10 text-accent-coral border border-accent-coral/30"><span
                                class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>Pending review</span>
                        <button
                            class="rounded-full px-3 py-1.5 text-[12px] font-semibold bg-wa-deep hover:bg-wa-teal text-paper-0 flex items-center gap-1.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M2.5 6 5 8.5 9.5 4" />
                            </svg>
                            Approve
                        </button>
                        <button
                            class="hairline border border-accent-coral/40 text-accent-coral rounded-full px-3 py-1.5 text-[12px] font-medium bg-paper-0 hover:bg-accent-coral/10 flex items-center gap-1.5">
                            {{ __('Reject') }}
                        </button>
                        <a href="{{ url('/admin/meta-ads/1/edit') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                            title="{{ __('Edit') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 11.5V13h1.5L11.8 5.7l-1.5-1.5L3 11.5Z" />
                                <path d="M9.4 5.1l1.5 1.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="rounded-xl border border-accent-coral/30 bg-accent-coral/10 p-3 mb-3">
                    <div class="flex items-start gap-2">
                        <svg viewBox="0 0 16 16" class="w-4 h-4 text-accent-coral mt-0.5" fill="none"
                            stroke="currentColor" stroke-width="1.7">
                            <circle cx="8" cy="8" r="6" />
                            <path d="M8 5v3.5M8 11h.01" />
                        </svg>
                        <div class="flex-1">
                            <div class="text-[12.5px] font-semibold text-accent-coral">
                                {{ __('Flagged: ad copy may violate Meta food policy') }}</div>
                            <div class="text-[11px] text-ink-700 mt-0.5">
                                {{ __('Words "guaranteed weight loss" detected in creative_body. Review before activating to avoid Meta auto-rejection.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="hairline-t border-t border-paper-200 pt-3 flex flex-wrap items-center gap-x-[18px] gap-y-2 text-[11.5px] text-ink-500 font-mono [&_svg]:text-ink-600">
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="12" height="11" rx="1" />
                            <path d="M2 6h12M5 1.5v3M11 1.5v3" />
                        </svg>{{ __('Submitted: 2026-04-26') }}</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="9" height="9" rx="1" />
                            <rect x="5" y="6" width="9" height="9" rx="1" />
                        </svg>1 Ad Set(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>2 Ad(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 5l-2 2a2.83 2.83 0 0 0 4 4l1-1M9 11l2-2a2.83 2.83 0 0 0-4-4l-1 1" />
                        </svg>{{ __('FB ID: pending') }}</span>
                    <span class="ml-auto inline-flex items-center gap-1.5 text-accent-coral"><span
                            class="w-1.5 h-1.5 rounded-full bg-accent-coral"></span>Awaiting admin</span>
                </div>
            </div>

            <!-- Campaign 3: ACTIVE - FitKart -->
            <div
                class="camp-card bg-white border border-paper-200 rounded-2xl px-5 py-[18px] transition hover:border-wa-deep/25 hover:shadow-soft">
                <div class="flex flex-wrap items-start gap-3 mb-3">
                    <span
                        class="w-9 h-9 rounded-full bg-wa-bubble text-wa-deep flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[15px] font-semibold">{{ __("Mother's Day — Link clicks") }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('FitKart') }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Pro') }}</span>
                        </div>
                        <div class="text-[12px] text-ink-500 mt-0.5">Owner: Meera Shah · Link Clicks · Budget:
                            $18.00/day</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0 ml-auto">
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-green/10 text-wa-deep border border-wa-green/30"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
                        <button
                            class="hairline border border-accent-coral/40 text-accent-coral rounded-full px-3 py-1.5 text-[12px] font-medium bg-paper-0 hover:bg-accent-coral/10 flex items-center gap-1.5">{{ __('Force pause') }}</button>
                        <a href="{{ url('/admin/meta-ads/analytics/1') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-wa-bubble text-wa-deep flex items-center justify-center"
                            title="{{ __('Analytics') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                            </svg>
                        </a>
                        <a href="{{ url('/admin/meta-ads/1/edit') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                            title="{{ __('Edit') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 11.5V13h1.5L11.8 5.7l-1.5-1.5L3 11.5Z" />
                                <path d="M9.4 5.1l1.5 1.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-3">
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Spend') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">$286.40</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Impressions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">51,280</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Clicks') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">1,890</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Reach') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">41,220</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Conversions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">96</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CTR') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">3.68%</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CPC') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">$0.15</div>
                    </div>
                    <div class="metric bg-wa-bubble border border-wa-deep/20 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-wa-deep uppercase">
                            {{ __('Revenue') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 text-wa-deep">$2,140.00
                        </div>
                    </div>
                </div>

                <div
                    class="hairline-t border-t border-paper-200 pt-3 flex flex-wrap items-center gap-x-[18px] gap-y-2 text-[11.5px] text-ink-500 font-mono [&_svg]:text-ink-600">
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="12" height="11" rx="1" />
                            <path d="M2 6h12M5 1.5v3M11 1.5v3" />
                        </svg>{{ __('Created: 2026-04-11') }}</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="9" height="9" rx="1" />
                            <rect x="5" y="6" width="9" height="9" rx="1" />
                        </svg>1 Ad Set(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>2 Ad(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 5l-2 2a2.83 2.83 0 0 0 4 4l1-1M9 11l2-2a2.83 2.83 0 0 0-4-4l-1 1" />
                        </svg>{{ __('FB ID: 1203948…512') }}</span>
                    <span class="ml-auto inline-flex items-center gap-1.5 text-wa-deep"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Policy: clean</span>
                </div>
            </div>

            <!-- Campaign 4: PAUSED - Northstar -->
            <div
                class="camp-card bg-white border border-paper-200 rounded-2xl px-5 py-[18px] transition hover:border-wa-deep/25 hover:shadow-soft">
                <div class="flex flex-wrap items-start gap-3 mb-3">
                    <span
                        class="w-9 h-9 rounded-full bg-paper-100 text-ink-700 flex items-center justify-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="currentColor">
                            <rect x="5" y="4" width="2" height="8" rx="0.5" />
                            <rect x="9" y="4" width="2" height="8" rx="0.5" />
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[15px] font-semibold">{{ __('Lead gen — Diabetes care') }}</span>
                            <span
                                class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Northstar Clinic') }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded-full bg-paper-100 text-ink-600 text-[10px] font-mono">{{ __('Enterprise') }}</span>
                        </div>
                        <div class="text-[12px] text-ink-500 mt-0.5">Owner: Anya Menon · Lead Generation · Budget:
                            $12.00/day</div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 shrink-0 ml-auto">
                        <span
                            class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-[#EFE5F5] text-[#5B3D8A] border border-[#D9C7E8]"><span
                                class="w-1.5 h-1.5 rounded-full bg-[#5B3D8A]"></span>Paused</span>
                        <button
                            class="rounded-full px-3 py-1.5 text-[12px] font-semibold bg-ink-900 hover:bg-ink-800 text-paper-0 flex items-center gap-1.5">
                            <svg viewBox="0 0 12 12" class="w-3 h-3" fill="currentColor">
                                <polygon points="3,2 10,6 3,10" />
                            </svg>
                            Activate
                        </button>
                        <a href="{{ url('/admin/meta-ads/analytics/1') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-wa-bubble text-wa-deep flex items-center justify-center"
                            title="{{ __('Analytics') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                            </svg>
                        </a>
                        <a href="{{ url('/admin/meta-ads/1/edit') }}"
                            class="hairline border border-paper-200 rounded-full w-8 h-8 bg-paper-0 hover:bg-paper-50 flex items-center justify-center"
                            title="{{ __('Edit') }}">
                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 11.5V13h1.5L11.8 5.7l-1.5-1.5L3 11.5Z" />
                                <path d="M9.4 5.1l1.5 1.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-3">
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Spend') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">$142.90</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Impressions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">24,180</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Clicks') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">612</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Reach') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">19,840</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('Conversions') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">41</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CTR') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">2.53%</div>
                    </div>
                    <div class="metric bg-white border border-paper-200 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-ink-500 uppercase">
                            {{ __('CPC') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5">$0.23</div>
                    </div>
                    <div class="metric bg-wa-bubble border border-wa-deep/20 rounded-[10px] px-3 py-2">
                        <div class="metric-label font-mono text-[10px] tracking-[0.12em] text-wa-deep uppercase">
                            {{ __('Revenue') }}</div>
                        <div class="metric-value text-[14px] font-semibold tabular-nums mt-0.5 text-wa-deep">$860.00
                        </div>
                    </div>
                </div>

                <div
                    class="hairline-t border-t border-paper-200 pt-3 flex flex-wrap items-center gap-x-[18px] gap-y-2 text-[11.5px] text-ink-500 font-mono [&_svg]:text-ink-600">
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="12" height="11" rx="1" />
                            <path d="M2 6h12M5 1.5v3M11 1.5v3" />
                        </svg>{{ __('Created: 2026-04-04') }}</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="3" width="9" height="9" rx="1" />
                            <rect x="5" y="6" width="9" height="9" rx="1" />
                        </svg>1 Ad Set(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="currentColor">
                            <polygon points="6,4 12,8 6,12" />
                        </svg>1 Ad(s)</span>
                    <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5"
                            fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 5l-2 2a2.83 2.83 0 0 0 4 4l1-1M9 11l2-2a2.83 2.83 0 0 0-4-4l-1 1" />
                        </svg>{{ __('FB ID: 1203948…603') }}</span>
                    <span class="ml-auto inline-flex items-center gap-1.5 text-wa-deep"><span
                            class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Policy: clean</span>
                </div>
            </div>

        </div>

        <div class="mt-6 text-[11px] text-ink-500 mono font-mono text-center">
            {{ __('Showing 4 of 386 campaigns · synced from Meta Marketing API · last 30 days') }}
        </div>
    </main>

</x-layouts.admin>
