<x-layouts.admin :title="__('Admin · Edit campaign')" admin-key="metaads">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0 min-w-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/meta-ads') }}" class="hover:text-ink-900">{{ __('Meta Ads') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span
                class="text-ink-900 normal-case tracking-normal truncate max-w-[280px]">{{ __('Meta CTWA — Summer sale') }}</span>
        </div>
        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-wa-green/10 text-wa-deep border border-wa-green/30"><span
                    class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Active</span>
            <a href="{{ url('/admin/meta-ads/analytics') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M2 12h12M4 10l2.2-3 3 2 3.2-5" />
                </svg>
                Analytics
            </a>
            <button
                class="px-3.5 py-1.5 hairline border border-accent-coral/40 text-accent-coral rounded-full bg-paper-0 hover:bg-accent-coral/10 text-[12px] font-medium">{{ __('Force pause') }}</button>
            <button type="submit" form="campaignForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Save changes
            </button>
        </div>
    </header>

    <!-- Page heading -->
    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Editing campaign #CAM_4421') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[36px] leading-[1.0]">{{ __('Meta CTWA —') }}
                    <span class="italic text-wa-deep">{{ __('Summer sale') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">{{ __('Workspace:') }} <b>Bloomly</b> · Owner: <b>Vetrick
                        R.</b> · FB ID <span class="font-mono">1203948…441</span> · Last synced just now.</p>
            </div>
        </div>
    </div>

    <!-- Form -->
    <main class="px-4 sm:px-6 lg:px-7 pb-7">
        <form id="campaignForm" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">

            <!-- LEFT: form sections -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card">

                <!-- Section 00: Admin context (read-only here) -->
                <div class="px-[18px] py-4 hairline-b border-b border-paper-200 bg-wa-bubble/30">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-wa-deep text-paper-0 inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">00</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Admin context') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('read-only') }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-[12.5px]">
                        <div>
                            <div class="text-[10.5px] text-ink-500 font-mono uppercase tracking-wider mb-0.5">
                                {{ __('Workspace') }}</div>
                            <div class="font-semibold flex items-center gap-2">{{ __('Bloomly') }} <span
                                    class="px-1.5 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Pro') }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-[10.5px] text-ink-500 font-mono uppercase tracking-wider mb-0.5">
                                {{ __('Ad account') }}</div>
                            <div class="font-semibold font-mono text-[11px]">{{ __('act_18234907112') }}</div>
                        </div>
                        <div>
                            <div class="text-[10.5px] text-ink-500 font-mono uppercase tracking-wider mb-0.5">
                                {{ __('Submitted by') }}</div>
                            <div class="font-semibold">{{ __('Vetrick R.') }}</div>
                        </div>
                        <div>
                            <div class="text-[10.5px] text-ink-500 font-mono uppercase tracking-wider mb-0.5">
                                {{ __('Created') }}</div>
                            <div class="font-semibold font-mono">2026-04-18</div>
                        </div>
                    </div>
                </div>

                <!-- Section 01: Campaign details -->
                <div class="px-[18px] py-4 hairline-b border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Campaign details') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="campaign-name">{{ __('Campaign name') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="campaign-name" name="name" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="Meta CTWA — Summer sale" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="objective">{{ __('Objective') }} <span class="text-accent-coral">*</span></label>
                            <select id="objective" name="optimization_goal"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option value="MESSAGES" selected>{{ __('Messages — WhatsApp inbox') }}</option>
                                <option value="LINK_CLICKS">{{ __('Link Clicks — Website traffic') }}</option>
                                <option value="CONVERSIONS">{{ __('Conversions — Sales or signup') }}</option>
                                <option value="LEAD_GENERATION">{{ __('Lead Generation — Meta lead form') }}</option>
                                <option value="REACH">{{ __('Reach — Maximum people') }}</option>
                                <option value="BRAND_AWARENESS">{{ __('Brand Awareness') }}</option>
                                <option value="VIDEO_VIEWS">{{ __('Video Views') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="adset-name">{{ __('Ad set name') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="adset-name" name="adset_name" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="IN Adults 22-45" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="budget">{{ __('Daily budget') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="budget" name="daily_budget" type="number" min="1" step="0.01"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="25.00" required>
                            <div class="text-[10.5px] text-ink-500 mt-1">Workspace plan cap: $500/day.</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="status-mode">{{ __('Current status') }}</label>
                            <select id="status-mode" name="status"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="ACTIVE" selected>{{ __('Active') }}</option>
                                <option value="PAUSED">{{ __('Paused') }}</option>
                                <option value="DRAFT">{{ __('Draft') }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label
                                class="hairline border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 w-full">
                                <span>
                                    <span
                                        class="block text-[12.5px] font-semibold">{{ __('Click to WhatsApp') }}</span>
                                    <span
                                        class="block text-[10.5px] text-ink-500">{{ __('Send ad clicks to WhatsApp') }}</span>
                                </span>
                                <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                        class="peer opacity-0 w-0 h-0" type="checkbox" id="ctwa-enabled"
                                        checked><span
                                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-phone">{{ __('WhatsApp number') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="ctwa-phone" name="ctwa_phone" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="919876543210">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-message">{{ __('Prefilled message') }}</label>
                            <input id="ctwa-message" name="ctwa_message" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="Hi, I am interested in the summer sale.">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-cta">{{ __('CTA button') }}</label>
                            <select id="ctwa-cta" name="ctwa_cta"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="WHATSAPP_MESSAGE" selected>{{ __('WhatsApp Message') }}</option>
                                <option value="LEARN_MORE">{{ __('Learn More') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 02: Audience -->
                <div class="px-[18px] py-4 hairline-b border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Audience targeting') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('Meta ad set') }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="countries">{{ __('Target countries') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="countries" name="target_countries" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="IN, AE" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="age-min">{{ __('Min age') }} <span class="text-accent-coral">*</span></label>
                            <input id="age-min" name="age_min" type="number" min="18" max="65"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="22" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="age-max">{{ __('Max age') }} <span class="text-accent-coral">*</span></label>
                            <input id="age-max" name="age_max" type="number" min="18" max="65"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="45" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="gender">{{ __('Gender') }}</label>
                            <select id="gender" name="gender"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="" selected>{{ __('All genders') }}</option>
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="interests">{{ __('Interests') }} <span
                                class="font-mono text-[10px] text-ink-500">3/50</span></label>
                        <textarea id="interests" name="interests"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            rows="2">Fashion, Online shopping, Sale</textarea>
                    </div>
                </div>

                <!-- Section 03: Ad creative -->
                <div class="px-[18px] py-4 hairline-b border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Ad creative') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('headline, text, media') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="headline">{{ __('Ad headline') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="headline" name="creative_title" type="text" maxlength="100"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="Summer Sale is live — up to 40% off" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="destination">{{ __('Destination URL') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="destination" name="creative_link_url" type="url"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="https://bloomly.in/summer-sale" required>
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="body">{{ __('Ad text') }} <span class="text-accent-coral">*</span></label>
                            <textarea id="body" name="creative_body"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                rows="4" required>Get up to 40% off today. Message us on WhatsApp to claim your offer.</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Ad image') }}
                                <span
                                    class="font-mono text-[10px] text-ink-500">{{ __('summer-sale-2026.jpg · 482 KB') }}</span></label>
                            <div
                                class="flex items-center gap-2.5 px-[11px] py-2.5 border border-wa-deep rounded-lg bg-wa-bubble cursor-pointer">
                                <span
                                    class="w-[34px] h-[34px] rounded-lg bg-wa-deep text-paper-0 inline-flex items-center justify-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                        <circle cx="6" cy="7" r="1.5" />
                                        <path d="M2 11l4-3 4 3 4-2" />
                                    </svg></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12px] font-semibold text-ink-900">
                                        {{ __('summer-sale-2026.jpg') }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">1200×628 · 482 KB</div>
                                </div>
                                <button type="button"
                                    class="text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0">{{ __('Replace') }}</button>
                                <button type="button"
                                    class="text-[10.5px] font-semibold text-accent-coral px-[9px] py-1 rounded-full bg-white border border-accent-coral cursor-pointer shrink-0">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 04: Admin actions -->
                <div class="px-[18px] py-4">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-accent-coral/10 text-accent-coral inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Admin actions') }}</span>
                        <span class="font-mono text-[10px] text-accent-coral">{{ __('danger zone') }}</span>
                    </div>
                    <div class="space-y-2">
                        <div
                            class="flex items-center justify-between gap-3 px-3 py-2.5 hairline border border-paper-200 rounded-lg">
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Refund last 7 days of spend') }}</div>
                                <div class="text-[10.5px] text-ink-500">Issue a $412.50 credit back to Bloomly's
                                    wallet.</div>
                            </div>
                            <button type="button"
                                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Issue refund') }}</button>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 px-3 py-2.5 hairline border border-paper-200 rounded-lg">
                            <div>
                                <div class="text-[12.5px] font-semibold">{{ __('Transfer ownership') }}</div>
                                <div class="text-[10.5px] text-ink-500">
                                    {{ __('Move this campaign to a different workspace.') }}</div>
                            </div>
                            <button type="button"
                                class="px-3 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Transfer…') }}</button>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 px-3 py-2.5 border border-accent-coral/30 rounded-lg bg-accent-coral/5">
                            <div>
                                <div class="text-[12.5px] font-semibold text-accent-coral">{{ __('Delete campaign') }}
                                </div>
                                <div class="text-[10.5px] text-ink-700">
                                    {{ __('Permanent. Removes from Meta and :app. Owner will be notified.', ['app' => brand_name()]) }}
                                </div>
                            </div>
                            <button type="button"
                                class="px-3 py-1.5 rounded-full bg-accent-coral text-paper-0 hover:bg-accent-coral/80 text-[12px] font-semibold">{{ __('Delete forever') }}</button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="admin-note">{{ __('Admin note (internal)') }}</label>
                        <textarea id="admin-note" name="admin_note" rows="2"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            placeholder="{{ __('Reason for editing on behalf of workspace') }}"></textarea>
                    </div>
                </div>
            </div>

            <!-- RIGHT: snapshot -->
            <aside class="sticky top-[78px] self-start space-y-3">
                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 px-1">
                        {{ __('Performance snapshot') }}</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2 rounded-lg bg-paper-50">
                            <div class="text-[10px] font-mono uppercase text-ink-500">{{ __('Spend') }}</div>
                            <div class="text-[16px] font-semibold tabular-nums">$412.50</div>
                        </div>
                        <div class="p-2 rounded-lg bg-paper-50">
                            <div class="text-[10px] font-mono uppercase text-ink-500">{{ __('Revenue') }}</div>
                            <div class="text-[16px] font-semibold tabular-nums text-wa-deep">$3,210</div>
                        </div>
                        <div class="p-2 rounded-lg bg-paper-50">
                            <div class="text-[10px] font-mono uppercase text-ink-500">{{ __('Clicks') }}</div>
                            <div class="text-[16px] font-semibold tabular-nums">2,210</div>
                        </div>
                        <div class="p-2 rounded-lg bg-paper-50">
                            <div class="text-[10px] font-mono uppercase text-ink-500">{{ __('CTR') }}</div>
                            <div class="text-[16px] font-semibold tabular-nums">2.62%</div>
                        </div>
                    </div>
                    <div class="mt-3"><a href="{{ url('/admin/meta-ads/analytics') }}"
                            class="text-[12px] font-semibold text-wa-deep hover:underline inline-flex items-center gap-1">{{ __('Open full analytics →') }}</a>
                    </div>
                </div>

                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 px-1">
                        {{ __('Audit trail') }}</div>
                    <ol class="space-y-2.5 text-[11.5px]">
                        <li class="flex gap-2"><span
                                class="w-1.5 h-1.5 rounded-full bg-wa-green mt-1.5 shrink-0"></span><span><b>Vetrick
                                    R.</b> created campaign · <span class="text-ink-500">2026-04-18 09:14</span></span>
                        </li>
                        <li class="flex gap-2"><span
                                class="w-1.5 h-1.5 rounded-full bg-paper-300 mt-1.5 shrink-0"></span><span>{{ __('Auto-approved by policy bot ·') }}
                                <span class="text-ink-500">2026-04-18 09:15</span></span></li>
                        <li class="flex gap-2"><span
                                class="w-1.5 h-1.5 rounded-full bg-accent-amber mt-1.5 shrink-0"></span><span><b>Meera
                                    Shah</b> increased budget $20 → $25 · <span class="text-ink-500">2026-04-22
                                    14:02</span></span></li>
                        <li class="flex gap-2"><span
                                class="w-1.5 h-1.5 rounded-full bg-[#13478A] mt-1.5 shrink-0"></span><span>{{ __('Insights synced ·') }}
                                <span class="text-ink-500">{{ __('just now') }}</span></span></li>
                    </ol>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-paper-200 rounded-[14px] shadow-card p-3 text-[11px] text-ink-700 leading-snug">
                    <b>Heads-up:</b> changes to live campaigns push to Meta within ~2 minutes. The workspace owner will
                    see your edit in their audit log.
                </div>
            </aside>
        </form>
    </main>

</x-layouts.admin>
