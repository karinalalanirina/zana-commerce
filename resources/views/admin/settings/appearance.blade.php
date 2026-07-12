<x-layouts.admin :title="__('Appearance')" admin-key="settings">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.settings.index') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Appearance') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    {{-- Reset form lives OUTSIDE the colours form (forms can't nest). The reset
         button in the aside submits it via its form="appearance-reset" attribute. --}}
    <form id="appearance-reset" method="POST" action="{{ route('admin.settings.appearance.reset') }}"
        onsubmit="return confirm('{{ __('Reset every colour back to the shipped defaults?') }}')" class="hidden">
        @csrf
    </form>

    @php $groups = collect($palette)->groupBy(fn ($m) => $m[2], true); @endphp

    <form method="POST" action="{{ route('admin.settings.appearance.update') }}">
        @csrf

        <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Appearance') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[34px] lg:text-[40px] leading-[1.0]">{{ __('Dashboard') }}
                        <span class="italic text-wa-deep">{{ __('colours') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Recolour the entire app — every page of the user dashboard AND the admin panel — by changing these theme colours. Saving applies instantly to everyone; no rebuild needed. Leave a colour at its default to keep the shipped look.') }}
                    </p>
                </div>
                <div class="flex items-center flex-wrap gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    @if (session('status'))
                        <span class="px-3 py-1.5 rounded-full bg-wa-mint text-wa-deep border border-wa-green/40 text-[11.5px] font-mono">{{ session('status') }}</span>
                    @endif
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save colours') }}</button>
                </div>
            </div>

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">

                {{-- LEFT — colour groups --}}
                <div class="space-y-5 min-w-0">
                    @foreach ($groups as $groupName => $tokens)
                        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                            <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between gap-3">
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ \Illuminate\Support\Str::slug($groupName) }}</div>
                                    <h2 class="font-serif text-[22px] leading-tight mt-1">{{ __($groupName) }}</h2>
                                </div>
                                <span class="rounded-full bg-paper-50 text-ink-500 border border-paper-200 px-2.5 py-1 text-[11px] font-mono">{{ count($tokens) }} {{ __('tokens') }}</span>
                            </div>
                            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach ($tokens as $key => $m)
                                    @php $hex = $values[$key] ?? ($m[1] ?? '#000000'); @endphp
                                    <label class="flex items-center gap-3 border border-paper-200 rounded-xl p-3 hover:bg-paper-50 cursor-pointer">
                                        <input type="color" name="colors[{{ $key }}]" value="{{ $hex }}"
                                            class="w-10 h-10 rounded-lg cursor-pointer border border-paper-200 bg-transparent shrink-0 p-0">
                                        <span class="min-w-0">
                                            <span class="block text-[12.5px] font-semibold text-ink-900 truncate">{{ __($m[0]) }}</span>
                                            <span class="block text-[10.5px] font-mono text-ink-500">{{ $key }} · {{ $hex }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                {{-- RIGHT — guide + reset --}}
                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Quick guide') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('How colours work') }}</h3>
                        </div>
                        <div class="p-4 space-y-3 text-[12px] text-ink-700">
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Applies instantly') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Saving rewrites the live theme tokens for everyone — no rebuild, no deploy.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('One token, one job') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Each swatch maps to a single CSS variable used across both the user dashboard and the admin panel.') }}</p>
                            </div>
                            <div>
                                <div class="font-semibold text-[12.5px] text-ink-900">{{ __('Leave defaults alone') }}</div>
                                <p class="text-ink-600 mt-0.5">{{ __('Any colour left at its shipped value keeps the original WaDesk look — only changed tokens are overridden.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-accent-coral/30 rounded-2xl shadow-card p-4">
                        <div class="text-[12.5px] font-semibold text-ink-900">{{ __('Reset to defaults') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Clears all overrides and restores the original WaDesk palette.') }}</p>
                        <button type="submit" form="appearance-reset"
                            class="mt-3 w-full px-4 py-2 rounded-full border border-accent-coral/40 text-accent-coral hover:bg-accent-coral/10 text-[12.5px] font-semibold">{{ __('Reset all colours') }}</button>
                    </div>

                    <div class="bg-wa-bubble border border-wa-green/40 rounded-2xl p-4">
                        <div class="font-semibold text-[12.5px]">{{ __('Tip') }}</div>
                        <p class="text-[11.5px] text-ink-600 mt-1">{{ __('Check contrast after big changes — text colours and background colours both come from these tokens.') }}</p>
                    </div>
                </aside>

            </section>

            {{-- Sticky save bar --}}
            <div class="admin-save-bar flex items-center justify-between gap-3 mt-2 px-4 py-2.5 bg-paper-0 border border-paper-200 rounded-full shadow-card">
                <span class="text-[11.5px] text-ink-500">{{ __('Applies to the whole dashboard the moment you save.') }}</span>
                <div class="flex items-center gap-2">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
                    <button type="submit"
                        class="px-5 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save colours') }}</button>
                </div>
            </div>

        </main>
    </form>

</x-layouts.admin>
