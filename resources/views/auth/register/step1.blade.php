<x-layouts.guest :title="__('Create your account / Step 1')" page="auth-register-step1">
    @php $__brandName = (string) brand_name(); @endphp

    <div class="grid lg:grid-cols-[540px_1fr] h-screen overflow-hidden">

        <!-- LEFT: form (single screen, no scroll) -->
        <main class="flex flex-col justify-center px-6 py-6 lg:px-14 order-2 lg:order-1 overflow-hidden">
            <div class="w-full max-w-[420px] mx-auto">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-6 lg:hidden">
                    <span class="w-8 h-8 rounded-md bg-wa-deep text-paper-0 grid place-items-center"><svg
                            viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12c0 1.96.57 3.79 1.55 5.34L2 22l4.78-1.5A9.93 9.93 0 0 0 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2Z" />
                        </svg></span>
                    <span class="font-serif text-[22px] tracking-[-0.01em]">{{ $__brandName }}</span>
                </a>

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Step 1 of 2 / Account') }}</div>
                <h2 class="font-serif text-[32px] leading-tight tracking-[-0.01em]">{{ __('Create your') }} <span
                        class="italic text-wa-deep">{{ __('account') }}</span>.</h2>
                <p class="text-[12.5px] text-ink-600 mt-1.5">{{ __('Free to start. Cancel any time.') }}</p>

                <!-- Steps indicator -->
                <ol class="flex items-center gap-2 mt-4 mb-5 text-[10.5px] font-mono uppercase tracking-wider">
                    <li class="text-wa-deep flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">1</span>Account
                    </li>
                    <li class="w-4 h-px bg-paper-300"></li>
                    <li class="text-ink-500 flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-paper-100 grid place-items-center text-[10px]">2</span>Workspace
                    </li>
                </ol>

                @if ($errors->any())
                    <div
                        class="mb-3 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                        @foreach ($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ url('/register') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Full name') }}</label>
                        <input required type="text" name="name" value="{{ old('name') }}"
                            placeholder="{{ __('Your name') }}"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Work email') }}</label>
                        <input required type="email" name="email" value="{{ old('email') }}"
                            placeholder="you@company.com"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <div>
                        <label class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Password') }}</label>
                        {{-- Eye-reveal is auto-injected by resources/js/lib/password-reveal.js — no per-page wiring needed. --}}
                        <input id="pw" required type="password" name="password"
                            placeholder="{{ __('At least 8 characters') }}" minlength="8" autocomplete="new-password"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <div>
                        <label
                            class="text-[11.5px] font-semibold text-ink-700 mb-1 block">{{ __('Confirm password') }}</label>
                        <input required type="password" name="password_confirmation" minlength="8"
                            autocomplete="new-password"
                            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                    </div>
                    <label class="flex items-start gap-2 cursor-pointer pt-1">
                        <input type="checkbox" name="agree" value="1" required
                            class="w-4 h-4 accent-wa-deep mt-0.5" />
                        <span class="text-[11.5px] text-ink-700 leading-snug">{{ __('I agree to') }}
                            {{ $__brandName }}{{ __("'s") }} <a
                                class="text-wa-deep font-semibold hover:underline" href="{{ legal_url('terms') }}"
                                target="_blank">{{ __('Terms') }}</a> and <a
                                class="text-wa-deep font-semibold hover:underline" href="{{ legal_url('privacy') }}"
                                target="_blank">{{ __('Privacy policy') }}</a>.</span>
                    </label>

                    <button type="submit"
                        class="w-full px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-1">
                        {{ __('Continue / Set up workspace') }}
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="1.7">
                            <path d="M3 8h10M9 4l4 4-4 4" />
                        </svg>
                    </button>

                    <p class="text-[12px] text-ink-600 text-center">{{ __('Already have an account?') }} <a
                            href="{{ url('/login') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Sign in') }}</a></p>
                </form>
            </div>
        </main>

        <!-- RIGHT: visual showcase -->
        <aside class="auth-art relative hidden lg:flex flex-col p-10 text-paper-0 overflow-hidden order-1 lg:order-2">
            <div class="blob bg-wa-green w-[300px] h-[300px] -top-12 -right-12"></div>
            <div class="blob bg-accent-amber w-[260px] h-[260px] bottom-12 left-12"></div>

            <div class="relative z-10 flex-1 flex flex-col justify-center w-full">

                <!-- Top: Hero card spanning full width -->
                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-5 mb-4">
                    <div class="flex items-start gap-3">
                        <span
                            class="w-10 h-10 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center shrink-0">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor"
                                stroke-width="1.6">
                                <path d="M2 4h12v8H2zM5 4v8M11 4v8" />
                            </svg>
                        </span>
                        <div>
                            <div class="text-[14px] font-semibold leading-tight">{{ __('Multi-workspace') }}</div>
                            <div class="text-[12px] text-paper-0/75 leading-snug mt-1">
                                {{ __('Run agencies, brands or clients side-by-side / each one fully isolated.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3">
                    {{ __('Create / switch / collaborate') }}</div>
                <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]">{{ __('Multiple') }} <span
                        class="italic text-wa-green">{{ __('workspaces') }}</span>, one login.</h1>
                <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed">
                    {{ __('Each workspace runs its own contacts, devices, broadcasts, flows, and templates / fully isolated. Switch from the top bar.') }}
                </p>

                <!-- Stat pills (full width) -->
                <div class="grid grid-cols-3 gap-3 mt-5">
                    <div class="stat-pill rounded-2xl p-4 text-center">
                        <div class="font-serif text-[24px] leading-none">42M+</div>
                        <div class="text-[10.5px] text-paper-0/70 mt-1">{{ __('messages sent') }}</div>
                    </div>
                    <div class="stat-pill rounded-2xl p-4 text-center">
                        <div class="font-serif text-[24px] leading-none">99.9%</div>
                        <div class="text-[10.5px] text-paper-0/70 mt-1">{{ __('delivery rate') }}</div>
                    </div>
                    <div class="stat-pill rounded-2xl p-4 text-center">
                        <div class="font-serif text-[24px] leading-none">4.9 *</div>
                        <div class="text-[10.5px] text-paper-0/70 mt-1">{{ __('G2 / Capterra') }}</div>
                    </div>
                </div>

                <!-- Two-column feature row -->
                <div class="grid grid-cols-2 gap-3 mt-5">
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M3 8h3l1.5-4 2 8 1.5-4h2" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Broadcasts') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Send to thousands at once with smart throttling and per-contact tracking.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M5 3l8 5-8 5z" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Flow builder') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Trigger / branch / wait / AI assist. Drag-drop the whole conversation.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path
                                        d="M3 5.5A2.5 2.5 0 0 1 5.5 3h5A2.5 2.5 0 0 1 13 5.5v3A2.5 2.5 0 0 1 10.5 11H8l-3.5 2v-2A2.5 2.5 0 0 1 3 8.5v-3Z" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Team inbox') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Live shared inbox with assignments, internal notes, and AI suggestions.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M3 5h8l1 6H4z" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Shopify + Woo') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Cart recovery, order updates, and catalog sync out of the box.') }}</div>
                    </div>
                </div>

                <!-- Bottom: What's inside compact list -->
                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4 mt-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70 mb-2">
                        {{ __("What's also inside") }}</div>
                    <div class="grid grid-cols-3 gap-x-3 gap-y-1.5 text-[11.5px] text-paper-0/85">
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Templates') }}</span>
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Meta Ads / CTWA') }}</span>
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('AI assist') }}</span>
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Webhooks') }}</span>
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Auto-replies') }}</span>
                        <span class="flex items-center gap-1.5"><svg viewBox="0 0 16 16"
                                class="w-3 h-3 text-wa-green shrink-0" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg>{{ __('Encrypted') }}</span>
                    </div>
                </div>
            </div>

            <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right">2026 {{ $__brandName }}
                / Mumbai, India</div>
        </aside>
    </div>

    {{-- eye-toggle wiring lives in resources/js/charts/auth-register.js --}}

</x-layouts.guest>
