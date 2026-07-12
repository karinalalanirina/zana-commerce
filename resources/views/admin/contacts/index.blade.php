<x-layouts.admin :title="__('Contacts')" admin-key="contacts">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Contacts') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Compliance') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Contact') }}
                    <span class="italic text-wa-deep">{{ __('moderation') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Cross-workspace blocklist, abuse reports, and GDPR / DPDP delete requests.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Bulk delete') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Add to blocklist') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total contacts') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">3.84M</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('across 142 workspaces') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Blocklist') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-coral">2,184</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('platform-wide') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Open abuse reports') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">28</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('awaiting review') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Delete requests') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">14</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in 30-day window') }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            <div class="lg:col-span-2 bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Abuse reports') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Awaiting review') }}</h2>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[11.5px] font-semibold">{{ __('Open') }}</button>
                        <button
                            class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Dismissed') }}</button>
                        <button
                            class="px-3 py-1.5 rounded-full text-[11.5px] font-semibold text-ink-700 hover:bg-paper-50">{{ __('Actioned') }}</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-[12.5px]">
                    <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-5 py-2.5 font-medium">{{ __('Reported number') }}</th>
                            <th class="text-left px-3 py-2.5 w-[140px] font-medium">{{ __('Reporter') }}</th>
                            <th class="text-left px-3 py-2.5 w-[140px] font-medium">{{ __('Reason') }}</th>
                            <th class="text-right px-3 py-2.5 w-[80px] font-medium">{{ __('Reports') }}</th>
                            <th class="text-right pl-3 pr-5 py-2.5 w-[120px] font-medium">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-mono">+91 90123 56789</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('first reported 4d ago') }}</div>
                            </td>
                            <td class="px-3 py-3">{{ __('FitKart') }}</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral text-[10px] font-semibold">{{ __('Spam') }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">9</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full bg-wa-deep text-paper-0 px-3 py-1 text-[11px]">{{ __('Block') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-mono">+1 415 555 0184</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('first reported 2d ago') }}</div>
                            </td>
                            <td class="px-3 py-3">{{ __('Lumina') }}</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-accent-amber/15 text-[#7B5A14] text-[10px] font-semibold">{{ __('Phishing') }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">4</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Review') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-mono">+44 7700 900456</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('first reported 1d ago') }}</div>
                            </td>
                            <td class="px-3 py-3">{{ __('PixelPlay') }}</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-accent-coral/10 text-accent-coral text-[10px] font-semibold">{{ __('Harassment') }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">3</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full bg-wa-deep text-paper-0 px-3 py-1 text-[11px]">{{ __('Block') }}</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-mono">+91 99887 12345</div>
                                <div class="text-[10.5px] text-ink-500">{{ __('first reported 6h ago') }}</div>
                            </td>
                            <td class="px-3 py-3">{{ __('QuickShop') }}</td>
                            <td class="px-3 py-3"><span
                                    class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-700 text-[10px] font-semibold">{{ __('Wrong number') }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">2</td>
                            <td class="pl-3 pr-5 py-3 text-right"><button
                                    class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Dismiss') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-paper-200">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('GDPR / DPDP') }}
                    </div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Delete requests') }}</h2>
                </div>
                <div class="p-5 space-y-3 text-[12.5px]">
                    <div class="rounded-2xl border border-paper-200 p-3">
                        <div class="font-mono text-[11px]">+91 98765 43210</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Bloomly · in 14-day window') }}</div><button
                            class="mt-2 w-full rounded-full bg-wa-deep text-paper-0 px-3 py-1.5 text-[11px] font-semibold">{{ __('Hard delete') }}</button>
                    </div>
                    <div class="rounded-2xl border border-paper-200 p-3">
                        <div class="font-mono text-[11px]">+1 415 555 0102</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('Lumina · in 21-day window') }}</div><button
                            class="mt-2 w-full rounded-full border border-paper-200 px-3 py-1.5 text-[11px] font-semibold">{{ __('Hard delete') }}</button>
                    </div>
                    <div class="rounded-2xl border border-paper-200 p-3">
                        <div class="font-mono text-[11px]">+44 7700 900111</div>
                        <div class="text-[11px] text-ink-500 mt-1">{{ __('PixelPlay · in 6-day window') }}</div>
                        <button
                            class="mt-2 w-full rounded-full border border-paper-200 px-3 py-1.5 text-[11px] font-semibold">{{ __('Hard delete') }}</button>
                    </div>
                </div>
            </div>

        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                    {{ __('Platform blocklist') }}</div>
                <h2 class="font-serif text-[22px] leading-tight mt-1">
                    {{ __('Numbers blocked across all workspaces') }}</h2>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-5 py-2.5 font-medium">{{ __('Number') }}</th>
                        <th class="text-left px-3 py-2.5 w-[150px] font-medium">{{ __('Reason') }}</th>
                        <th class="text-left px-3 py-2.5 w-[140px] font-medium">{{ __('Added by') }}</th>
                        <th class="text-right px-3 py-2.5 w-[120px] font-medium">{{ __('When') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[100px] font-medium">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr>
                        <td class="px-5 py-3 font-mono">+91 90123 56789</td>
                        <td class="px-3 py-3">{{ __('Spam (9 reports)') }}</td>
                        <td class="px-3 py-3">{{ __('Riya') }}</td>
                        <td class="px-3 py-3 text-right font-mono text-[11px]">{{ __('2h ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Unblock') }}</button>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">+1 415 555 0102</td>
                        <td class="px-3 py-3">{{ __('DPDP delete') }}</td>
                        <td class="px-3 py-3">{{ __('System') }}</td>
                        <td class="px-3 py-3 text-right font-mono text-[11px]">{{ __('1d ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Detail') }}</button>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">+44 7700 900456</td>
                        <td class="px-3 py-3">{{ __('Harassment') }}</td>
                        <td class="px-3 py-3">{{ __('Aman') }}</td>
                        <td class="px-3 py-3 text-right font-mono text-[11px]">{{ __('3d ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Unblock') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

    </main>

</x-layouts.admin>
