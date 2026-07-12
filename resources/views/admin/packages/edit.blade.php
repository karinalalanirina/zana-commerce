<x-layouts.admin :title="__('Admin · Edit package · Pro')" admin-key="packages" page="packages-edit">



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
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Pro · v5') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2 flex-wrap justify-end">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-mint text-wa-deep border border-wa-green/40 font-mono"><span
                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Live · 84 subs</span>
            <a href="{{ url('/admin/packages/analytics/overview') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                </svg>
                Analytics
            </a>
            <a href="{{ url('/admin/packages') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="packageForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Save changes
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Editing package #plan_pro_v5') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]"><span
                    class="italic text-wa-deep">{{ __('Pro') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">Live plan · 84 subscribers · $75.5k MRR · last edited
                2026-04-12 by Sahil K. Edits don't affect existing subscribers — clone if you need a different tier.</p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        <form id="packageForm" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5 items-start">

            <!-- Form card -->
            <div class="bg-white border border-paper-200 rounded-2xl shadow-card overflow-hidden">

                <!-- Stepper indicator -->
                <div class="px-5 py-4 border-b border-paper-200 bg-paper-50/40 overflow-x-auto">
                    <div class="flex items-center min-w-[640px]" id="stepper">
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="1">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-wa-deep text-wa-deep ring-4 ring-wa-deep/10">1</span>
                            <span
                                class="lab text-[11.5px] font-semibold whitespace-nowrap text-wa-deep">{{ __('Basics') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="2">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">2</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Limits') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="3">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">3</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Features') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="4">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">4</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Branding') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 flex-1 cursor-pointer" data-n="5">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">5</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Add-ons') }}</span>
                            <span class="bar flex-1 h-[2px] mx-2 rounded bg-paper-200"></span>
                        </div>
                        <div class="step-node flex items-center gap-2.5 cursor-pointer" data-n="6">
                            <span
                                class="dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px] bg-paper-0 border-paper-200 text-ink-500">6</span>
                            <span
                                class="lab text-[11.5px] font-medium whitespace-nowrap text-ink-500">{{ __('Review') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Step body -->
                <div class="p-5">

                    <!-- Step 1: Basics -->
                    <div class="step-pane" data-step="1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Plan basics') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Plan unique ID') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input id="plan-id" name="plan_id" type="text"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 font-mono"
                                    value="plan_pro_v5" required>
                                <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Slug used in code & webhooks.') }}
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Package name') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input id="pname" name="pname" type="text"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="Pro" required>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Badge / tagline') }}</label>
                                <input id="badge" name="badge" type="text"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="Most popular">
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Price') }}
                                    <span class="text-accent-coral">*</span></label>
                                <div
                                    class="flex items-stretch border border-paper-200 rounded-lg bg-white focus-within:border-wa-deep focus-within:ring-4 focus-within:ring-wa-deep/10">
                                    <select
                                        class="px-2 py-[7px] text-[12.5px] border-r border-paper-200 bg-paper-50 rounded-l-lg focus:outline-none">
                                        <option>{{ __('USD') }}</option>
                                        <option>{{ __('EUR') }}</option>
                                        <option>{{ __('INR') }}</option>
                                        <option>{{ __('GBP') }}</option>
                                    </select>
                                    <input id="amount" name="plan_amount" type="number" step="0.01"
                                        min="0"
                                        class="flex-1 px-[11px] py-[7px] text-[12.5px] focus:outline-none"
                                        value="899.00" required>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Duration') }}
                                    <span class="text-accent-coral">*</span></label>
                                <input id="duration" name="plan_duration" type="number" min="1"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    value="1" required>
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Interval') }}
                                    <span class="text-accent-coral">*</span></label>
                                <select id="unit" name="plan_unit"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    required>
                                    <option>{{ __('Day') }}</option>
                                    <option>{{ __('Week') }}</option>
                                    <option selected>{{ __('Month') }}</option>
                                    <option>{{ __('Year') }}</option>
                                    <option>{{ __('Lifetime') }}</option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Description') }}</label>
                                <textarea id="desc" name="description" rows="2"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">For growing teams ready to scale outbound &amp; inbox automation. 8M messages, full automation suite, priority support.</textarea>
                            </div>
                            <div class="md:col-span-3 grid grid-cols-2 md:grid-cols-4 gap-2">
                                <label
                                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-2 cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                        class="text-[12px] font-medium">{{ __('Free plan') }}</span><input
                                        type="checkbox" name="free"
                                        class="rounded border-paper-300 text-wa-deep"></label>
                                <label
                                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-2 cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                        class="text-[12px] font-medium">{{ __('Default trial') }}</span><input
                                        type="checkbox" name="trial"
                                        class="rounded border-paper-300 text-wa-deep"></label>
                                <label
                                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-2 cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                        class="text-[12px] font-medium">{{ __('Featured') }}</span><input
                                        type="checkbox" name="featured"
                                        class="rounded border-paper-300 text-wa-deep"></label>
                                <label
                                    class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-2 cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                        class="text-[12px] font-medium">{{ __('Active on signup') }}</span><input
                                        type="checkbox" name="active" class="rounded border-paper-300 text-wa-deep"
                                        checked></label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Limits -->
                    <div class="step-pane hidden" data-step="2">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Usage limits') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('caps & throttles') }}</span>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Messages') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Monthly messages') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="8000000"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Daily messages') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="500000"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Per-min throttle') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="80"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Free WhatsApp messages') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="100000"></div>
                            <div><label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">Overage rate
                                    ($/msg)</label><input type="number" step="0.0001"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="0.0008"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Free CTWA clicks') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="50000"></div>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Contacts & team') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Max contacts') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="500000"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Contact groups') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="200"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Custom fields') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="50"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Max users') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="50"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Max devices') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="10"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Custom roles') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="20"></div>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Media & storage') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Storage (GB)') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="100"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('File size (MB)') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="100"></div>
                            <div><label
                                    class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Bandwidth / mo (GB)') }}</label><input
                                    type="number"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep"
                                    value="500"></div>
                        </div>
                    </div>

                    <!-- Step 3: Features -->
                    <div class="step-pane hidden" data-step="3">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Features & integrations') }}</span>
                            <span class="font-mono text-[10px] text-ink-500"><span id="feat-count">9</span>
                                enabled</span>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Automation') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-5">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Flow builder') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Visual automation canvas') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Auto-reply rules') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Keyword-triggered replies') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('REST API access') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Programmatic send + webhooks') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('AI auto-reply') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('OpenAI / Gemini / Claude') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"></label>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Integrations') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-5">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Shopify') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Order events + abandoned cart') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('WooCommerce') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Same as Shopify for Woo') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Google Sheets') }}</span><span
                                        class="block text-[10.5px] text-ink-500">2-way contact sync</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Click-to-WhatsApp ads') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Meta Ads CTWA') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Compliance & support') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('SSO / SAML') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Enterprise identity') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Audit log export') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('SOC2 / ISO ready') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Priority support') }}</span><span
                                        class="block text-[10.5px] text-ink-500">2-hour SLA</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"
                                    checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Dedicated CSM') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Customer success manager') }}</span></span><input
                                    type="checkbox" class="feat rounded border-paper-300 text-wa-deep"></label>
                        </div>
                    </div>

                    <!-- Step 4: Branding -->
                    <div class="step-pane hidden" data-step="4">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Custom branding') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('white-label') }}</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-5">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('White-label dashboard') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Hide :app branding', ['app' => brand_name()]) }}</span></span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Custom domain') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('CNAME workspace.com') }}</span></span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep" checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Custom email sender') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('Magic-link & receipts') }}</span></span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Custom invoice logo') }}</span><span
                                        class="block text-[10.5px] text-ink-500">{{ __('PDF invoices') }}</span></span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep" checked></label>
                        </div>

                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Templates & categories') }}</div>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                    class="text-[12px] font-medium">{{ __('Marketing templates') }}</span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep" checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                    class="text-[12px] font-medium">{{ __('Utility templates') }}</span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep" checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                    class="text-[12px] font-medium">{{ __('Authentication') }}</span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                    class="text-[12px] font-medium">{{ __('Carousel') }}</span><input type="checkbox"
                                    class="rounded border-paper-300 text-wa-deep" checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                    class="text-[12px] font-medium">{{ __('Multi-product') }}</span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span
                                    class="text-[12px] font-medium">{{ __('Custom approval queue') }}</span><input
                                    type="checkbox" class="rounded border-paper-300 text-wa-deep"></label>
                        </div>
                    </div>

                    <!-- Step 5: Add-ons -->
                    <div class="step-pane hidden" data-step="5">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">05</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Available add-ons') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">à la carte</span>
                        </div>
                        <p class="text-[11.5px] text-ink-600 mb-3">
                            {{ __('Tick the add-ons workspaces on this plan can purchase.') }}</p>
                        <div class="space-y-2">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Extra messages bundle') }}</span><span
                                        class="block text-[10.5px] text-ink-500">+1M messages — $49
                                        one-time</span></span><input type="checkbox"
                                    class="rounded border-paper-300 text-wa-deep" checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Extra device slot') }}</span><span
                                        class="block text-[10.5px] text-ink-500">+1 device —
                                        $29/month</span></span><input type="checkbox"
                                    class="rounded border-paper-300 text-wa-deep" checked></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Extra agent seat') }}</span><span
                                        class="block text-[10.5px] text-ink-500">+1 inbox agent —
                                        $19/month</span></span><input type="checkbox"
                                    class="rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('AI add-on') }}</span><span
                                        class="block text-[10.5px] text-ink-500">10k AI replies —
                                        $99/month</span></span><input type="checkbox"
                                    class="rounded border-paper-300 text-wa-deep"></label>
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between cursor-pointer hover:bg-paper-50 has-[:checked]:border-wa-deep has-[:checked]:bg-wa-bubble"><span><span
                                        class="block text-[12.5px] font-semibold">{{ __('Premium support') }}</span><span
                                        class="block text-[10.5px] text-ink-500">24/7 phone support —
                                        $199/month</span></span><input type="checkbox"
                                    class="rounded border-paper-300 text-wa-deep"></label>
                        </div>
                    </div>

                    <!-- Step 6: Review -->
                    <div class="step-pane hidden" data-step="6">
                        <div class="flex items-center gap-2.5 mb-4">
                            <span
                                class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">06</span>
                            <span
                                class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Review & publish') }}</span>
                            <span class="font-mono text-[10px] text-ink-500">{{ __('last check') }}</span>
                        </div>
                        <div class="space-y-3">
                            <div class="rounded-xl border border-paper-200 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                        {{ __('Basics') }}</div><button type="button"
                                        class="text-[11.5px] text-wa-deep hover:underline"
                                        onclick="show(1)">{{ __('Edit') }}</button>
                                </div>
                                <div class="mt-2 text-[12.5px] grid grid-cols-2 md:grid-cols-4 gap-2">
                                    <div>
                                        <div class="text-ink-500 text-[10.5px]">{{ __('Plan ID') }}</div>
                                        <div class="font-mono">{{ __('plan_pro_v6') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-ink-500 text-[10.5px]">{{ __('Name') }}</div>
                                        <div class="font-semibold">{{ __('Pro') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-ink-500 text-[10.5px]">{{ __('Price') }}</div>
                                        <div class="font-mono">{!! isset($package)
                                            ? \App\Support\FormatSettings::formatIn($package->plan_amount ?? 0, $package->currency ?? 'USD') .
                                                ' / ' .
                                                ($package->plan_unit ?? 'month')
                                            : '—' !!}</div>
                                        <div>
                                            <div class="text-ink-500 text-[10.5px]">{{ __('Status') }}</div>
                                            <div>{{ __('Active on signup') }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-paper-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                            {{ __('Limits') }}</div><button type="button"
                                            class="text-[11.5px] text-wa-deep hover:underline"
                                            onclick="show(2)">{{ __('Edit') }}</button>
                                    </div>
                                    <div class="mt-2 text-[12.5px] grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <div>
                                            <div class="text-ink-500 text-[10.5px]">{{ __('Messages / mo') }}</div>
                                            <div class="font-mono">8,000,000</div>
                                        </div>
                                        <div>
                                            <div class="text-ink-500 text-[10.5px]">{{ __('Devices') }}</div>
                                            <div class="font-mono">10</div>
                                        </div>
                                        <div>
                                            <div class="text-ink-500 text-[10.5px]">{{ __('Users') }}</div>
                                            <div class="font-mono">50</div>
                                        </div>
                                        <div>
                                            <div class="text-ink-500 text-[10.5px]">{{ __('Storage') }}</div>
                                            <div class="font-mono">100 GB</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-paper-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                            {{ __('Features') }}</div><button type="button"
                                            class="text-[11.5px] text-wa-deep hover:underline"
                                            onclick="show(3)">{{ __('Edit') }}</button>
                                    </div>
                                    <div class="mt-2 text-[12.5px] flex flex-wrap gap-1.5">
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('Flow builder') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('REST API') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('Shopify') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('WooCommerce') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('Google Sheets') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('CTWA ads') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('Auto-reply') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10.5px]">{{ __('Priority support') }}</span>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-paper-200 p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                            {{ __('Branding') }}</div><button type="button"
                                            class="text-[11.5px] text-wa-deep hover:underline"
                                            onclick="show(4)">{{ __('Edit') }}</button>
                                    </div>
                                    <div class="mt-2 text-[12.5px] flex flex-wrap gap-1.5">
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px]">{{ __('Custom domain') }}</span>
                                        <span
                                            class="px-2 py-0.5 rounded-full bg-paper-50 text-ink-700 text-[10.5px]">{{ __('Invoice logo') }}</span>
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-wa-green/30 bg-wa-bubble/40 p-3 text-[11.5px] text-ink-700 leading-snug">
                                    <b class="text-ink-900">Heads up:</b> publishing makes this plan visible on the
                                    public pricing page. Existing subscribers are not affected by changes to a published
                                    plan — clone instead if you need a new tier.
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Step nav -->
                    <div class="px-5 py-4 flex items-center justify-between bg-paper-50/40 border-t border-paper-200">
                        <button type="button" id="btn-prev"
                            class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium text-ink-700 inline-flex items-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M10 4l-4 4 4 4" />
                            </svg>
                            Previous
                        </button>
                        <div class="font-mono text-[11px] text-ink-500">{{ __('Step') }} <span
                                id="cur-step">1</span> of 6</div>
                        <button type="button" id="btn-next"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">
                            Next
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M6 4l4 4-4 4" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Right rail: live preview -->
                <aside class="sticky top-[78px] self-start space-y-3">
                    <div class="bg-paper-0 border-2 border-wa-deep rounded-2xl shadow-card p-5 relative">
                        <span
                            class="absolute -top-3 left-5 px-2.5 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-semibold uppercase tracking-wider">{{ __('Live preview') }}</span>
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                            {{ __('Plan card') }}</div>
                        <h3 class="font-serif text-[26px] leading-none mt-1">{{ __('Pro') }}</h3>
                        <div class="mt-3 flex items-baseline gap-1"><span
                                class="font-serif text-[32px]">$899</span><span class="text-[12px] text-ink-500">/
                                month</span></div>
                        <ul class="mt-4 space-y-1.5 text-[12px] text-ink-700">
                            <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>8M messages</li>
                            <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>10 devices · 50 users</li>
                            <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>{{ __('Flow builder · API · Shopify') }}</li>
                            <li class="flex items-center gap-2"><svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 8l3 3 7-7" />
                                </svg>{{ __('Priority support · Custom domain') }}</li>
                        </ul>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card p-4">
                        <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Projected economics') }}</div>
                        <div class="space-y-1.5 text-[12px]">
                            <div class="flex items-center justify-between"><span
                                    class="text-ink-500">{{ __('Cost per workspace') }}</span><b
                                    class="font-mono">~$112</b></div>
                            <div class="flex items-center justify-between"><span
                                    class="text-ink-500">{{ __('Margin / mo') }}</span><b
                                    class="font-mono text-wa-deep">$787</b></div>
                            <div class="flex items-center justify-between"><span
                                    class="text-ink-500">{{ __('Break-even') }}</span><b class="font-mono">12
                                    subs</b></div>
                            <div class="flex items-center justify-between"><span
                                    class="text-ink-500">{{ __('Target by Q2') }}</span><b class="font-mono">90
                                    subs</b></div>
                        </div>
                    </div>

                    <div
                        class="bg-wa-bubble/40 border border-paper-200 rounded-2xl shadow-card p-3 text-[11px] text-ink-700 leading-snug">
                        <b>Tip:</b> name plans by audience ("Studio", "Agency"), not just tier ("Plan B"). Workspaces
                        upgrade more often when the name fits their identity.
                    </div>
                </aside>

        </form>

        <!-- Danger zone (full width) -->
        <div class="bg-white border border-accent-coral/30 rounded-[14px] shadow-card p-5 mt-5">
            <div class="flex items-center gap-2.5 mb-4">
                <span
                    class="w-[23px] h-[23px] rounded-[7px] bg-accent-coral/10 text-accent-coral inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">!</span>
                <span class="font-serif text-[18px] leading-none">{{ __('Danger zone') }}</span>
                <span class="font-mono text-[10px] text-accent-coral ml-auto">{{ __('irreversible') }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div class="px-3 py-2.5 rounded-lg border border-paper-200 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[12.5px] font-semibold">{{ __('Duplicate as new plan') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('Clone with all settings') }}</div>
                    </div>
                    <a href="{{ url('/admin/packages/create') }}"
                        class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">{{ __('Duplicate') }}</a>
                </div>
                <div class="px-3 py-2.5 rounded-lg border border-paper-200 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[12.5px] font-semibold">{{ __('Hide from pricing page') }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-0.5">{{ __('Existing 84 subscribers stay') }}</div>
                    </div>
                    <button
                        class="px-3 py-1.5 rounded-full border border-paper-200 text-[12px] font-semibold hover:bg-paper-50">{{ __('Hide') }}</button>
                </div>
                <div
                    class="px-3 py-2.5 rounded-lg border border-accent-coral/40 bg-accent-coral/5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[12.5px] font-semibold text-accent-coral">{{ __('Archive package') }}</div>
                        <div class="text-[10.5px] text-ink-700 mt-0.5">{{ __('Reassign 84 users first') }}</div>
                    </div>
                    <button
                        class="px-3 py-1.5 rounded-full bg-accent-coral text-paper-0 text-[12px] font-semibold hover:bg-accent-coral/80">{{ __('Archive') }}</button>
                </div>
            </div>
        </div>
    </main>

</x-layouts.admin>
