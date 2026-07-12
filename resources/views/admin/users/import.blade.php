<x-layouts.admin :title="__('Admin · Import users')" admin-key="users">



    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/users') }}" class="hover:text-ink-900">{{ __('Users') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Import') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ url('/admin/users') }}"
                class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button
                class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 8l5 5 7-9" />
                </svg>
                Import users
            </button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div>
            <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                {{ __('Admin · Users · Bulk import') }}</div>
            <h1 class="font-serif font-normal tracking-[-0.01em] text-[36px] leading-[1.0]">
                {{ __('Import users from') }} <span class="italic text-wa-deep">{{ __('CSV') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                {{ __("Upload a CSV with up to 5,000 users at a time. They'll all receive a magic-link login email after import succeeds.") }}
            </p>
        </div>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-5">

            <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5 space-y-5">

                <div>
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">01</span>
                        <span class="font-serif text-[18px] leading-none">{{ __('Download the template') }}</span>
                    </div>
                    <div class="px-4 py-3 border border-paper-200 rounded-xl flex items-center gap-3">
                        <span class="w-10 h-10 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center"><svg
                                viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M3 2h7l3 3v9H3z" />
                                <path d="M10 2v3h3" />
                                <path d="M5 9h6M5 11h4" />
                            </svg></span>
                        <div class="flex-1 min-w-0">
                            <div class="text-[13px] font-semibold">{{ __('wadesk-users-template.csv') }}</div>
                            <div class="text-[10.5px] text-ink-500 font-mono">
                                {{ __('Columns: name, email, mobile, role, workspace, gender, address, status') }}
                            </div>
                        </div>
                        <a href="{{ route('admin.users.import.template') }}"
                            class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold flex items-center gap-2 hover:bg-wa-teal">
                            <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10" />
                            </svg>
                            Download
                        </a>
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">02</span>
                        <span class="font-serif text-[18px] leading-none">{{ __('Choose CSV file') }} <span
                                class="text-accent-coral">*</span></span>
                    </div>
                    <label
                        class="block px-6 py-10 border-2 border-dashed border-paper-300 hover:border-wa-deep rounded-xl bg-paper-50/40 hover:bg-wa-bubble/30 transition cursor-pointer text-center">
                        <input type="file" accept=".csv" class="sr-only">
                        <svg viewBox="0 0 32 32" class="w-12 h-12 mx-auto text-wa-deep mb-3" fill="none"
                            stroke="currentColor" stroke-width="1.5">
                            <path d="M16 4v16m0 0l-6-6m6 6l6-6M6 26h20" />
                        </svg>
                        <div class="text-[15px] font-semibold">{{ __('Drag & drop your CSV here') }}</div>
                        <div class="text-[11.5px] text-ink-500 mt-1">or <span
                                class="text-wa-deep font-semibold underline">{{ __('browse') }}</span> · max 5,000
                            rows · 10 MB</div>
                    </label>
                </div>

                <div>
                    <div class="flex items-center gap-2.5 mb-3">
                        <span
                            class="w-[23px] h-[23px] rounded-[7px] bg-paper-50 text-wa-deep inline-flex items-center justify-center text-[10px] font-semibold font-mono shrink-0">03</span>
                        <span class="font-serif text-[18px] leading-none">{{ __('Import options') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="default-workspace">{{ __('Default workspace') }}</label>
                            <select id="default-workspace"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option>{{ __('Use CSV value') }}</option>
                                <option>{{ __('Bloomly') }}</option>
                                <option>{{ __('FitKart') }}</option>
                                <option>{{ __('Northstar Clinic') }}</option>
                                <option>{{ __('QuickBite') }}</option>
                            </select>
                            <div class="text-[10.5px] text-ink-500 mt-1">
                                {{ __('Applied to rows missing the workspace column.') }}</div>
                        </div>
                        <div>
                            <label class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block"
                                for="default-role">{{ __('Default role') }}</label>
                            <select id="default-role"
                                class="w-full px-[11px] py-[7px] border border-paper-200 rounded-lg bg-white text-[12.5px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option selected>{{ __('Agent') }}</option>
                                <option>{{ __('Manager') }}</option>
                                <option>{{ __('Owner') }}</option>
                                <option>{{ __('Viewer') }}</option>
                            </select>
                        </div>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Send welcome email') }}</span>
                                <span
                                    class="block text-[10.5px] text-ink-500">{{ __('Magic-link login per row') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" checked><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                        <label
                            class="border border-paper-200 rounded-lg px-3 py-2.5 flex items-center justify-between gap-3 cursor-pointer hover:bg-paper-50">
                            <span>
                                <span class="block text-[12.5px] font-semibold">{{ __('Skip duplicates') }}</span>
                                <span class="block text-[10.5px] text-ink-500">{{ __('Match by email') }}</span>
                            </span>
                            <span class="relative inline-block w-[34px] h-5 shrink-0"><input
                                    class="peer opacity-0 w-0 h-0" type="checkbox" checked><span
                                    class="absolute cursor-pointer inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[14px]"></span></span>
                        </label>
                    </div>
                </div>

            </div>

            <aside class="space-y-3">
                <div class="bg-white border border-paper-200 rounded-[14px] shadow-card p-5">
                    <div class="font-mono text-[9.5px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                        {{ __('CSV requirements') }}</div>
                    <ul class="text-[12px] text-ink-700 space-y-2">
                        <li class="flex gap-2"><span class="text-wa-deep">✓</span>UTF-8 encoded</li>
                        <li class="flex gap-2"><span class="text-wa-deep">✓</span>Comma-separated</li>
                        <li class="flex gap-2"><span class="text-wa-deep">✓</span>First row must be the header</li>
                        <li class="flex gap-2"><span class="text-wa-deep">✓</span>Required: name, email, mobile, role
                        </li>
                        <li class="flex gap-2"><span class="text-ink-500">·</span>Optional: workspace, gender,
                            address, status</li>
                        <li class="flex gap-2"><span class="text-accent-coral">⚠</span>Max 5,000 rows / 10 MB</li>
                    </ul>
                </div>

                <div
                    class="bg-wa-bubble/40 border border-wa-green/30 rounded-[14px] shadow-card p-4 text-[11.5px] text-ink-700 leading-snug">
                    <b class="text-ink-900">Last import:</b> 412 users imported successfully · 8 skipped (duplicates) ·
                    0 failed · 2 hours ago.
                </div>
            </aside>
        </div>
    </main>

</x-layouts.admin>
