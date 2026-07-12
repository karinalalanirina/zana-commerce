<x-layouts.admin :title="__('Admin · Create campaign')" admin-key="metaads">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/meta-ads') }}" class="hover:text-ink-900">{{ __('Campaigns') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('New') }}</span>
        </div>
        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('Draft / unsaved') }}</span>
            <a href="{{ url('/admin/meta-ads') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="button"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            <button type="submit" form="campaignForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Publish & approve
            </button>
        </div>
    </header>

    <!-- Page heading -->
    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin · Meta Ads · New') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[36px] leading-[1.0]">
                    {{ __('Create Meta Ads') }} <span class="italic text-wa-deep">{{ __('campaign') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2">
                    {{ __('Spin up a campaign on behalf of any workspace. As admin, you can bypass approval and publish directly.') }}
                </p>
            </div>
        </div>
    </div>

    <!-- Form -->
    <main class="px-4 sm:px-6 lg:px-7 pb-7">
        <form id="campaignForm" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">

            <!-- LEFT: form sections -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card">

                <!-- Section 00: Admin context (NEW for admin) -->
                <div class="px-[18px] py-4 hairline-b border-b border-paper-200 bg-wa-bubble/30">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-wa-deep text-paper-0 inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">00</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Admin context') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('admin only') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="workspace">{{ __('Workspace') }} <span class="text-accent-coral">*</span></label>
                            <select id="workspace" name="workspace_id"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option value="">{{ __('Select workspace') }}</option>
                                <option value="bloomly" selected>{{ __('Bloomly · Pro · Vetrick R.') }}</option>
                                <option value="fitkart">{{ __('FitKart · Pro · Meera Shah') }}</option>
                                <option value="northstar">{{ __('Northstar Clinic · Enterprise · Anya Menon') }}
                                </option>
                                <option value="quickbite">{{ __('QuickBite · Starter · Ravi Tandon') }}</option>
                            </select>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __("Campaign will be created under this workspace's Meta Ad account.") }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ad-account">{{ __('Meta Ad Account') }}</label>
                            <input id="ad-account" name="ad_account_id" type="text" readonly
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-paper-50 text-[12.5px] text-ink-700 font-mono"
                                value="act_18234907112" />
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Auto-populated from workspace.') }}
                            </div>
                        </div>
                        <div class="flex items-end">
                            <label
                                class="hairline border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 w-full bg-paper-0">
                                <span>
                                    <span
                                        class="block text-[12.5px] font-semibold">{{ __('Bypass policy review') }}</span>
                                    <span
                                        class="block text-[10.5px] text-ink-500">{{ __('Skip the pending-review queue') }}</span>
                                </span>
                                <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                        class="peer opacity-0 w-0 h-0" type="checkbox" name="bypass_review"
                                        checked><span
                                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                            </label>
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
                                placeholder="{{ __('Meta CTWA — Summer sale') }}" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="objective">{{ __('Objective') }} <span class="text-accent-coral">*</span></label>
                            <select id="objective" name="optimization_goal"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option value="MESSAGES">{{ __('Messages — WhatsApp inbox') }}</option>
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
                                placeholder="{{ __('IN Adults 18-45') }}" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="budget">{{ __('Daily budget') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="budget" name="daily_budget" type="number" min="1" step="0.01"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="25.00" required>
                            <div class="text-[10.5px] text-ink-500 mt-1">Min $1/day. Workspace plan caps at $500/day.
                            </div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="status-mode">{{ __('Initial status') }}</label>
                            <select id="status-mode" name="status"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="ACTIVE">{{ __('Active after publish') }}</option>
                                <option value="PAUSED">{{ __('Publish paused') }}</option>
                                <option value="DRAFT">{{ __('Save as draft') }}</option>
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
                                placeholder="919876543210">
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Digits only, no plus sign. Must be linked to workspace WABA.') }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-message">{{ __('Prefilled message') }}</label>
                            <input id="ctwa-message" name="ctwa_message" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="Hi, I am interested in the offer.">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="ctwa-cta">{{ __('CTA button') }}</label>
                            <select id="ctwa-cta" name="ctwa_cta"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="WHATSAPP_MESSAGE">{{ __('WhatsApp Message') }}</option>
                                <option value="LEARN_MORE">{{ __('Learn More') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 02: Audience targeting -->
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
                                value="IN" required>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('ISO codes, comma-separated.') }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="age-min">{{ __('Min age') }} <span class="text-accent-coral">*</span></label>
                            <input id="age-min" name="age_min" type="number" min="18" max="65"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="18" required>
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
                                <option value="">{{ __('All genders') }}</option>
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                            for="interests">{{ __('Interests') }} <span
                                class="font-mono text-[10px] text-ink-500">0/50</span></label>
                        <textarea id="interests" name="interests"
                            class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                            rows="2" placeholder="{{ __('Travel, Sports, Cooking') }}"></textarea>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Comma-separated. Empty = broad targeting.') }}</div>
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
                                for="headline">{{ __('Ad headline') }} <span class="text-accent-coral">*</span> <span
                                    class="font-mono text-[10px] text-ink-500">0/100</span></label>
                            <input id="headline" name="creative_title" type="text" maxlength="100"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Summer Sale is live') }}" required>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="destination">{{ __('Destination URL') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="destination" name="creative_link_url" type="url"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="https://yourwebsite.com" required>
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="body">{{ __('Ad text') }} <span class="text-accent-coral">*</span></label>
                            <textarea id="body" name="creative_body"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                rows="4" placeholder="{{ __('Get up to 40% off today. Message us on WhatsApp to claim your offer.') }}"
                                required></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Ad image') }}
                                <span class="text-accent-coral">*</span></label>
                            <div
                                class="flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer transition hover:bg-wa-bubble">
                                <span
                                    class="w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                        <circle cx="6" cy="7" r="1.5" />
                                        <path d="M2 11l4-3 4 3 4-2" />
                                    </svg></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12px] font-semibold text-ink-900">{{ __('Choose image') }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">1200×628 recommended · max 10MB
                                    </div>
                                </div>
                                <span
                                    class="text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0">{{ __('Browse') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 04: Admin override -->
                <div class="px-[18px] py-4">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-accent-coral/10 text-accent-coral inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">04</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Admin override') }}</span>
                        <span class="font-mono text-[10px] text-accent-coral">{{ __('use carefully') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label
                            class="hairline border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Charge to platform credit') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Bill the campaign to :app, not the workspace.', ['app' => brand_name()]) }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" name="bill_platform"><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <label
                            class="hairline border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Notify workspace owner') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Email + in-app notification on publish.') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" name="notify_owner" checked><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="admin-note">{{ __('Admin note (internal)') }}</label>
                            <textarea id="admin-note" name="admin_note" rows="2"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Why are you creating this on behalf of the workspace? (visible only to admins)') }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: preview + checklist -->
            <aside class="sticky top-[78px] self-start space-y-3">
                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="flex items-center justify-between mb-2 px-1">
                        <div
                            class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>Ad preview</div>
                        <span
                            class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('Messages') }}</span>
                    </div>
                    <div class="bg-white border border-paper-200 rounded-[10px] overflow-hidden">
                        <div
                            class="h-[168px] bg-[#DFF1ED] flex items-center justify-center text-wa-deep text-[11px] font-mono">
                            {{ __('Image preview') }}</div>
                        <div class="p-3">
                            <div class="text-[10px] uppercase tracking-[0.12em] text-ink-500 font-mono mb-1">
                                {{ __('Sponsored') }}</div>
                            <div class="text-[14px] font-semibold text-ink-900">{{ __('Your headline appears here') }}
                            </div>
                            <p class="text-[12px] text-ink-600 leading-relaxed mt-1">
                                {{ __('Write ad copy to preview it here.') }}</p>
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span class="text-[11px] text-ink-500 truncate">{{ __('yourwebsite.com') }}</span>
                                <span
                                    class="px-3 py-1.5 rounded-md bg-paper-100 text-[11px] font-semibold text-ink-900">{{ __('Send Message') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 px-1">
                        {{ __('Workspace summary') }}</div>
                    <div class="space-y-2 text-[12px]">
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Workspace') }}</span><b>Bloomly</b></div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Plan') }}</span><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Pro') }}</span>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Credit balance') }}</span><b
                                class="font-mono text-wa-deep">$4,820.00</b></div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Active campaigns') }}</span><b class="font-mono">3</b>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Daily cap') }}</span><b class="font-mono">$500/day</b>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-paper-200 rounded-[14px] shadow-card p-3 text-[11px] text-ink-700 leading-snug">
                    <b>Admin checklist:</b> workspace selected · objective set · budget within plan cap · creative
                    reviewed for Meta policy · destination URL valid.
                </div>
            </aside>
        </form>
    </main>

</x-layouts.admin>
