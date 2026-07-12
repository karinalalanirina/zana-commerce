{{--
 /admin/settings/pwa — installable Progressive Web App manifest.

 Each field maps 1:1 to a `pwa_*` system_settings row.
 partials/pwa-meta.blade.php reads them on every request to emit the
 manifest link + theme-color meta. The `/manifest.json` route is
 generated dynamically from these same rows.
--}}
<x-layouts.admin :title="__('PWA settings')" admin-key="settings">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <a href="{{ url('/admin/settings') }}" class="hover:text-ink-900">{{ __('Settings') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('PWA') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <form method="POST" action="{{ route('admin.settings.pwa.update') }}" enctype="multipart/form-data"
        class="contents">
        @csrf @method('PATCH')

        <main class="px-4 sm:px-7 py-7 space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                        {{ __('Admin · Project settings') }}</div>
                    <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('PWA') }}
                        <span class="italic text-wa-deep">{{ __('settings') }}</span>.</h1>
                    <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                        {{ __('Installable app manifest: name, icons, colors, display mode, and offline cache. Drives /manifest.json + the install prompt.') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0 pb-1">
                    <a href="{{ url('/admin/settings') }}"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('All settings') }}</a>
                    <button type="reset"
                        class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Reset draft') }}</button>
                    <button type="submit"
                        class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">{{ __('Save changes') }}</button>
                </div>
            </div>

            @if (session('success'))
                <div
                    class="rounded-xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-3 text-[12.5px] font-medium">
                    {{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div
                    class="rounded-xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-3 text-[12.5px]">
                    <div class="font-semibold mb-1">{{ __('Please fix the highlighted fields:') }}</div>
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
                <div class="space-y-5 min-w-0">
                    <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-paper-200 flex items-center justify-between">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                    {{ __('Installable app') }}</div>
                                <h2 class="font-serif text-[25px] leading-tight mt-1">{{ __('PWA manifest') }}</h2>
                            </div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <span class="text-[12px] text-ink-700">{{ __('PWA enabled') }}</span>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="pwa_enabled" value="1"
                                        @checked(old('pwa_enabled', $pwa['enabled'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                        </div>
                        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('App name') }}</span>
                                <input name="pwa_app_name" value="{{ old('pwa_app_name', $pwa['app_name']) }}"
                                    maxlength="60"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Shown on the install prompt and home-screen label.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Short name') }}</span>
                                <input name="pwa_short_name" value="{{ old('pwa_short_name', $pwa['short_name']) }}"
                                    maxlength="12"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Used when the full name overflows. Keep ≤ 12 chars.') }}</span>
                            </label>

                            <label class="space-y-1.5 col-span-2">
                                <span class="text-[11.5px] font-semibold">{{ __('Description') }}</span>
                                <textarea name="pwa_description" rows="2" maxlength="300"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] focus:outline-none focus:border-wa-deep">{{ old('pwa_description', $pwa['description']) }}</textarea>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Start URL') }}</span>
                                <input name="pwa_start_url" value="{{ old('pwa_start_url', $pwa['start_url']) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Scope') }}</span>
                                <input name="pwa_scope" value="{{ old('pwa_scope', $pwa['scope']) }}"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Display mode') }}</span>
                                <select name="pwa_display"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @php $disp = old('pwa_display', $pwa['display'] ?: 'standalone'); @endphp
                                    <option value="standalone" @selected($disp === 'standalone')>{{ __('standalone') }}
                                    </option>
                                    <option value="fullscreen" @selected($disp === 'fullscreen')>{{ __('fullscreen') }}
                                    </option>
                                    <option value="minimal-ui" @selected($disp === 'minimal-ui')>{{ __('minimal-ui') }}
                                    </option>
                                    <option value="browser" @selected($disp === 'browser')>{{ __('browser') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Orientation') }}</span>
                                <select name="pwa_orientation"
                                    class="w-full rounded-xl border border-paper-200 bg-paper-0 px-3 py-2.5 text-[13px] font-mono focus:outline-none focus:border-wa-deep">
                                    @php $or = old('pwa_orientation', $pwa['orientation'] ?: 'portrait'); @endphp
                                    <option value="any" @selected($or === 'any')>{{ __('any') }}</option>
                                    <option value="portrait" @selected($or === 'portrait')>{{ __('portrait') }}</option>
                                    <option value="landscape" @selected($or === 'landscape')>{{ __('landscape') }}
                                    </option>
                                    <option value="natural" @selected($or === 'natural')>{{ __('natural') }}</option>
                                </select>
                            </label>

                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Theme color') }}</span>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="pwa_theme_color"
                                        value="{{ old('pwa_theme_color', $pwa['theme_color']) }}"
                                        class="h-11 w-14 rounded-xl border border-paper-200 bg-paper-0 px-1">
                                    <input value="{{ old('pwa_theme_color', $pwa['theme_color']) }}" readonly
                                        tabindex="-1"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono">
                                </div>
                                <span class="text-[11px] text-ink-500">{{ __('Tints the Android URL bar.') }}</span>
                            </label>
                            <label class="space-y-1.5">
                                <span class="text-[11.5px] font-semibold">{{ __('Background color') }}</span>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="pwa_background_color"
                                        value="{{ old('pwa_background_color', $pwa['background_color']) }}"
                                        class="h-11 w-14 rounded-xl border border-paper-200 bg-paper-0 px-1">
                                    <input value="{{ old('pwa_background_color', $pwa['background_color']) }}"
                                        readonly tabindex="-1"
                                        class="flex-1 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5 text-[13px] font-mono">
                                </div>
                                <span
                                    class="text-[11px] text-ink-500">{{ __('Shown while the app is loading.') }}</span>
                            </label>

                            {{-- App icon · 192×192 — upload (replaces the old URL field). --}}
                            <div class="space-y-1.5 col-span-2 sm:col-span-1">
                                <span class="text-[11.5px] font-semibold block">{{ __('App icon') }} <span
                                        class="font-mono text-[10.5px] text-ink-500">{{ __('· recommended 192×192 px PNG') }}</span></span>
                                <div id="pwa-icon-192-preview"
                                    class="rounded-2xl border border-paper-200 bg-paper-50 h-[110px] grid place-items-center overflow-hidden">
                                    @if ($pwa['icon_192'])
                                        <img src="{{ \Illuminate\Support\Str::startsWith($pwa['icon_192'], ['http://', 'https://', '/']) ? $pwa['icon_192'] : asset('storage/' . $pwa['icon_192']) }}"
                                            alt="{{ __('App icon 192') }}" class="max-h-20 max-w-20 object-contain">
                                    @else
                                        <span
                                            class="text-[11px] font-mono text-ink-400">{{ __('No icon yet') }}</span>
                                    @endif
                                </div>
                                <input type="file" name="pwa_icon_192_file" accept=".png,.webp"
                                    data-preview-target="pwa-icon-192-preview"
                                    data-preview-class="max-h-20 max-w-20 object-contain"
                                    class="block w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[11.5px] file:font-medium file:cursor-pointer">
                                <div class="text-[10.5px] font-mono text-ink-500">
                                    {{ __('Square PNG, 192×192 px. Used as the home-screen app icon.') }}</div>
                            </div>

                            {{-- App icon · 512×512 — upload. --}}
                            <div class="space-y-1.5 col-span-2 sm:col-span-1">
                                <span class="text-[11.5px] font-semibold block">{{ __('Large icon / splash') }} <span
                                        class="font-mono text-[10.5px] text-ink-500">{{ __('· recommended 512×512 px PNG') }}</span></span>
                                <div id="pwa-icon-512-preview"
                                    class="rounded-2xl border border-paper-200 bg-paper-50 h-[110px] grid place-items-center overflow-hidden">
                                    @if ($pwa['icon_512'])
                                        <img src="{{ \Illuminate\Support\Str::startsWith($pwa['icon_512'], ['http://', 'https://', '/']) ? $pwa['icon_512'] : asset('storage/' . $pwa['icon_512']) }}"
                                            alt="{{ __('App icon 512') }}" class="max-h-20 max-w-20 object-contain">
                                    @else
                                        <span
                                            class="text-[11px] font-mono text-ink-400">{{ __('No icon yet') }}</span>
                                    @endif
                                </div>
                                <input type="file" name="pwa_icon_512_file" accept=".png,.webp"
                                    data-preview-target="pwa-icon-512-preview"
                                    data-preview-class="max-h-20 max-w-20 object-contain"
                                    class="block w-full text-[12px] file:mr-3 file:px-3 file:py-1.5 file:rounded-full file:border-0 file:bg-wa-deep file:text-paper-0 file:text-[11.5px] file:font-medium file:cursor-pointer">
                                <div class="text-[10.5px] font-mono text-ink-500">
                                    {{ __('Square PNG, 512×512 px. Used for the install splash + high-DPI screens.') }}
                                </div>
                            </div>

                            <label
                                class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between col-span-2 cursor-pointer">
                                <div>
                                    <div class="text-[13px] font-semibold">{{ __('Show install prompt') }}</div>
                                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                                        {{ __('Display the modal asking visitors to add the app to their home screen.') }}
                                    </div>
                                </div>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="pwa_install_prompt" value="1"
                                        @checked(old('pwa_install_prompt', $pwa['install_prompt'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>
                            <label
                                class="rounded-2xl border border-paper-200 p-4 flex items-center justify-between col-span-2 cursor-pointer">
                                <div>
                                    <div class="text-[13px] font-semibold">{{ __('Offline cache (service worker)') }}
                                    </div>
                                    <div class="text-[11.5px] text-ink-500 mt-0.5">
                                        {{ __('Cache assets for offline access.') }}</div>
                                </div>
                                <span class="relative inline-flex items-center w-10 h-5 shrink-0">
                                    <input type="checkbox" name="pwa_offline_enabled" value="1"
                                        @checked(old('pwa_offline_enabled', $pwa['offline_enabled'])) class="sr-only peer">
                                    <span
                                        class="absolute inset-0 bg-paper-200 peer-checked:bg-wa-deep rounded-full transition"></span>
                                    <span
                                        class="absolute top-0.5 left-0.5 w-4 h-4 bg-paper-0 rounded-full transition peer-checked:translate-x-5"></span>
                                </span>
                            </label>

                            <div
                                class="col-span-2 rounded-xl border border-paper-200 bg-paper-50/60 px-4 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[12px] font-mono text-ink-500">{{ __('Live manifest URL') }}
                                    </div>
                                    <a href="{{ url('/manifest.json') }}" target="_blank"
                                        class="text-[12.5px] font-mono text-wa-deep hover:underline truncate block">{{ url('/manifest.json') }}</a>
                                </div>
                                <a href="{{ url('/manifest.json') }}" target="_blank" rel="noopener"
                                    class="px-3 py-1.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[11.5px] font-semibold shrink-0">{{ __('View JSON') }}</a>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-[88px]">
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Related settings') }}</div>
                            <h3 class="font-serif text-[18px] leading-tight mt-0.5">{{ __('Compliance & tracking') }}
                            </h3>
                        </div>
                        <div class="p-2">
                            <a href="{{ url('/admin/settings/privacy') }}"
                                class="block px-3 py-2.5 rounded-xl hover:bg-paper-50 text-[13px]">
                                <div class="font-semibold">{{ __('Privacy, GDPR & ADA') }} →</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('Cookie banner, policies, accessibility flags.') }}</div>
                            </a>
                            <a href="{{ url('/admin/settings/analytics') }}"
                                class="block px-3 py-2.5 rounded-xl hover:bg-paper-50 text-[13px]">
                                <div class="font-semibold">{{ __('Analytics integrations') }} →</div>
                                <div class="text-[11.5px] text-ink-500 mt-0.5">
                                    {{ __('GA4, GTM, Meta Pixel, Clarity, and more.') }}</div>
                            </a>
                        </div>
                    </div>

                    <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
                        <div class="px-4 py-3 border-b border-paper-200">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Icon prep') }}</div>
                            <h3 class="font-serif text-[16px] leading-tight mt-0.5">{{ __('Manifest fields') }}</h3>
                        </div>
                        <div class="p-4 space-y-2 text-[11.5px] text-ink-700">
                            <div><span class="font-semibold">{{ __('Icons') }}:</span>
                                {{ __('square PNG, no transparency. Generate 192 + 512 from the same master.') }}</div>
                            <div><span class="font-semibold">{{ __('Display') }}:</span> <span
                                    class="font-mono">{{ __('standalone') }}</span>
                                {{ __('hides the browser chrome — feels like a native app.') }}</div>
                            <div><span class="font-semibold">{{ __('Theme color') }}:</span>
                                {{ __('tints the Android system UI.') }}</div>
                        </div>
                    </div>
                </aside>
            </section>
        </main>

    </form>

</x-layouts.admin>
