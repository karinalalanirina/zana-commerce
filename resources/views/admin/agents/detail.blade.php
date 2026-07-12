<x-layouts.admin :title="__('Agent · Riya Arora')" admin-key="agents">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/agents') }}" class="hover:text-ink-900">{{ __('Agents') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Riya Arora') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div
                    class="w-16 h-16 rounded-full bg-wa-deep grid place-items-center text-paper-0 font-serif text-[24px]">
                    RA</div>
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin - Agent profile') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[26px] sm:text-[30px] lg:text-[34px] leading-[1.0]">
                        {{ __('Riya Arora') }}<span class="text-wa-deep">.</span></h1>
                    <div class="flex items-center flex-wrap gap-2 mt-2 text-[12px]">
                        <span
                            class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10.5px] font-mono border border-wa-green/40">{{ __('online') }}</span>
                        <span
                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px] font-semibold">{{ __('Messaging') }}</span>
                        <span class="text-ink-500">riya@wadesk.in</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/agents') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All agents') }}</a>
                <button
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Send DM') }}</button>
                <button
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Open tickets') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">9</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('75% capacity') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Solved 30d') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">128</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('+12 vs prev') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('SLA hit rate') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">98%</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('target 95%') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('CSAT') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">4.8</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('42 ratings') }}</div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            <div class="lg:col-span-2 space-y-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Configuration') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Routing & capacity') }}</h2>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="space-y-1.5"><span
                                class="text-[11.5px] font-semibold">{{ __('Team') }}</span><select
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px]">
                                <option>{{ __('Messaging') }}</option>
                                <option>{{ __('Billing') }}</option>
                                <option>{{ __('Templates') }}</option>
                                <option>{{ __('Integrations') }}</option>
                                <option>{{ __('Meta Ads') }}</option>
                                <option>{{ __('Engineering') }}</option>
                                <option>{{ __('Access') }}</option>
                            </select></label>
                        <label class="space-y-1.5"><span
                                class="text-[11.5px] font-semibold">{{ __('Skill tags') }}</span><input
                                value="campaigns, queues, devices"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px]"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11.5px] font-semibold">{{ __('Concurrent ticket cap') }}</span><input
                                type="number" value="12"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px]"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11.5px] font-semibold">{{ __('Daily ticket limit') }}</span><input
                                type="number" value="40"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px]"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11.5px] font-semibold">{{ __('Working hours start') }}</span><input
                                type="time" value="09:00"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono"></label>
                        <label class="space-y-1.5"><span
                                class="text-[11.5px] font-semibold">{{ __('Working hours end') }}</span><input
                                type="time" value="18:00"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono"></label>
                        <label class="space-y-1.5 col-span-2"><span
                                class="text-[11.5px] font-semibold">{{ __('Timezone') }}</span><input
                                value="Asia/Kolkata"
                                class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono"></label>
                        <div
                            class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between col-span-2">
                            <div>
                                <div class="font-semibold text-[13px]">{{ __('Auto-assign new tickets') }}</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('Receive tickets from the routing engine when below capacity.') }}</div>
                            </div><label class="toggle"><input type="checkbox" checked /><span
                                    class="track"></span><span class="thumb"></span></label>
                        </div>
                        <div
                            class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between col-span-2">
                            <div>
                                <div class="font-semibold text-[13px]">{{ __('Allow weekend escalations') }}</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('Pull this agent in for breach-risk tickets outside hours.') }}</div>
                            </div><label class="toggle"><input type="checkbox" /><span class="track"></span><span
                                    class="thumb"></span></label>
                        </div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Currently handling') }}</div>
                        <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __('Active tickets') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                            <tr>
                                <th class="text-left px-4 py-2.5 w-[90px] font-medium">{{ __('Ticket') }}</th>
                                <th class="text-left px-3 py-2.5 font-medium">{{ __('Subject') }}</th>
                                <th class="text-left px-3 py-2.5 w-[140px] font-medium">{{ __('Workspace') }}</th>
                                <th class="text-center px-3 py-2.5 w-[90px] font-medium">{{ __('SLA') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-200">
                            <tr>
                                <td class="px-4 py-2.5 font-mono">{{ __('SUP-4821') }}</td>
                                <td class="px-3 py-2.5">{{ __('Campaign queue stalled') }}</td>
                                <td class="px-3 py-2.5">{{ __('Bloomly') }}</td>
                                <td class="px-3 py-2.5 text-center text-accent-coral font-mono">42m</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5 font-mono">{{ __('SUP-4805') }}</td>
                                <td class="px-3 py-2.5">{{ __('Auto-reply not triggering') }}</td>
                                <td class="px-3 py-2.5">{{ __('PixelPlay') }}</td>
                                <td class="px-3 py-2.5 text-center font-mono">14h</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5 font-mono">{{ __('SUP-4799') }}</td>
                                <td class="px-3 py-2.5">{{ __('Device QR re-link loop') }}</td>
                                <td class="px-3 py-2.5">{{ __('Wandermark') }}</td>
                                <td class="px-3 py-2.5 text-center font-mono">22h</td>
                            </tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                        {{ __('Quality (30 days)') }}</div>
                    <h2 class="font-serif text-[22px] leading-tight mt-1 mb-4">{{ __('Performance') }}</h2>
                    <div class="space-y-2.5 text-[12.5px]">
                        <div class="flex justify-between"><span>{{ __('First reply SLA') }}</span><span
                                class="font-mono text-wa-deep">98%</span></div>
                        <div class="flex justify-between"><span>{{ __('Resolution SLA') }}</span><span
                                class="font-mono text-wa-deep">95%</span></div>
                        <div class="flex justify-between"><span>{{ __('Reopen rate') }}</span><span
                                class="font-mono">3.2%</span></div>
                        <div class="flex justify-between"><span>{{ __('Median first reply') }}</span><span
                                class="font-mono">22m</span></div>
                        <div class="flex justify-between"><span>{{ __('CSAT') }}</span><span
                                class="font-mono text-wa-deep">4.8 / 5</span></div>
                    </div>
                </div>

                <div class="bg-paper-0 border border-accent-coral/30 rounded-2xl p-5 shadow-card">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-accent-coral">
                        {{ __('Danger zone') }}</div>
                    <h3 class="font-serif text-[18px] leading-tight mt-1 mb-3">{{ __('Account actions') }}</h3>
                    <div class="space-y-2 text-[12px]">
                        <button
                            class="w-full text-left rounded-xl border border-paper-200 hover:bg-paper-50 px-3 py-2">{{ __('Force log-out everywhere') }}</button>
                        <button
                            class="w-full text-left rounded-xl border border-paper-200 hover:bg-paper-50 px-3 py-2">{{ __('Reset password') }}</button>
                        <button
                            class="w-full text-left rounded-xl border border-accent-coral/30 hover:bg-accent-coral/5 text-accent-coral px-3 py-2">{{ __('Suspend agent') }}</button>
                        <button
                            class="w-full text-left rounded-xl border border-accent-coral/30 hover:bg-accent-coral/5 text-accent-coral px-3 py-2">{{ __('Remove from support') }}</button>
                    </div>
                </div>
            </div>

        </section>

    </main>

</x-layouts.admin>
