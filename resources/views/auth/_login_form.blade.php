{{-- Login form column — shared by the original (variant 1) layout AND the
     <x-auth-shell> variants 2–5. No outer chrome / no logo here; the wrapper
     supplies those. --}}
@php $__brandName = (string) brand_name(); @endphp

<div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Sign in') }}</div>
<h2 class="font-serif text-[34px] leading-tight tracking-[-0.01em]">{{ __('Welcome') }} <span
        class="italic text-wa-deep">{{ __('back') }}</span>.</h2>
<p class="text-[13px] text-ink-600 mt-2">{{ __('Enter your details below to get back into your workspace.') }}</p>

@include('auth._social')

@if ($errors->any())
    <div class="mb-3 rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
        @foreach ($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
@endif
@if (session('status'))
    <div class="mb-3 rounded-lg border border-wa-green/40 bg-wa-mint text-wa-deep px-3 py-2 text-[12.5px]">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-[11.5px] font-semibold text-ink-700 mb-1.5 block">{{ __('Email') }}</label>
        <input name="email" type="email" required value="{{ old('email') }}" autocomplete="email" autofocus
            placeholder="you@company.com"
            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>
    <div>
        <div class="flex items-center justify-between mb-1.5">
            <label class="text-[11.5px] font-semibold text-ink-700">{{ __('Password') }}</label>
            <a href="{{ route('password.request') }}"
                class="text-[11px] text-wa-deep font-semibold hover:underline cursor-pointer">{{ __('Forgot?') }}</a>
        </div>
        <input id="pw" name="password" type="password" required autocomplete="current-password" placeholder="********"
            class="w-full px-3 py-2.5 border border-paper-200 rounded-lg bg-paper-0 text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
    </div>

    <label class="flex items-center gap-2 cursor-pointer">
        <input name="remember" type="checkbox" value="1" checked class="w-4 h-4 accent-wa-deep" />
        <span class="text-[12.5px] text-ink-700">{{ __('Keep me signed in for 30 days') }}</span>
    </label>

    @include('auth._recaptcha', ['action' => 'login'])

    <button type="submit"
        class="w-full px-4 py-3 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[13px] font-semibold inline-flex items-center justify-center gap-2 mt-2">
        <span>{{ __('Sign in') }}</span>
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8h10M9 4l4 4-4 4" /></svg>
    </button>

    <p class="text-[12.5px] text-ink-600 text-center mt-4">{{ __('New to') }} {{ $__brandName }}?
        <a href="{{ route('register') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Create an account') }}</a></p>
</form>

@php
    $authTermsUrl   = \App\Models\SystemSetting::get('privacy_terms_url', '')   ?: url('/legal/terms');
    $authPrivacyUrl = \App\Models\SystemSetting::get('privacy_policy_url', '')  ?: url('/legal/privacy');
    $authCookiesUrl = (string) \App\Models\SystemSetting::get('privacy_cookies_policy_url', '');
@endphp
<p class="text-[10.5px] text-ink-500 text-center mt-8 leading-relaxed">
    {{ __('By signing in you agree to our') }}
    <a href="{{ $authTermsUrl }}" target="_blank" rel="noopener" class="text-wa-deep font-semibold hover:underline">{{ __('Terms') }}</a> {{ __('and') }}
    <a href="{{ $authPrivacyUrl }}" target="_blank" rel="noopener" class="text-wa-deep font-semibold hover:underline">{{ __('Privacy policy') }}</a>@if ($authCookiesUrl !== ''), <a href="{{ $authCookiesUrl }}" target="_blank" rel="noopener" class="text-wa-deep font-semibold hover:underline">{{ __('Cookies') }}</a>@endif.
</p>
