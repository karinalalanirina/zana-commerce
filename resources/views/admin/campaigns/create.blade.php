<x-layouts.admin :title="__('Admin · New WA Campaign')" admin-key="campaigns" page="admin-campaigns-create">



    <!-- Admin top bar -->
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/campaigns') }}" class="hover:text-ink-900">{{ __('Campaigns') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('New') }}</span>
        </div>
        <div class="ml-auto flex items-center flex-wrap gap-2">
            <span
                class="pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-paper-50 text-ink-700 mono font-mono">{{ __('Draft / unsaved') }}</span>
            <a href="{{ url('/admin/campaigns') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="button"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Save draft') }}</button>
            <button type="submit" form="campaignForm"
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                {{ __('Schedule send') }}
            </button>
        </div>
    </header>

    <!-- Page heading -->
    <div class="px-4 sm:px-6 lg:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · WA Campaigns · New') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">{{ __('Create WhatsApp') }}
                <span class="italic text-wa-deep">{{ __('campaign') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2">
                {{ __('Compose a broadcast on behalf of any workspace. As admin, you can override approval, daily caps, and audience size limits.') }}
            </p>
        </div>
    </div>

    <!-- Form -->
    <main class="px-4 sm:px-6 lg:px-7 pb-7">
        <form id="campaignForm" class="grid grid-cols-1 xl:grid-cols-[1fr_342px] gap-5">

            <!-- LEFT: form sections -->
            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card">

                <!-- Section 00: Admin context -->
                <div class="px-[18px] py-4 border-b border-paper-200 bg-wa-bubble/30">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-wa-deep text-paper-0 inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">00</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Admin context') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('admin only') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                                {{ __("Broadcast goes through this workspace's WABA.") }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="device">{{ __('Sending device') }} <span
                                    class="text-accent-coral">*</span></label>
                            <select id="device" name="device_id"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option>{{ __('+91 98765 43210 · Sales (Bloomly)') }}</option>
                                <option>{{ __('+91 98765 11225 · Support (Bloomly)') }}</option>
                                <option>{{ __('+91 89283 99002 · Marketing (Bloomly)') }}</option>
                            </select>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('3 numbers linked to this workspace.') }}</div>
                        </div>
                        <div class="flex items-end">
                            <label
                                class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50 w-full bg-paper-0">
                                <span>
                                    <span
                                        class="block text-[12.5px] font-semibold">{{ __('Override daily cap') }}</span>
                                    <span
                                        class="block text-[10.5px] text-ink-500">{{ __('Bypass workspace plan limit') }}</span>
                                </span>
                                <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                        class="peer opacity-0 w-0 h-0" type="checkbox" name="override_cap"><span
                                        class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section 01: Campaign basics -->
                <div class="px-[18px] py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Campaign basics') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('required') }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="campaign-name">{{ __('Campaign name') }} <span
                                    class="text-accent-coral">*</span></label>
                            <input id="campaign-name" name="name" type="text"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Spring lipstick launch') }}" required>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __("Internal label only · customers don't see this.") }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="msg-type">{{ __('Message type') }} <span
                                    class="text-accent-coral">*</span></label>
                            <select id="msg-type" name="msg_type"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>
                                <option value="custom">{{ __('Custom message') }}</option>
                                <option value="template" selected>{{ __('Template') }}</option>
                                <option value="flow">{{ __('Flow builder') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="template">{{ __('Template') }}</label>
                            <select id="template" name="template_id"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option>{{ __('spring_launch_v1 · approved') }}</option>
                                <option>{{ __('vip_coupon_v3 · approved') }}</option>
                                <option>{{ __('cart_recovery_v2 · approved') }}</option>
                            </select>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Only Meta-approved templates from this workspace.') }}</div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="schedule">{{ __('Schedule') }}</label>
                            <select id="schedule" name="schedule_mode"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="now">{{ __('Send immediately') }}</option>
                                <option value="schedule" selected>{{ __('Schedule for later') }}</option>
                                <option value="recurring">{{ __('Recurring') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="schedule-date">{{ __('Send date') }}</label>
                            <input id="schedule-date" name="schedule_date" type="datetime-local"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                value="2026-04-29T09:00">
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="timezone">{{ __('Timezone') }}</label>
                            <select id="timezone" name="timezone"
                                data-value="{{ old('timezone', 'Asia/Kolkata') }}"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="Asia/Kolkata">{{ __('Asia/Kolkata') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 02: Audience -->
                <div class="px-[18px] py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Audience') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('recipients') }}</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="audience-source">{{ __('Source') }}</label>
                            <select id="audience-source" name="audience_source"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option>{{ __('All contacts') }}</option>
                                <option selected>{{ __('Contact group') }}</option>
                                <option>{{ __('Saved segment') }}</option>
                                <option>{{ __('Upload CSV') }}</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="contact-group">{{ __('Contact group') }}</label>
                            <select id="contact-group" name="contact_group_id"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option>{{ __('VIP customers · 1,420') }}</option>
                                <option selected>{{ __('Newsletter opt-in · 3,210') }}</option>
                                <option>{{ __('Cart abandoners · 642') }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]">{{ __('Filters (optional)') }}</label>
                            <div
                                class="flex flex-wrap gap-1.5 px-3 py-2 border border-paper-200 rounded-lg bg-paper-50 min-h-[44px]">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-paper-0 border border-paper-200 text-[11.5px]">{{ __('tag · new') }}
                                    <button type="button"
                                        class="text-ink-500 hover:text-accent-coral">×</button></span>
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-paper-0 border border-paper-200 text-[11.5px]">{{ __('country · IN') }}
                                    <button type="button"
                                        class="text-ink-500 hover:text-accent-coral">×</button></span>
                                <input type="text" placeholder="{{ __('+ add filter') }}"
                                    class="bg-transparent outline-none flex-1 min-w-[120px] text-[12.5px]" />
                            </div>
                            <div class="text-[10.5px] text-ink-500 mt-1">{{ __('Estimated audience:') }} <b
                                    class="text-wa-deep">3,210 contacts</b> · within plan limit (8,000/day).</div>
                        </div>
                    </div>
                </div>

                <!-- Section 03: Compose -->
                <div class="px-[18px] py-4 border-b border-paper-200">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                        <span
                            class="font-serif text-[18px] leading-none text-ink-900 flex-1">{{ __('Compose message') }}</span>
                        <span class="font-mono text-[10px] text-ink-500">{{ __('template body') }}</span>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="header">{{ __('Header (image or text)') }}</label>
                            <div
                                class="flex items-center gap-2.5 px-[11px] py-2.5 border border-dashed border-wa-deep rounded-lg bg-paper-0 cursor-pointer hover:bg-wa-bubble">
                                <span
                                    class="w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep grid place-items-center shrink-0"><svg
                                        viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                        stroke-width="1.6">
                                        <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                        <circle cx="6" cy="7" r="1.5" />
                                        <path d="M2 11l4-3 4 3 4-2" />
                                    </svg></span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-[12px] font-semibold text-ink-900">
                                        {{ __('Choose header image') }}</div>
                                    <div class="text-[10.5px] text-ink-500 font-mono">
                                        {{ __('JPG/PNG · 1080×566 recommended · max 5MB') }}</div>
                                </div>
                                <span
                                    class="text-[10.5px] font-semibold text-wa-deep px-[9px] py-1 rounded-full bg-white border border-wa-deep cursor-pointer shrink-0">{{ __('Browse') }}</span>
                            </div>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="body">{{ __('Body') }} <span class="text-accent-coral">*</span></label>
                            <textarea id="body" name="body" rows="5"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                required>Hi @{{ 1 }}, the spring lipstick collection just dropped 🌸 Tap below to shop with a 15% launch discount.</textarea>
                            <div class="text-[10.5px] text-ink-500 mt-1">Variables: @{{ 1 }}=name ·
                                @{{ 2 }}=order_id. Personalize from contact attributes.</div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="footer">{{ __('Footer (optional)') }}</label>
                                <input id="footer" name="footer" type="text"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="{{ __('Reply STOP to unsubscribe') }}"
                                    value="Reply STOP to unsubscribe">
                            </div>
                            <div>
                                <label
                                    class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                    for="cta">{{ __('Call to action') }}</label>
                                <select id="cta" name="cta_type"
                                    class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option>{{ __('Shop now (URL)') }}</option>
                                    <option>{{ __('Quick reply') }}</option>
                                    <option>{{ __('Phone call') }}</option>
                                    <option>{{ __('None') }}</option>
                                </select>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Skip workspace approval') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Send without owner review') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" name="skip_approval" checked><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Charge to platform credit') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Bill to :app, not workspace', ['app' => brand_name()]) }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" name="bill_platform"><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span
                                    class="block text-[12.5px] font-semibold">{{ __('Notify workspace owner') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Email + in-app on send') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" name="notify_owner" checked><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Pin to top of inbox') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Force priority queue slot') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" name="pin_priority"><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <div class="md:col-span-2">
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 flex items-center justify-between gap-2 mb-[5px]"
                                for="admin-note">{{ __('Admin note (internal)') }}</label>
                            <textarea id="admin-note" name="admin_note" rows="2"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                placeholder="{{ __('Why are you sending this on behalf of the workspace?') }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: preview + summary -->
            <aside class="sticky top-[78px] self-start space-y-3">
                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-3">
                    <div
                        class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2 px-1 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>
                        {{ __('Phone preview') }}
                    </div>
                    <div class="bg-ink-900 rounded-[24px] p-[7px] max-w-[280px] mx-auto shadow-soft">
                        <div class="bg-wa-chat rounded-[18px] min-h-[400px] flex flex-col overflow-hidden">
                            <div class="bg-wa-deep text-paper-0 px-3 py-2 flex items-center gap-2 text-[11.5px]">
                                <div
                                    class="w-6 h-6 rounded-full bg-wa-mint text-wa-deep grid place-items-center text-[9px] font-semibold">
                                    B</div>
                                <div class="leading-tight">
                                    <div class="text-[11.5px] font-semibold">
                                        {{ $previewDeviceName ?? 'Your business' }}</div>
                                    <div class="text-[9px] opacity-70">{{ $previewDeviceRegion ?? 'business · IN' }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 p-3"
                                style="background-image: radial-gradient(rgba(7,94,84,0.06) 1px, transparent 1px); background-size: 14px 14px;">
                                <div
                                    class="bg-paper-0 rounded-[7px] rounded-tl-[2px] px-2 py-2 max-w-[88%] shadow-sm mb-2">
                                    <div
                                        class="h-[100px] bg-[#DFF1ED] rounded mb-2 grid place-items-center text-[10px] text-wa-deep font-mono">
                                        {{ __('Header image') }}</div>
                                    <div class="text-[12px] text-ink-900 leading-snug">Hi
                                        <b>@{{ 1 }}</b>, the spring lipstick collection just dropped 🌸
                                        Tap below to shop with a 15% launch discount.</div>
                                    <div class="text-[10px] text-ink-500 mt-2">{{ __('Reply STOP to unsubscribe') }}
                                    </div>
                                    <div class="text-[9px] text-ink-500 text-right mt-1 font-mono">09:00 ✓✓</div>
                                </div>
                                <div
                                    class="bg-paper-0 rounded-full px-3 py-1.5 text-[11px] text-wa-deep font-semibold inline-block shadow-sm border border-wa-deep/20">
                                    → Shop now</div>
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
                                class="text-ink-500">{{ __('Daily cap') }}</span><b class="font-mono">8,000 /
                                day</b></div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Used today') }}</span><b class="font-mono">2,840</b>
                        </div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Remaining') }}</span><b
                                class="font-mono text-wa-deep">5,160</b></div>
                        <div class="flex items-center justify-between"><span
                                class="text-ink-500">{{ __('Wallet') }}</span><b
                                class="font-mono text-wa-deep">$4,820.00</b></div>
                    </div>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-paper-200 rounded-[14px] shadow-card p-3 text-[11px] text-ink-700 leading-snug">
                    <b>Admin checklist:</b> workspace selected · template approved by Meta · audience within daily cap ·
                    variables mapped · admin reason logged.
                </div>
            </aside>
        </form>
    </main>

</x-layouts.admin>
