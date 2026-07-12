<x-layouts.admin :title="__('Broadcasts')" admin-key="broadcasts">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Broadcasts') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Messaging') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Live') }}
                    <span class="italic text-wa-deep">{{ __('broadcasts') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Live + scheduled broadcasts across all workspaces. Pause runaways or hit the global kill switch when Meta throttles.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Pause all') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:bg-accent-coral/90">{{ __('Kill switch') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Live now') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">14</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 11 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Sending now') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">8.4k/min</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('message rate') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Delivery rate') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">96.2%</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('last 24h') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Throttled') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">3</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('near Meta cap') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Scheduled') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">42</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in next 24h') }}</div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Live broadcasts') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Currently sending') }}</h2>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('Live') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Scheduled') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Paused') }}</button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-5 py-2.5 font-medium">{{ __('Broadcast') }}</th>
                        <th class="text-left px-3 py-2.5 w-[150px] font-medium">{{ __('Workspace') }}</th>
                        <th class="text-right px-3 py-2.5 w-[110px] font-medium">{{ __('Audience') }}</th>
                        <th class="text-right px-3 py-2.5 w-[110px] font-medium">{{ __('Sent') }}</th>
                        <th class="text-right px-3 py-2.5 w-[110px] font-medium">{{ __('Delivery') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[110px] font-medium">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('New Year VIP drop') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Started 9:05 AM · device dev_84NY') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Bloomly') }}</td>
                        <td class="px-3 py-3 text-right font-mono">28,400</td>
                        <td class="px-3 py-3 text-right font-mono">9,980</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-amber">85.2%</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Pause') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Cart recovery hot leads') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Started 8:42 AM') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('FitKart') }}</td>
                        <td class="px-3 py-3 text-right font-mono">12,210</td>
                        <td class="px-3 py-3 text-right font-mono">7,180</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">96.8%</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Pause') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Clinic appointment reminder · Jan') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Started 7:55 AM') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Northstar') }}</td>
                        <td class="px-3 py-3 text-right font-mono">3,420</td>
                        <td class="px-3 py-3 text-right font-mono">3,420</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">99.4%</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('View') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Flash sale FOMO') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Started 9:18 AM · throttled by Meta') }}
                            </div>
                        </td>
                        <td class="px-3 py-3">{{ __('PixelPlay') }}</td>
                        <td class="px-3 py-3 text-right font-mono">42,100</td>
                        <td class="px-3 py-3 text-right font-mono">8,940</td>
                        <td class="px-3 py-3 text-right font-mono text-accent-coral">62.4%</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-accent-coral/40 px-3 py-1 text-[11px] text-accent-coral">{{ __('Pause') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3">
                            <div class="font-semibold">{{ __('Onboarding welcome batch') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('Started 9:22 AM') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('QuickShop') }}</td>
                        <td class="px-3 py-3 text-right font-mono">812</td>
                        <td class="px-3 py-3 text-right font-mono">812</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">98.1%</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('View') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Top senders (24h)') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Volume vs Meta cap') }}</h2>
                <div class="space-y-3 text-[12px]">
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('PixelPlay') }} <span
                                    class="text-ink-500">· cap 50k/day</span></span><span
                                class="font-mono text-accent-coral">94%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-accent-coral rounded-full" style="width:94%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('Bloomly') }} <span class="text-ink-500">·
                                    cap 100k/day</span></span><span class="font-mono text-accent-amber">71%</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-accent-amber rounded-full" style="width:71%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('FitKart') }} <span class="text-ink-500">·
                                    cap 50k/day</span></span><span class="font-mono">42%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-deep rounded-full" style="width:42%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('Northstar') }} <span
                                    class="text-ink-500">· cap 250k/day</span></span><span
                                class="font-mono">12%</span></div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-deep rounded-full" style="width:12%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ __('QuickShop') }} <span
                                    class="text-ink-500">· cap 10k/day</span></span><span class="font-mono">8%</span>
                        </div>
                        <div class="h-2 bg-paper-100 rounded-full">
                            <div class="h-full bg-wa-deep rounded-full" style="width:8%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Coming up') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Scheduled in next 24h') }}</h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-4 py-2.5 font-medium">{{ __('Broadcast') }}</th>
                            <th class="text-left px-3 py-2.5 w-[120px] font-medium">{{ __('Workspace') }}</th>
                            <th class="text-right pl-3 pr-5 py-2.5 w-[100px] font-medium">{{ __('When') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr>
                            <td class="px-4 py-2.5">{{ __('Weekend deals teaser') }}</td>
                            <td class="px-3 py-2.5">{{ __('Bloomly') }}</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px]">{{ __('In 2h') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5">{{ __('Cart recovery batch') }}</td>
                            <td class="px-3 py-2.5">{{ __('QuickShop') }}</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px]">{{ __('In 4h') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5">{{ __('Lapsed customers reactivation') }}</td>
                            <td class="px-3 py-2.5">{{ __('FitKart') }}</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px]">{{ __('In 9h') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5">{{ __('Appointment reminders · tomorrow') }}</td>
                            <td class="px-3 py-2.5">{{ __('Northstar') }}</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px]">{{ __('In 14h') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5">{{ __('Welcome batch new signups') }}</td>
                            <td class="px-3 py-2.5">{{ __('Lumina') }}</td>
                            <td class="pl-3 pr-5 py-2.5 text-right font-mono text-[11px]">{{ __('In 18h') }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </section>

    </main>

</x-layouts.admin>
