<x-layouts.admin :title="__('Admin · Devices')" admin-key="devices" page="devices-index">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Devices') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <!-- Heading -->
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Platform devices') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('All') }}
                    <span class="italic text-wa-deep">{{ __('devices') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every paired WhatsApp number across the platform. Force re-pair, disconnect, or transfer ownership without leaving the admin console.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1 flex-wrap">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>284 connected</span>
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                    </svg>
                    Bulk re-sync
                </button>
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                        stroke-width="1.7">
                        <path d="M8 2v8M5 7l3 3 3-3M3 12v2h10v-2" />
                    </svg>
                    Export CSV
                </button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3v10M3 8h10" />
                    </svg>
                    Pair on behalf
                </button>
            </div>
        </div>

        <!-- KPI strip -->
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Total devices') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">+18 this week</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">312</span><span
                        class="text-[11px] text-ink-500">{{ __('across 142 wks') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Connected') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">91.0%</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">284</span><span
                        class="text-[11px] text-wa-deep">{{ __('healthy') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Needs re-pair') }}</span><span
                        class="text-[10px] text-accent-amber font-mono">{{ __('action req') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">14</span><span
                        class="text-[11px] text-ink-500">{{ __('expired QR') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Disconnected') }}</span><span
                        class="text-[10px] text-accent-coral font-mono">{{ __('offline') }}</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">14</span><span
                        class="text-[11px] text-ink-500">{{ __('over 24h') }}</span></div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="flex items-center justify-between"><span
                        class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Sent (24h)') }}</span><span
                        class="text-[10px] text-wa-deep font-mono">+12% MoM</span></div>
                <div class="mt-2 flex items-baseline gap-2"><span
                        class="font-serif text-[30px] leading-none">2.42M</span><span
                        class="text-[11px] text-ink-500">{{ __('across all') }}</span></div>
            </div>
        </section>

        <!-- Filter bar -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex items-center gap-1 shadow-card flex-wrap">
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0 active">
                {{ __('All') }} <span class="font-mono text-[11px] opacity-80">(312)</span></div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Connected') }} <span class="font-mono text-[11px] opacity-80">284</span></div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Needs re-pair') }} <span class="font-mono text-[11px] text-accent-amber">14</span></div>
            <div
                class="filter-pill inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] text-ink-600 cursor-pointer transition hover:bg-paper-50 [&.active]:bg-ink-900 [&.active]:text-paper-0">
                {{ __('Disconnected') }} <span class="font-mono text-[11px] text-accent-coral">14</span></div>
            <div class="flex-1"></div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <select
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option>{{ __('All workspaces') }}</option>
                    <option>{{ __('Bloomly') }}</option>
                    <option>{{ __('FitKart') }}</option>
                    <option>{{ __('Northstar Clinic') }}</option>
                    <option>{{ __('QuickBite') }}</option>
                </select>
                <select
                    class="hairline border border-paper-200 rounded-full px-3 py-1.5 text-[12px] bg-paper-0 hover:bg-paper-50 focus:outline-none focus:border-wa-deep">
                    <option>{{ __('All regions') }}</option>
                    <option>{{ __('India') }}</option>
                    <option>{{ __('USA') }}</option>
                    <option>{{ __('UAE') }}</option>
                    <option>UK</option>
                </select>
                <div class="relative flex-1 min-w-[180px]">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500"
                        fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="7" cy="7" r="5" />
                        <path d="m11 11 3 3" />
                    </svg>
                    <input placeholder="{{ __('Search name, number, owner…') }}"
                        class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full sm:w-72 focus:outline-none focus:border-wa-deep" />
                </div>
            </div>
        </div>

        <!-- Devices table -->
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] table-fixed min-w-[880px]">
                <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-3 py-2.5 w-[34px]"><input type="checkbox"
                                class="rounded border-paper-300"></th>
                        <th class="text-left px-2 py-2.5 w-[44px]"></th>
                        <th class="text-left px-2 py-2.5">{{ __('Device & number') }}</th>
                        <th class="text-left px-2 py-2.5 w-[150px]">{{ __('Workspace') }}</th>
                        <th class="text-left px-2 py-2.5 w-[130px]">{{ __('Owner') }}</th>
                        <th class="text-left px-2 py-2.5 w-[100px]">{{ __('Last sync') }}</th>
                        <th class="text-right px-2 py-2.5 w-[80px]">{{ __('Sent 24h') }}</th>
                        <th class="text-center px-2 py-2.5 w-[110px]">{{ __('Status') }}</th>
                        <th class="text-center px-2 py-2.5 w-[44px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">

                    <!-- Row 1: Bloomly Sales line · Connected -->
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-3 py-2"><input type="checkbox" class="rounded border-paper-300"></td>
                        <td class="px-2 py-2"><span
                                class="w-9 h-9 rounded-lg bg-wa-mint text-wa-deep grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                    <circle cx="8" cy="11.5" r="0.8" />
                                </svg></span></td>
                        <td class="px-2 py-2 min-w-0">
                            <div class="font-semibold leading-none text-[12px] truncate">{{ __('Sales line') }}</div>
                            <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">+91 98765 43210
                                · iPhone 14 · IN</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="text-[12px] font-semibold leading-none truncate">{{ __('Bloomly') }}</div>
                            <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-1">
                                {{ __('Pro') }}</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1.5"><span
                                    class="w-5 h-5 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 grid place-items-center text-[8.5px] font-bold">VR</span><span
                                    class="text-[11.5px] truncate">{{ __('Vetrick R.') }}</span></div>
                        </td>
                        <td class="px-2 py-2 font-mono text-[10.5px] text-wa-deep whitespace-nowrap">
                            {{ __('just now') }}</td>
                        <td class="px-2 py-2 font-mono text-[11.5px] text-right">3,128</td>
                        <td class="px-2 py-2 text-center"><span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected</span></td>
                        <td class="px-2 py-2 text-center">
                            <div class="relative inline-block"><button type="button"
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                    onclick="toggleDevMenu(event,this)" title="{{ __('Actions') }}"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                        <circle cx="3" cy="8" r="1.2" />
                                        <circle cx="8" cy="8" r="1.2" />
                                        <circle cx="13" cy="8" r="1.2" />
                                    </svg></button>
                                <div
                                    class="dev-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                    <a href="{{ url('/admin/devices/1') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                        </svg>{{ __('View analytics') }}</a>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                        </svg>{{ __('Refresh session') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="2" width="5" height="5" />
                                            <rect x="9" y="2" width="5" height="5" />
                                            <rect x="2" y="9" width="5" height="5" />
                                        </svg>{{ __('Re-pair QR') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 8h10M9 4l4 4-4 4" />
                                        </svg>{{ __('Transfer to workspace') }}</button>
                                    <div class="border-t border-paper-200 my-1"></div>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-amber hover:bg-accent-amber/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.7">
                                            <path d="M5 7L3 9l4 4 2-2M11 9l2-2-4-4-2 2" />
                                        </svg>{{ __('Force disconnect') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                        </svg>{{ __('Remove device') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Row 2: FitKart Support · Connected -->
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-3 py-2"><input type="checkbox" class="rounded border-paper-300"></td>
                        <td class="px-2 py-2"><span
                                class="w-9 h-9 rounded-lg bg-[#D9E5F2] text-[#13478A] grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                    <circle cx="8" cy="11.5" r="0.8" />
                                </svg></span></td>
                        <td class="px-2 py-2 min-w-0">
                            <div class="font-semibold leading-none text-[12px] truncate">{{ __('Support line') }}
                            </div>
                            <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">+1 415 555 0142
                                · Pixel 8 · USA</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="text-[12px] font-semibold leading-none truncate">{{ __('FitKart') }}</div>
                            <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-1">
                                {{ __('Pro') }}</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1.5"><span
                                    class="w-5 h-5 rounded-full bg-gradient-to-br from-accent-amber to-accent-coral text-paper-0 grid place-items-center text-[8.5px] font-bold">MS</span><span
                                    class="text-[11.5px] truncate">{{ __('Meera S.') }}</span></div>
                        </td>
                        <td class="px-2 py-2 font-mono text-[10.5px] text-wa-deep whitespace-nowrap">2 min ago</td>
                        <td class="px-2 py-2 font-mono text-[11.5px] text-right">2,418</td>
                        <td class="px-2 py-2 text-center"><span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected</span></td>
                        <td class="px-2 py-2 text-center">
                            <div class="relative inline-block"><button type="button"
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                    onclick="toggleDevMenu(event,this)" title="{{ __('Actions') }}"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                        <circle cx="3" cy="8" r="1.2" />
                                        <circle cx="8" cy="8" r="1.2" />
                                        <circle cx="13" cy="8" r="1.2" />
                                    </svg></button>
                                <div
                                    class="dev-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                    <a href="{{ url('/admin/devices/1') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                        </svg>{{ __('View analytics') }}</a>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                        </svg>{{ __('Refresh session') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="2" width="5" height="5" />
                                            <rect x="9" y="2" width="5" height="5" />
                                            <rect x="2" y="9" width="5" height="5" />
                                        </svg>{{ __('Re-pair QR') }}</button>
                                    <div class="border-t border-paper-200 my-1"></div>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-amber hover:bg-accent-amber/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.7">
                                            <path d="M5 7L3 9l4 4 2-2M11 9l2-2-4-4-2 2" />
                                        </svg>{{ __('Force disconnect') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                        </svg>{{ __('Remove device') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Row 3: Northstar Marketing · Connected -->
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-3 py-2"><input type="checkbox" class="rounded border-paper-300"></td>
                        <td class="px-2 py-2"><span
                                class="w-9 h-9 rounded-lg bg-[#F3E9FF] text-[#5B3D8A] grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                    <circle cx="8" cy="11.5" r="0.8" />
                                </svg></span></td>
                        <td class="px-2 py-2 min-w-0">
                            <div class="font-semibold leading-none text-[12px] truncate">{{ __('Clinic line') }}</div>
                            <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">+91 99820 11423
                                · Galaxy S23 · IN</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="text-[12px] font-semibold leading-none truncate">{{ __('Northstar Clinic') }}
                            </div>
                            <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-1">
                                {{ __('Enterprise') }}</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1.5"><span
                                    class="w-5 h-5 rounded-full bg-[#D9E5F2] text-[#13478A] grid place-items-center text-[8.5px] font-bold">AM</span><span
                                    class="text-[11.5px] truncate">{{ __('Anya M.') }}</span></div>
                        </td>
                        <td class="px-2 py-2 font-mono text-[10.5px] text-ink-700 whitespace-nowrap">22 min ago</td>
                        <td class="px-2 py-2 font-mono text-[11.5px] text-right">1,866</td>
                        <td class="px-2 py-2 text-center"><span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono"><span
                                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Connected</span></td>
                        <td class="px-2 py-2 text-center">
                            <div class="relative inline-block"><button type="button"
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                    onclick="toggleDevMenu(event,this)" title="{{ __('Actions') }}"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                        <circle cx="3" cy="8" r="1.2" />
                                        <circle cx="8" cy="8" r="1.2" />
                                        <circle cx="13" cy="8" r="1.2" />
                                    </svg></button>
                                <div
                                    class="dev-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                    <a href="{{ url('/admin/devices/1') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                        </svg>{{ __('View analytics') }}</a>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path
                                                d="M3 8a5 5 0 0 1 8.5-3.5L13 6M13 8a5 5 0 0 1-8.5 3.5L3 10M13 3v3h-3M3 13v-3h3" />
                                        </svg>{{ __('Refresh session') }}</button>
                                    <div class="border-t border-paper-200 my-1"></div>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-amber hover:bg-accent-amber/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.7">
                                            <path d="M5 7L3 9l4 4 2-2M11 9l2-2-4-4-2 2" />
                                        </svg>{{ __('Force disconnect') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                        </svg>{{ __('Remove device') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Row 4: QuickBite · Needs re-pair -->
                    <tr class="hover:bg-paper-50/60 bg-accent-amber/5">
                        <td class="px-3 py-2"><input type="checkbox" class="rounded border-paper-300"></td>
                        <td class="px-2 py-2"><span
                                class="w-9 h-9 rounded-lg bg-accent-amber/15 text-accent-amber grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                    <circle cx="8" cy="11.5" r="0.8" />
                                </svg></span></td>
                        <td class="px-2 py-2 min-w-0">
                            <div class="font-semibold leading-none text-[12px] truncate">{{ __('Orders line') }}</div>
                            <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">+91 90211 87332
                                · OnePlus 11 · IN</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="text-[12px] font-semibold leading-none truncate">{{ __('QuickBite') }}</div>
                            <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-1">
                                {{ __('Starter') }}</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1.5"><span
                                    class="w-5 h-5 rounded-full bg-[#FFF4E0] text-[#7B5A14] grid place-items-center text-[8.5px] font-bold">RT</span><span
                                    class="text-[11.5px] truncate">{{ __('Ravi T.') }}</span></div>
                        </td>
                        <td class="px-2 py-2 font-mono text-[10.5px] text-accent-amber whitespace-nowrap">
                            {{ __('QR expired') }}</td>
                        <td class="px-2 py-2 font-mono text-[11.5px] text-right text-ink-500">—</td>
                        <td class="px-2 py-2 text-center"><span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-accent-amber/10 text-accent-amber text-[10.5px] font-mono"><span
                                    class="w-1.5 h-1.5 rounded-full bg-accent-amber"></span>Re-pair</span></td>
                        <td class="px-2 py-2 text-center">
                            <div class="relative inline-block"><button type="button"
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                    onclick="toggleDevMenu(event,this)" title="{{ __('Actions') }}"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                        <circle cx="3" cy="8" r="1.2" />
                                        <circle cx="8" cy="8" r="1.2" />
                                        <circle cx="13" cy="8" r="1.2" />
                                    </svg></button>
                                <div
                                    class="dev-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                    <a href="{{ url('/admin/devices/1') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                        </svg>{{ __('View analytics') }}</a>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-wa-deep hover:bg-wa-bubble/40"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="2" width="5" height="5" />
                                            <rect x="9" y="2" width="5" height="5" />
                                            <rect x="2" y="9" width="5" height="5" />
                                        </svg>{{ __('Generate new QR') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 8l5-5v3h7v4H7v3z" />
                                        </svg>{{ __('Email owner to re-pair') }}</button>
                                    <div class="border-t border-paper-200 my-1"></div>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                        </svg>{{ __('Remove device') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- Row 5: Lumina · Disconnected -->
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-3 py-2"><input type="checkbox" class="rounded border-paper-300"></td>
                        <td class="px-2 py-2"><span
                                class="w-9 h-9 rounded-lg bg-paper-100 text-ink-500 grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="3.5" y="2" width="9" height="12" rx="1.5" />
                                    <circle cx="8" cy="11.5" r="0.8" />
                                </svg></span></td>
                        <td class="px-2 py-2 min-w-0">
                            <div class="font-semibold leading-none text-[12px] truncate">{{ __('Beauty line') }}</div>
                            <div class="text-[10px] text-ink-500 mt-1 font-mono leading-none truncate">+91 78921 54006
                                · iPhone 13 · IN</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="text-[12px] font-semibold leading-none truncate">{{ __('Lumina Beauty') }}
                            </div>
                            <div class="text-[9.5px] text-ink-500 font-mono uppercase tracking-[0.12em] mt-1">
                                {{ __('Pro') }}</div>
                        </td>
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1.5"><span
                                    class="w-5 h-5 rounded-full bg-paper-100 text-ink-500 grid place-items-center text-[8.5px] font-bold">PI</span><span
                                    class="text-[11.5px] truncate">{{ __('Priya I.') }}</span></div>
                        </td>
                        <td class="px-2 py-2 font-mono text-[10.5px] text-accent-coral whitespace-nowrap">2 days ago
                        </td>
                        <td class="px-2 py-2 font-mono text-[11.5px] text-right text-ink-500">—</td>
                        <td class="px-2 py-2 text-center"><span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-paper-50 text-ink-500 text-[10.5px] font-mono"><span
                                    class="w-1.5 h-1.5 rounded-full bg-paper-300"></span>Offline</span></td>
                        <td class="px-2 py-2 text-center">
                            <div class="relative inline-block"><button type="button"
                                    class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto"
                                    onclick="toggleDevMenu(event,this)" title="{{ __('Actions') }}"><svg
                                        viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor">
                                        <circle cx="3" cy="8" r="1.2" />
                                        <circle cx="8" cy="8" r="1.2" />
                                        <circle cx="13" cy="8" r="1.2" />
                                    </svg></button>
                                <div
                                    class="dev-action-menu hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                    <a href="{{ url('/admin/devices/1') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                                        </svg>{{ __('View analytics') }}</a>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-wa-deep hover:bg-wa-bubble/40"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <rect x="2" y="2" width="5" height="5" />
                                            <rect x="9" y="2" width="5" height="5" />
                                            <rect x="2" y="9" width="5" height="5" />
                                        </svg>{{ __('Reconnect') }}</button>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 8h10M9 4l4 4-4 4" />
                                        </svg>{{ __('Transfer to workspace') }}</button>
                                    <div class="border-t border-paper-200 my-1"></div>
                                    <button
                                        class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10"><svg
                                            viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" />
                                        </svg>{{ __('Remove device') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>

                </tbody>
            </table>
            </div>

            <div
                class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex items-center justify-between rounded-b-2xl gap-3 flex-wrap">
                <div class="text-[11px] font-mono text-ink-500">{{ __('Showing 5 of 312 devices') }}</div>
                <div class="flex items-center gap-1">
                    <button
                        class="w-7 h-7 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M10 4 6 8l4 4" />
                        </svg></button>
                    <button
                        class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[11px] font-semibold">1</button>
                    <button
                        class="w-7 h-7 rounded-full hover:bg-paper-50 grid place-items-center text-[11px]">2</button>
                    <button
                        class="w-7 h-7 rounded-full hover:bg-paper-50 grid place-items-center text-[11px]">3</button>
                    <span class="text-[11px] text-ink-500 px-1">…</span>
                    <button
                        class="w-7 h-7 rounded-full hover:bg-paper-50 grid place-items-center text-[11px]">63</button>
                    <button
                        class="w-7 h-7 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 grid place-items-center"><svg
                            viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M6 4l4 4-4 4" />
                        </svg></button>
                </div>
            </div>
        </div>
    </main>

</x-layouts.admin>
