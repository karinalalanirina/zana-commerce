<x-layouts.admin :title="__('Integrations')" admin-key="integrations">


    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-6 lg:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 3l3 3-3 3" />
            </svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Integrations') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-6 lg:px-7 py-7 space-y-5">

        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Admin - Platform') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[40px] leading-[1.0]">{{ __('Store') }}
                    <span class="italic text-wa-deep">{{ __('connections') }}</span>.</h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">
                    {{ __('Every Shopify and WooCommerce store connected by a workspace. Force re-auth or disable from here.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ url('/admin/settings/shopify') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Shopify config') }}</a>
                <a href="{{ url('/admin/settings/woocommerce') }}"
                    class="px-4 py-2 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('WooCommerce config') }}</a>
            </div>
        </div>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Connected stores') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2">218</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('142 Shopify · 76 Woo') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Healthy') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-wa-deep">196</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('syncing') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Reauth needed') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-amber">14</div>
                <div class="text-[11px] text-accent-amber mt-2">{{ __('scope expired') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Disconnected') }}</div>
                <div class="font-serif text-[31px] leading-none mt-2 text-accent-coral">8</div>
                <div class="text-[11px] text-accent-coral mt-2">{{ __('last 24h') }}</div>
            </div>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl p-2 flex flex-wrap items-center gap-1 shadow-card">
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full bg-wa-deep text-paper-0 text-[12.5px] font-semibold">{{ __('All') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Shopify') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('WooCommerce') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Reauth needed') }}</button>
            <button
                class="inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[12.5px] font-semibold text-ink-600 hover:bg-paper-50">{{ __('Disconnected') }}</button>
        </section>

        <section class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-[12.5px] min-w-[760px]">
                <thead class="bg-paper-50/60 text-ink-500 border-b border-paper-200">
                    <tr>
                        <th class="text-left px-5 py-2.5 w-[18px]"></th>
                        <th class="text-left px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th class="text-left px-3 py-2.5 w-[150px] font-medium">{{ __('Workspace') }}</th>
                        <th class="text-left px-3 py-2.5 w-[110px] font-medium">{{ __('Provider') }}</th>
                        <th class="text-right px-3 py-2.5 w-[100px] font-medium">{{ __('Products') }}</th>
                        <th class="text-left px-3 py-2.5 w-[120px] font-medium">{{ __('Last sync') }}</th>
                        <th class="text-right pl-3 pr-5 py-2.5 w-[120px] font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200">
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('bloomly-store.myshopify.com') }}</div>
                            <div class="text-[10.5px] text-ink-500">
                                {{ __('read_products, read_orders, read_customers') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Bloomly') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Shopify') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">2,184</td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('12m ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-accent-amber"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('store.fitkart.in') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('SSL ok · permalinks ok') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('FitKart') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('WooCommerce') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">1,308</td>
                        <td class="px-3 py-3 font-mono text-[11px] text-accent-amber">{{ __('scope expired') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Reauth') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('quickshop-india.myshopify.com') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('read_products, write_orders') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('QuickShop') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Shopify') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">948</td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('7m ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-accent-coral"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('designhub.io') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('webhook 503 · disconnected 4h ago') }}
                            </div>
                        </td>
                        <td class="px-3 py-3">{{ __('DesignHub') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('WooCommerce') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">2,840</td>
                        <td class="px-3 py-3 font-mono text-[11px] text-accent-coral">{{ __('4h ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Reconnect') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('northstar-clinic.myshopify.com') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('read_customers, write_draft_orders') }}
                            </div>
                        </td>
                        <td class="px-3 py-3">{{ __('Northstar') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep text-[10px] font-semibold">{{ __('Shopify') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">312</td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('3m ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-paper-50/60">
                        <td class="px-5 py-3"><span class="block w-2 h-2 rounded-full bg-wa-green"></span></td>
                        <td class="px-3 py-3">
                            <div class="font-semibold">{{ __('store.lumina.app') }}</div>
                            <div class="text-[10.5px] text-ink-500">{{ __('SSL ok · permalinks ok') }}</div>
                        </td>
                        <td class="px-3 py-3">{{ __('Lumina') }}</td>
                        <td class="px-3 py-3"><span
                                class="px-2 py-0.5 rounded-full bg-[#F3E9FF] text-[#5B3D8A] text-[10px] font-semibold">{{ __('WooCommerce') }}</span>
                        </td>
                        <td class="px-3 py-3 text-right font-mono">624</td>
                        <td class="px-3 py-3 font-mono text-[11px]">{{ __('42m ago') }}</td>
                        <td class="pl-3 pr-5 py-3 text-right"><button
                                class="rounded-full border border-paper-200 px-3 py-1 text-[11px]">{{ __('Open') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>

    </main>

</x-layouts.admin>
