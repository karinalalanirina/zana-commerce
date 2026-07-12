<x-layouts.admin :title="__('Support agents')" admin-key="agents">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Support agents') }}</span>
        </div>
        <div class="relative flex-1 min-w-0 max-w-[520px] ml-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-500"
                fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="7" cy="7" r="5" />
                <path d="m11 11 3 3" />
            </svg>
            <input
                class="w-full rounded-full bg-paper-50 border border-paper-200 pl-10 pr-3 py-2 text-[12.5px] focus:outline-none focus:border-wa-deep focus:bg-paper-0 transition"
                placeholder="{{ __('Search agents...') }}" />
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Support') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[30px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Support') }}
                    <span class="italic text-wa-deep">{{ __('agents') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Roster of agents who handle support tickets. Set teams, capacity, and working hours from here.') }}
                </p>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/support') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Back to inbox') }}</a>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2"><svg
                        viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M8 3v10M3 8h10" />
                    </svg>{{ __('Add agent') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active agents') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">8</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 5 teams') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Online now') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">6</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('2 on break') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Near capacity') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">2</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('≥ 90% load') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Median CSAT') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">4.7</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('last 30 days') }}</div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200 flex items-center flex-wrap justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Roster') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('All agents') }}</h2>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('All') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Online') }}</button>
                    <button
                        class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Offline') }}</button>
                </div>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-4 py-2.5 w-[28px]"></th>
                        <th class="text-left px-3 py-2.5 font-medium">{{ __('Agent') }}</th>
                        <th class="text-left px-3 py-2.5 w-[130px] font-medium">{{ __('Team') }}</th>
                        <th class="text-left px-3 py-2.5 w-[170px] font-medium">{{ __('Workload') }}</th>
                        <th class="text-right px-3 py-2.5 w-[90px] font-medium">{{ __('Solved 30d') }}</th>
                        <th class="text-right px-3 py-2.5 w-[80px] font-medium">{{ __('SLA') }}</th>
                        <th class="text-right px-3 py-2.5 w-[80px] font-medium">{{ __('CSAT') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[110px] font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-wa-deep grid place-items-center text-paper-0 text-[11px] font-semibold">
                                    RA</div>
                                <div>
                                    <div class="font-semibold">{{ __('Riya Arora') }}</div>
                                    <div class="text-[10.5px] text-ink-500">riya@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Messaging') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">9 /
                                    12</span><span class="text-ink-500">75%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-wa-deep rounded-full" style="width:75%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">128</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">98%</td>
                        <td class="px-3 py-3 text-right font-mono">4.8</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    AM</div>
                                <div>
                                    <div class="font-semibold">{{ __('Aman Mehta') }}</div>
                                    <div class="text-[10.5px] text-ink-500">aman@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-accent-amber/15 text-[#7B5A14] text-[10px] font-semibold">{{ __('Billing') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">6 /
                                    12</span><span class="text-ink-500">50%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-wa-deep rounded-full" style="width:50%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">96</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">95%</td>
                        <td class="px-3 py-3 text-right font-mono">4.7</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-accent-amber"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    NI</div>
                                <div>
                                    <div class="font-semibold">{{ __('Nia Iyer') }}</div>
                                    <div class="text-[10.5px] text-ink-500">nia@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Access') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">11 /
                                    12</span><span class="text-accent-amber">92%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-accent-amber rounded-full" style="width:92%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">114</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">94%</td>
                        <td class="px-3 py-3 text-right font-mono">4.6</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    KP</div>
                                <div>
                                    <div class="font-semibold">{{ __('Kiran Patel') }}</div>
                                    <div class="text-[10.5px] text-ink-500">kiran@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#FFE6E6] text-accent-coral text-[10px] font-semibold">{{ __('Meta Ads') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">7 /
                                    12</span><span class="text-ink-500">58%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-wa-deep rounded-full" style="width:58%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">82</td>
                        <td class="px-3 py-3 text-right font-mono">90%</td>
                        <td class="px-3 py-3 text-right font-mono">4.5</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-paper-300"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    DV</div>
                                <div>
                                    <div class="font-semibold">{{ __('Devansh Vora') }}</div>
                                    <div class="text-[10.5px] text-ink-500">dev@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('Engineering') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">5 /
                                    12</span><span class="text-ink-500">42%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-accent-coral rounded-full" style="width:42%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">28</td>
                        <td class="px-3 py-3 text-right font-mono">88%</td>
                        <td class="px-3 py-3 text-right font-mono">—</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    SR</div>
                                <div>
                                    <div class="font-semibold">{{ __('Sanya Rao') }}</div>
                                    <div class="text-[10.5px] text-ink-500">sanya@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#D9E5F2] text-[#13478A] text-[10px] font-semibold">{{ __('Templates') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">8 /
                                    12</span><span class="text-ink-500">66%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-wa-deep rounded-full" style="width:66%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">73</td>
                        <td class="px-3 py-3 text-right font-mono text-wa-deep">93%</td>
                        <td class="px-3 py-3 text-right font-mono">4.7</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-paper-300"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    TM</div>
                                <div>
                                    <div class="font-semibold">{{ __('Tara Menon') }}</div>
                                    <div class="text-[10.5px] text-ink-500">tara@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Messaging') }}</span>
                        </td>
                        <td class="px-3 py-3 text-[11px] text-ink-500">{{ __('Off-shift until 9 AM') }}</td>
                        <td class="px-3 py-3 text-right font-mono">88</td>
                        <td class="px-3 py-3 text-right font-mono">96%</td>
                        <td class="px-3 py-3 text-right font-mono">4.8</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-4 py-3"><span class="block w-2 h-2 rounded-full bg-accent-amber"></span></td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-paper-100 grid place-items-center text-ink-700 text-[11px] font-semibold">
                                    JK</div>
                                <div>
                                    <div class="font-semibold">{{ __('Joshua Kim') }}</div>
                                    <div class="text-[10.5px] text-ink-500">josh@wadesk.in</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('Integrations') }}</span>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex justify-between text-[10.5px] mb-1"><span class="font-mono">11 /
                                    12</span><span class="text-accent-amber">91%</span></div>
                            <div class="h-1.5 bg-paper-100 rounded-full">
                                <div class="h-full bg-accent-amber rounded-full" style="width:91%"></div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">62</td>
                        <td class="px-3 py-3 text-right font-mono">89%</td>
                        <td class="px-3 py-3 text-right font-mono">4.4</td>
                        <td class="pl-3 pr-5 py-3 text-right"><a href="{{ url('/admin/agents/1') }}"
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px] hover:bg-paper-50">{{ __('Open') }}</a>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

    </main>

</x-layouts.admin>
