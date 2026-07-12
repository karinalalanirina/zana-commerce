<x-layouts.guest :title="__('Create your workspace / Step 2')" page="auth-register-step2">
    @php $__brandName = brand_name(); @endphp

    <style>
        .ts-control {
            border-color: rgb(var(--color-paper-200, 230 226 215)) !important;
            border-radius: 0.5rem;
            background: #fff !important;
            font-size: 13px !important;
            padding: 8px 10px !important;
            min-height: 42px !important;
        }

        .ts-wrapper.focus .ts-control {
            border-color: #075E54 !important;
            box-shadow: 0 0 0 4px rgba(7, 94, 84, 0.10) !important;
        }

        .ts-dropdown {
            font-size: 12.5px;
            border-radius: 0.5rem;
            border-color: #075E54;
        }

        .ts-dropdown .active {
            background: #075E54;
            color: #fff;
        }
    </style>

    <div class="grid lg:grid-cols-[1fr_540px] h-screen overflow-hidden">

        <!-- LEFT: visual showcase -->
        <aside class="auth-art relative hidden lg:flex flex-col p-10 text-paper-0 overflow-hidden">
            <div class="blob bg-wa-green w-[300px] h-[300px] -top-12 -left-12"></div>
            <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 right-12"></div>

            <div class="relative z-10 flex-1 flex flex-col justify-center w-full">

                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-5 mb-4">
                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M4 7V5a4 4 0 0 1 8 0v2M3 7h10v7H3z" />
                            </svg>
                        </span>
                        <div>
                            <div class="text-[14px] font-semibold leading-tight">{{ __('Sealed workspace') }}</div>
                            <div class="text-[12px] text-paper-0/75 leading-snug mt-1">
                                {{ __('Contacts, devices and billing live inside this workspace only / never bleed across.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3">
                    {{ __('Sealed by design') }}</div>
                <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]">{{ __('Workspaces are') }} <span
                        class="italic text-wa-green">{{ __('isolated') }}</span>.</h1>
                <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed">
                    {{ __('Contacts, devices, broadcasts, flows, templates, even billing / scoped per workspace. Nothing leaks across.') }}
                </p>

                <div class="grid grid-cols-2 gap-3 mt-5">
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="6" r="2.5" />
                                    <path d="M3 13a5 5 0 0 1 10 0" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Own contacts') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Independent contact lists, groups and tags / per workspace.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="4" y="2" width="8" height="12" rx="1.5" />
                                    <path d="M7 11h2" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Own devices') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Pair WhatsApp numbers per workspace / no cross-talk.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="2" y="3" width="12" height="10" rx="1.5" />
                                    <path d="M2 6h12M5 9h6" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Own templates') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Separate template library + flow library per workspace.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="6" cy="6" r="2.5" />
                                    <path d="M2 14c0-2.5 2-4 4-4s4 1.5 4 4" />
                                    <circle cx="11.5" cy="5" r="2" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Invite teammates') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Add owners, admins, members. Roles scoped per workspace.') }}</div>
                    </div>
                </div>

                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4 mt-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70 mb-2">
                        {{ __('Switch any time / from the top bar') }}</div>
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-wa-green/15 border border-wa-green/30 text-[12px]">
                            <span
                                class="w-6 h-6 rounded-md bg-wa-green/30 text-wa-green grid place-items-center text-[10px] font-semibold">BL</span>
                            <span class="flex-1 font-medium truncate">{{ __('Bloomly') }}</span>
                            <svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-green shrink-0" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>
                        </div>
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] text-paper-0/65">
                            <span
                                class="w-6 h-6 rounded-md bg-paper-0/15 grid place-items-center text-[10px] font-semibold">AN</span>
                            <span class="flex-1 truncate">{{ __('Anyaco') }}</span>
                        </div>
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12px] text-paper-0/65">
                            <span
                                class="w-6 h-6 rounded-md bg-paper-0/15 grid place-items-center text-[10px] font-semibold">RV</span>
                            <span class="flex-1 truncate">{{ __('Ravinder & Co') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right">2026 {{ $__brandName }} /
                Mumbai, India</div>
        </aside>

        <!-- RIGHT: form -->
        <main class="flex flex-col justify-center px-6 py-6 lg:px-10 overflow-y-auto">
            <div class="w-full max-w-[420px] mx-auto">

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Step 2 of 3 / Workspace') }}</div>
                <h2 class="font-serif text-[30px] leading-tight tracking-[-0.01em]">{{ __('Create your') }} <span
                        class="italic text-wa-deep">{{ __('workspace') }}</span>.</h2>

                <ol class="flex items-center gap-2 mt-3 mb-4 text-[10.5px] font-mono uppercase tracking-wider">
                    <li class="text-ink-500 flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center text-[10px]">✓</span>Account
                    </li>
                    <li class="w-4 h-px bg-wa-deep"></li>
                    <li class="text-wa-deep flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">2</span>Workspace
                    </li>
                    <li class="w-4 h-px bg-paper-200"></li>
                    <li class="text-ink-500 flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">3</span>Plan
                    </li>
                </ol>

                @if (!empty($existing) && $existing->isNotEmpty())
                    <div class="mb-4 rounded-xl bg-paper-50 border border-paper-200 p-3">
                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">
                            {{ __('Already have') }}</div>
                        <div class="space-y-1.5">
                            @foreach ($existing as $w)
                                <div class="flex items-center justify-between text-[12.5px]">
                                    <span class="font-medium text-ink-900">{{ $w->name }}</span>
                                    <span class="font-mono text-[10.5px] text-ink-500">{{ $w->slug }}</span>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('register.plan') }}"
                            class="mt-2 inline-flex items-center gap-1 text-[11.5px] text-wa-deep font-semibold hover:underline">
                            Skip / continue to plan <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none"
                                stroke="currentColor" stroke-width="1.7">
                                <path d="M3 8h10M9 4l4 4-4 4" />
                            </svg>
                        </a>
                    </div>
                @endif

                @if ($errors->any())
                    <div
                        class="mb-3 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('register.workspace.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Workspace name') }}</label>
                        <input required type="text" name="name" maxlength="120"
                            value="{{ old('name', $suggested_name ?? '') }}"
                            placeholder="{{ __('e.g. Bloomly Marketing') }}"
                            class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('A friendly label your team will see at the top of the app.') }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Industry') }}</label>
                            <select name="industry"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select industry') }}</option>
                                @foreach (['ecommerce', 'saas', 'agency', 'education', 'healthcare', 'finance', 'travel', 'hospitality', 'other'] as $opt)
                                    <option value="{{ $opt }}" @selected(old('industry') === $opt)>
                                        {{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Team size') }}</label>
                            <select name="size_range"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                <option value="">{{ __('Select team size') }}</option>
                                @foreach (['1', '2-5', '6-20', '21-100', '100+'] as $opt)
                                    <option value="{{ $opt }}" @selected(old('size_range') === $opt)>
                                        {{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block"
                            for="ws-timezone">{{ __('Timezone') }}</label>
                        <select id="ws-timezone" name="timezone" class="w-full">
                            @php $picked = old('timezone', 'Asia/Kolkata'); @endphp
                            <option value="{{ $picked }}" selected>{{ $picked }}</option>
                        </select>
                        <div class="text-[10.5px] text-ink-500 mt-1">
                            {{ __('Type to search any IANA timezone (Asia/Kolkata, Europe/London, etc.).') }}</div>
                    </div>

                    <button type="submit"
                        class="w-full px-4 py-2.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-2">
                        {{ __('Continue / pick a plan') }}
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </button>
                    <p class="text-[11.5px] text-ink-500 text-center">
                        {{ __('You can create more workspaces any time from the top bar.') }}</p>
                </form>
            </div>
        </main>

    </div>

</x-layouts.guest>
