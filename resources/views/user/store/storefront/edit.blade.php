<x-layouts.user :title="__('Storefront')" nav-key="connect" page="user-store-storefront-edit">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $cnameTarget = config('storefront.cname_target', parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $settings = $sf->settings_json ?? [];
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'storefront', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 max-w-4xl">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            {{ __('Store / Storefront') }}</div>
                        <h1 class="font-serif text-[26px] sm:text-[30px] lg:text-[34px] leading-tight tracking-[-0.02em]">{{ __('Storefront') }} <span
                                class="italic text-wa-deep">{{ __('design') }}</span></h1>
                    </div>
                    <a href="{{ $sf->public_url }}" target="_blank"
                        class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold inline-flex items-center gap-2">View
                        live →</a>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('user.store.storefront.update') }}"
                    class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-5">
                    @csrf @method('PUT')

                    @if ($errors->any())
                        <div
                            class="rounded-lg border border-accent-coral/40 bg-accent-coral/10 px-3 py-2 text-[12px] text-[#A1431F]">
                            @foreach ($errors->all() as $e)
                                <div>{{ $e }}</div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Theme picker -->
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Theme') }}</div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach ($themes as $key => $name)
                                @php $active = old('theme_key', $sf->theme_key) === $key; @endphp
                                <label class="cursor-pointer block">
                                    <input type="radio" name="theme_key" value="{{ $key }}"
                                        @checked($active) class="sr-only peer" />
                                    <div
                                        class="border peer-checked:border-wa-deep peer-checked:ring-2 peer-checked:ring-wa-deep/15 rounded-xl overflow-hidden transition">
                                        <div class="aspect-[4/3] grid place-items-center text-paper-0 text-[12px] font-mono uppercase tracking-wider"
                                            style="background:{{ ['aurora' => 'linear-gradient(135deg,#FBFAF6,#E5DFD0)', 'meridian' => 'linear-gradient(135deg,#0B1F1C,#3A5A55)', 'verdure' => 'linear-gradient(135deg,#D5E8C6,#8AAB7B)', 'bazaar' => 'linear-gradient(135deg,#F4A261,#E76F51)', 'noir' => 'linear-gradient(135deg,#0a0a0a,#262626)', 'kraft' => 'linear-gradient(135deg,#D4B896,#A6845A)', 'mercato' => 'linear-gradient(135deg,#C8553D,#F4D35E)', 'studio' => 'linear-gradient(135deg,#264653,#2A9D8F)'][$key] ?? '#075E54' }}">
                                            <span class="font-serif text-[18px] capitalize"
                                                style="text-shadow: 0 1px 2px rgba(0,0,0,.2)">{{ $key }}</span>
                                        </div>
                                        <div class="px-3 py-2 text-[11.5px] text-ink-700 bg-paper-0">
                                            {{ $name }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Slug + custom domain -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-paper-200">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Storefront slug') }}</span>
                            <input type="text" name="slug" required pattern="[a-z0-9-]+" maxlength="64"
                                value="{{ old('slug', $sf->slug) }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Your store will be at') }} <span
                                    class="font-mono">{{ $sf->slug }}.{{ config('storefront.subdomain_host') }}</span></span>
                        </label>
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Custom domain') }} <span
                                    class="text-ink-500 font-normal">(optional)</span></span>
                            <input type="text" name="custom_domain" maxlength="191"
                                value="{{ old('custom_domain', $sf->custom_domain) }}"
                                placeholder="{{ __('shop.yourbiz.com') }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            @if ($sf->custom_domain)
                                <div
                                    class="mt-2 text-[11px] {{ $sf->custom_domain_verified ? 'text-wa-deep' : 'text-accent-amber' }}">
                                    @if ($sf->custom_domain_verified)
                                        ✓ Verified — your domain is live
                                    @else
                                        ⚠ Add this CNAME at your DNS provider:
                                    @endif
                                </div>
                                @if (!$sf->custom_domain_verified)
                                    <div
                                        class="mt-1 px-2 py-1.5 bg-paper-50 rounded-lg font-mono text-[10.5px] text-ink-700">
                                        {{ __('CNAME') }} <strong>{{ explode('.', $sf->custom_domain)[0] }}</strong>
                                        → <strong>{{ $cnameTarget }}</strong></div>
                                    <button type="button" onclick="verifyDomain()"
                                        class="mt-2 px-3 py-1 border border-paper-200 rounded-full text-[11.5px] hover:bg-paper-50">{{ __('Verify now') }}</button>
                                    <span id="dns-result" class="text-[11px] ml-2"></span>
                                @endif
                            @endif
                        </label>
                    </div>

                    <!-- Brand -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-paper-200">
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Logo URL') }}</span>
                            <input type="url" name="logo_url" maxlength="1024"
                                value="{{ old('logo_url', $settings['logo_url'] ?? '') }}" placeholder="https://..."
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </label>
                        <label class="block">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Brand color (hex)') }}</span>
                            <div class="flex gap-2 mt-1">
                                <input type="color" name="brand_color"
                                    value="{{ old('brand_color', $settings['brand_color'] ?? '#075E54') }}"
                                    class="w-10 h-10 rounded-lg border border-paper-200" />
                                <input type="text" pattern="#[0-9a-fA-F]{6}" maxlength="7"
                                    value="{{ old('brand_color', $settings['brand_color'] ?? '#075E54') }}"
                                    oninput="this.previousElementSibling.value=this.value"
                                    class="flex-1 px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </div>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Hero text') }}</span>
                            <input type="text" name="hero_text" maxlength="280"
                                value="{{ old('hero_text', $settings['hero_text'] ?? '') }}"
                                placeholder="{{ __('Welcome to our store') }}"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Footer text') }}</span>
                            <input type="text" name="footer_text" maxlength="280"
                                value="{{ old('footer_text', $settings['footer_text'] ?? '') }}"
                                placeholder="© 2026 Your store"
                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                        </label>
                    </div>

                    {{-- Currency --}}
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Currency') }}</div>
                        <label class="block max-w-xs">
                            <select name="currency_code"
                                class="w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                @foreach ([
        'INR' => '₹ INR · Indian Rupee',
        'USD' => '$ USD · US Dollar',
        'EUR' => '€ EUR · Euro',
        'GBP' => '£ GBP · British Pound',
        'AED' => 'د.إ AED · UAE Dirham',
        'KES' => 'KSh KES · Kenyan Shilling',
        'NGN' => '₦ NGN · Nigerian Naira',
        'ZAR' => 'R ZAR · South African Rand',
        'BRL' => 'R$ BRL · Brazilian Real',
        'MXN' => '$ MXN · Mexican Peso',
        'CRC' => '₡ CRC · Costa Rican Colón',
        'PHP' => '₱ PHP · Philippine Peso',
        'IDR' => 'Rp IDR · Indonesian Rupiah',
        'SGD' => 'S$ SGD · Singapore Dollar',
        'MYR' => 'RM MYR · Malaysian Ringgit',
        'THB' => '฿ THB · Thai Baht',
        'VND' => '₫ VND · Vietnamese Đồng',
        'EGP' => 'E£ EGP · Egyptian Pound',
        'PKR' => '₨ PKR · Pakistani Rupee',
        'BDT' => '৳ BDT · Bangladeshi Taka',
        'LKR' => 'Rs LKR · Sri Lankan Rupee',
    ] as $c => $l)
                                    <option value="{{ $c }}" @selected(old('currency_code', $sf->currency_code ?? \App\Support\ZanaStorefrontCurrency::code($sf, $sf->workspace)) === $c)>
                                        {{ $l }}</option>
                                @endforeach
                            </select>
                            <span
                                class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Default for new products + the symbol shown in the cart drawer.') }}</span>
                        </label>
                    </div>

                    {{-- Shipping --}}
                    @php $shipping = $sf->shipping_json ?? []; @endphp
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Shipping') }}</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700">{{ __('Flat shipping fee') }}</span>
                                <input type="number" name="shipping_flat" min="0" step="0.01"
                                    value="{{ old('shipping_flat', isset($shipping['flat_minor']) ? number_format($shipping['flat_minor'] / 100, 2, '.', '') : '') }}"
                                    placeholder="0"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Added to every order. Blank or 0 = free.') }}</span>
                            </label>
                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700">{{ __('Free shipping above') }}</span>
                                <input type="number" name="shipping_free_above" min="0" step="0.01"
                                    value="{{ old('shipping_free_above', isset($shipping['free_above_minor']) ? number_format($shipping['free_above_minor'] / 100, 2, '.', '') : '') }}"
                                    placeholder="999"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Carts over this amount ship free.') }}</span>
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Shipping note') }} <span
                                        class="text-ink-500 font-normal">(optional)</span></span>
                                <input type="text" name="shipping_note" maxlength="160"
                                    value="{{ old('shipping_note', $shipping['note'] ?? '') }}"
                                    placeholder="2–5 business days · Tracked"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Shown in the cart and on the product page.') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- Payment --}}
                    @php $pay = $sf->payment_config_json ?? []; @endphp
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Merchant payment setup') }}</div>
                        <p class="text-[11.5px] text-ink-500 mb-3">
                            {{ __("Configure the Africa-first payment setup you want Zana operators to use: M-Pesa instructions, bank transfer details, a reusable payment link, and a default payment message. Buyers still pay manually unless you later connect an automated provider.") }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Provider') }}</span>
                                <select name="payment_provider" data-payment-provider
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    @foreach ([
        '' => 'Choose later',
        'manual_instructions' => 'Manual payment instructions',
        'external_link' => 'External payment link',
        'bank_transfer' => 'Bank transfer instructions',
        'cash_on_delivery' => 'Cash on delivery',
        'upi' => 'UPI (India only)',
        'razorpay_link' => 'Razorpay link (later / India-oriented)',
        'razorpay_api' => 'Razorpay auto-link (later / India-oriented)',
        'stripe_link' => 'Stripe payment link',
        'paypal_me' => 'PayPal.me handle',
    ] as $key => $label)
                                        <option value="{{ $key }}" @selected(old('payment_provider', $sf->payment_provider) === $key)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span
                                    class="text-[11.5px] font-semibold text-ink-700">{{ __('Primary payment detail') }}</span>
                                <input type="text" name="payment_handle" maxlength="255"
                                    value="{{ old('payment_handle', $pay['handle'] ?? '') }}"
                                    placeholder="e.g. https://paystack.com/pay/... or bank account name"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                                <span
                                    class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Use this for a pasted payment link, a bank transfer headline, or another manual payment pointer.') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('M-Pesa business name') }}</span>
                                <input type="text" name="mpesa_business_name" maxlength="120"
                                    value="{{ old('mpesa_business_name', $pay['mpesa_business_name'] ?? '') }}"
                                    placeholder="e.g. Zuri Beauty Store"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Accepted payment methods text') }}</span>
                                <input type="text" name="accepted_payment_methods_text" maxlength="255"
                                    value="{{ old('accepted_payment_methods_text', $pay['accepted_payment_methods_text'] ?? '') }}"
                                    placeholder="M-Pesa, Paystack link, Flutterwave link, bank transfer"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('M-Pesa Till number') }}</span>
                                <input type="text" name="mpesa_till_number" maxlength="64"
                                    value="{{ old('mpesa_till_number', $pay['mpesa_till_number'] ?? '') }}"
                                    placeholder="e.g. 123456"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('M-Pesa Paybill number') }}</span>
                                <input type="text" name="mpesa_paybill_number" maxlength="64"
                                    value="{{ old('mpesa_paybill_number', $pay['mpesa_paybill_number'] ?? '') }}"
                                    placeholder="e.g. 400200"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Payment reference / account format') }}</span>
                                <input type="text" name="payment_reference_format" maxlength="120"
                                    value="{{ old('payment_reference_format', $pay['payment_reference_format'] ?? '') }}"
                                    placeholder="e.g. ORDER-{order_id} or customer phone"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('External payment link') }}</span>
                                <input type="url" name="external_payment_link" maxlength="1024"
                                    value="{{ old('external_payment_link', $pay['external_payment_link'] ?? '') }}"
                                    placeholder="https://paystack.com/pay/your-link"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" />
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Bank transfer instructions') }}</span>
                                <textarea name="bank_transfer_instructions" rows="2" maxlength="500"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="Bank name, account number, account name, branch, or transfer note">{{ old('bank_transfer_instructions', $pay['bank_transfer_instructions'] ?? '') }}</textarea>
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Default payment instructions template') }}</span>
                                <textarea name="default_payment_instructions_template" rows="4" maxlength="1500"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10"
                                    placeholder="Hi {customer_name}, your order {order_id} is awaiting payment. Use M-Pesa Till {mpesa_till} or Paybill {mpesa_paybill}. Reference: {payment_reference}. Payment link: {external_payment_link}">{{ old('default_payment_instructions_template', $pay['default_payment_instructions_template'] ?? '') }}</textarea>
                                <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Available placeholders: {customer_name}, {order_id}, {order_total}, {business_name}, {mpesa_till}, {mpesa_paybill}, {payment_reference}, {external_payment_link}, {bank_transfer_instructions}, {accepted_payment_methods}.') }}</span>
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('24-hour fallback template for payment instructions') }}</span>
                                <select name="payment_instruction_template_id"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="">{{ __('Choose approved Meta template') }}</option>
                                    @foreach ($paymentFallbackTemplates as $template)
                                        <option value="{{ $template->id }}" @selected((int) old('payment_instruction_template_id', $pay['payment_instruction_template_id'] ?? 0) === (int) $template->id)>
                                            {{ $template->template_name }} @if($template->language) · {{ $template->language }} @endif @if($template->meta_category) · {{ strtoupper($template->meta_category) }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Used only when freeform WhatsApp sending is blocked by the 24-hour rule. Best practice: use an approved utility template whose first body variable can carry the full payment message.') }}</span>
                                <div class="mt-2 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2 text-[11.5px] text-ink-600">
                                    <div class="font-semibold text-ink-700">{{ __('Current state') }}: {{ $paymentTemplateReadiness['instruction']['label'] ?? __('Unknown') }}</div>
                                    <div class="mt-1">{{ $paymentTemplateReadiness['instruction']['notes'] ?? '' }}</div>
                                </div>
                            </label>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('24-hour fallback template for payment reminders') }}</span>
                                <select name="payment_reminder_template_id"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                                    <option value="">{{ __('Choose approved Meta template') }}</option>
                                    @foreach ($paymentFallbackTemplates as $template)
                                        <option value="{{ $template->id }}" @selected((int) old('payment_reminder_template_id', $pay['payment_reminder_template_id'] ?? 0) === (int) $template->id)>
                                            {{ $template->template_name }} @if($template->language) · {{ $template->language }} @endif @if($template->meta_category) · {{ strtoupper($template->meta_category) }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                <span class="text-[10.5px] text-ink-500 mt-1 block">{{ __('Use a reminder-safe approved template here if you want Zana to reopen the conversation compliantly instead of forcing operators to copy a reminder manually.') }}</span>
                                <div class="mt-2 rounded-xl border border-paper-200 bg-paper-50 px-3 py-2 text-[11.5px] text-ink-600">
                                    <div class="font-semibold text-ink-700">{{ __('Current state') }}: {{ $paymentTemplateReadiness['reminder']['label'] ?? __('Unknown') }}</div>
                                    <div class="mt-1">{{ $paymentTemplateReadiness['reminder']['notes'] ?? '' }}</div>
                                </div>
                            </label>
                        </div>

                        <div class="mt-4 rounded-2xl border border-paper-200 bg-paper-50/60 p-4">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Template guidance') }}</div>
                            <div class="mt-1 text-[12px] text-ink-600">
                                {{ $paymentTemplateReadiness['outside_24h_guidance'] ?? __('Outside the 24-hour window, payment sends may need an approved template or a manual copy fallback.') }}
                            </div>
                            <div class="mt-2 text-[11px] text-ink-500">
                                {{ __('Current workspace send path') }}: {{ $paymentTemplateReadiness['engine_label'] ?? __('Unknown') }}
                            </div>
                        </div>

                        @php
                            $hasPaystackSecret = !empty($sf->payment_config_json['paystack_secret_key']);
                        @endphp
                        <div class="mt-4 border border-paper-200 rounded-xl p-4 bg-paper-50/50">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Paystack order links') }}</div>
                            <div class="mt-1 text-[12px] text-ink-600">{{ __('Optional Africa-facing order-specific Paystack checkout links. Zana generates the link per order, then operators send it through the existing WhatsApp or copy fallback flow.') }}</div>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="inline-flex items-start gap-2 text-[12.5px] sm:col-span-2">
                                    <input type="hidden" name="paystack_enabled" value="0" />
                                    <input type="checkbox" name="paystack_enabled" value="1" @checked(old('paystack_enabled', $pay['paystack_enabled'] ?? false)) class="mt-0.5 rounded border-paper-200 text-wa-deep" />
                                    <span>
                                        <span class="font-semibold block">{{ __('Enable Paystack link generation for this merchant') }}</span>
                                        <span class="text-[10.5px] text-ink-500">{{ __('Keeps M-Pesa/manual and Daraja flows intact. This only adds a Paystack order-link option on the order page.') }}</span>
                                    </span>
                                </label>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Paystack public key') }}</span>
                                    <input type="text" name="paystack_public_key" maxlength="191"
                                        value="{{ old('paystack_public_key', $pay['paystack_public_key'] ?? '') }}"
                                        placeholder="pk_live_or_test_..."
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                </label>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Paystack secret key') }}</span>
                                    <input type="password" name="paystack_secret_key" maxlength="191" autocomplete="new-password"
                                        placeholder="{{ $hasPaystackSecret ? __('saved — leave blank to keep') : __('sk_live_or_test_...') }}"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                </label>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Reference prefix') }}</span>
                                    <input type="text" name="paystack_reference_prefix" maxlength="20"
                                        value="{{ old('paystack_reference_prefix', $pay['paystack_reference_prefix'] ?? 'ZANA') }}"
                                        placeholder="ZANA"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                </label>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Fallback customer email') }}</span>
                                    <input type="email" name="paystack_fallback_customer_email" maxlength="191"
                                        value="{{ old('paystack_fallback_customer_email', $pay['paystack_fallback_customer_email'] ?? '') }}"
                                        placeholder="payments@yourstore.com"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-wa-deep" />
                                </label>
                                <label class="block sm:col-span-2">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Redirect note') }}</span>
                                    <input type="text" name="paystack_redirect_note" maxlength="160"
                                        value="{{ old('paystack_redirect_note', $pay['paystack_redirect_note'] ?? '') }}"
                                        placeholder="{{ __('Customer returns to the order status page after checkout') }}"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-wa-deep" />
                                </label>
                            </div>
                            <div class="mt-3 rounded-xl border border-paper-200 bg-paper-0 px-3 py-3 text-[11.5px] text-ink-600">
                                <div class="font-semibold text-ink-700">{{ __('Current state') }}: {{ $paystackReadiness['label'] ?? __('Unknown') }}</div>
                                <div class="mt-1">{{ $paystackReadiness['notes'] ?? '' }}</div>
                            </div>
                        </div>

                        @if (config('zana.enable_daraja_sandbox'))
                            @php
                                $hasDarajaKey = !empty($sf->payment_config_json['daraja_consumer_key']);
                                $hasDarajaSecret = !empty($sf->payment_config_json['daraja_consumer_secret']);
                                $hasDarajaPasskey = !empty($sf->payment_config_json['daraja_passkey']);
                            @endphp
                            <div class="mt-4 border border-paper-200 rounded-xl p-4 bg-paper-50/50">
                                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Daraja sandbox (staging only)') }}</div>
                                <div class="mt-1 text-[12px] text-ink-600">{{ __('This scaffold is for staging validation only: STK initiation, callback receipt, order linkage, and duplicate handling. It is not a production rollout yet.') }}</div>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <label class="inline-flex items-start gap-2 text-[12.5px] sm:col-span-2">
                                        <input type="hidden" name="daraja_enabled" value="0" />
                                        <input type="checkbox" name="daraja_enabled" value="1" @checked(old('daraja_enabled', $pay['daraja_enabled'] ?? false)) class="mt-0.5 rounded border-paper-200 text-wa-deep" />
                                        <span>
                                            <span class="font-semibold block">{{ __('Enable Daraja sandbox for this merchant') }}</span>
                                            <span class="text-[10.5px] text-ink-500">{{ __('Keep this off outside staging/testing until sandbox validation succeeds.') }}</span>
                                        </span>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Environment') }}</span>
                                        <select name="daraja_environment" class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                            <option value="sandbox" @selected(old('daraja_environment', $pay['daraja_environment'] ?? 'sandbox') === 'sandbox')>{{ __('Sandbox') }}</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Business short code') }}</span>
                                        <input type="text" name="daraja_shortcode" maxlength="64"
                                            value="{{ old('daraja_shortcode', $pay['daraja_shortcode'] ?? '') }}"
                                            placeholder="174379"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Consumer key') }}</span>
                                        <input type="password" name="daraja_consumer_key" autocomplete="new-password"
                                            placeholder="{{ $hasDarajaKey ? __('saved — leave blank to keep') : __('sandbox consumer key') }}"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Consumer secret') }}</span>
                                        <input type="password" name="daraja_consumer_secret" autocomplete="new-password"
                                            placeholder="{{ $hasDarajaSecret ? __('saved — leave blank to keep') : __('sandbox consumer secret') }}"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Passkey') }}</span>
                                        <input type="password" name="daraja_passkey" autocomplete="new-password"
                                            placeholder="{{ $hasDarajaPasskey ? __('saved — leave blank to keep') : __('sandbox passkey') }}"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Transaction type') }}</span>
                                        <select name="daraja_transaction_type" class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                            <option value="CustomerPayBillOnline" @selected(old('daraja_transaction_type', $pay['daraja_transaction_type'] ?? 'CustomerPayBillOnline') === 'CustomerPayBillOnline')>{{ __('CustomerPayBillOnline') }}</option>
                                            <option value="CustomerBuyGoodsOnline" @selected(old('daraja_transaction_type', $pay['daraja_transaction_type'] ?? '') === 'CustomerBuyGoodsOnline')>{{ __('CustomerBuyGoodsOnline') }}</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Reference prefix') }}</span>
                                        <input type="text" name="daraja_reference_prefix" maxlength="20"
                                            value="{{ old('daraja_reference_prefix', $pay['daraja_reference_prefix'] ?? 'ORDER') }}"
                                            placeholder="ORDER"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="inline-flex items-start gap-2 text-[12.5px] sm:col-span-2">
                                        <input type="hidden" name="daraja_callback_enabled" value="0" />
                                        <input type="checkbox" name="daraja_callback_enabled" value="1" @checked(old('daraja_callback_enabled', $pay['daraja_callback_enabled'] ?? true)) class="mt-0.5 rounded border-paper-200 text-wa-deep" />
                                        <span>
                                            <span class="font-semibold block">{{ __('Receive sandbox callbacks') }}</span>
                                            <span class="text-[10.5px] text-ink-500">{{ __('Enable this only when your staging URL or tunnel is publicly reachable over HTTPS.') }}</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mt-3 rounded-xl border border-paper-200 bg-paper-0 px-3 py-3 text-[11.5px] text-ink-600">
                                    <div class="font-semibold text-ink-700">{{ __('Current state') }}: {{ $darajaReadiness['label'] ?? __('Unknown') }}</div>
                                    <div class="mt-1">{{ $darajaReadiness['notes'] ?? '' }}</div>
                                    <div class="mt-2 text-[11px] text-ink-500">{{ $darajaReadiness['phone_guidance'] ?? '' }}</div>
                                    @if (!empty($darajaReadiness['callback_url']))
                                        <div class="mt-2 text-[11px] text-ink-500">{{ __('Sandbox callback URL') }}: <code class="font-mono">{{ $darajaReadiness['callback_url'] }}</code></div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="mt-4 border border-paper-200 rounded-xl p-4 bg-paper-50/50">
                                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Daraja STK testing') }}</div>
                                <div class="mt-1 text-[12px] text-ink-600">{{ __('This staging-only Daraja section is currently hidden because the global Zana Daraja sandbox feature flag is off.') }}</div>
                                <div class="mt-2 text-[11px] text-ink-500">{{ __('Enable ZANA_ENABLE_DARAJA_SANDBOX=true, clear config cache, then return here to configure shortcode, credentials, callback behavior, and STK settings safely.') }}</div>
                            </div>
                        @endif

                        {{-- Razorpay API keys — only used when provider = "Razorpay (auto-generate)".
 Lets the store mint a real payment link per order + auto-mark paid via webhook.
 Secrets are encrypted at rest and never echoed back. --}}
                        @php
                            $hasRzpSecret = !empty($sf->payment_config_json['key_secret']);
                            $hasRzpWh = !empty($sf->payment_config_json['webhook_secret']);
                        @endphp
                        <div class="mt-3 border border-paper-200 rounded-xl p-3 bg-paper-50/50">
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                                {{ __('Razorpay API (later / optional)') }}</div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="block"><span
                                        class="text-[11px] text-ink-700">{{ __('Key ID') }}</span>
                                    <input type="text" name="rzp_key_id"
                                        value="{{ old('rzp_key_id', $sf->payment_config_json['key_id'] ?? '') }}"
                                        placeholder="rzp_live_… / rzp_test_…"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep"></label>
                                <label class="block"><span
                                        class="text-[11px] text-ink-700">{{ __('Key secret') }}</span>
                                    <input type="password" name="rzp_key_secret" autocomplete="new-password"
                                        placeholder="{{ $hasRzpSecret ? __('saved — leave blank to keep') : '' }}"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep"></label>
                                <label class="block"><span
                                        class="text-[11px] text-ink-700">{{ __('Webhook secret') }}</span>
                                    <input type="password" name="rzp_webhook_secret" autocomplete="new-password"
                                        placeholder="{{ $hasRzpWh ? __('saved — leave blank to keep') : '' }}"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] font-mono focus:outline-none focus:border-wa-deep"></label>
                            </div>
                            <span
                                class="text-[10.5px] text-ink-500 mt-2 block">{{ __('Keep this only if you plan to use the India/Razorpay auto-link path later. In Razorpay dashboard, set the webhook URL to') }}
                                <code class="font-mono">{{ url('/webhooks/storefront-pay') }}</code>
                                {{ __('for the payment_link.paid event.') }}</span>
                        </div>
                    </div>

                    {{-- Abandoned-cart recovery (S3) --}}
                    @php $rc = is_array($sf->settings_json) ? $sf->settings_json : []; @endphp
                    <div class="pt-3 border-t border-paper-200">
                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-2">
                            {{ __('Abandoned-cart recovery') }}</div>
                        <label class="inline-flex items-start gap-2 text-[12.5px]">
                            <input type="hidden" name="cart_recovery_enabled" value="0" />
                            <input type="checkbox" name="cart_recovery_enabled" value="1"
                                @checked(old('cart_recovery_enabled', $rc['cart_recovery_enabled'] ?? false)) class="mt-0.5 rounded border-paper-200 text-wa-deep" />
                            <span><span
                                    class="font-semibold block">{{ __('Send a WhatsApp nudge to customers who start checkout but don\'t finish') }}</span>
                                <span
                                    class="text-[10.5px] text-ink-500">{{ __('Captures the phone at checkout; the nudge auto-cancels if they order. Needs a connected device.') }}</span></span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-3">
                            <label class="block"><span
                                    class="text-[11px] text-ink-700">{{ __('Delay (minutes)') }}</span>
                                <input type="number" name="cart_recovery_delay_min" min="5" max="1440"
                                    value="{{ old('cart_recovery_delay_min', $rc['cart_recovery_delay_min'] ?? 30) }}"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-wa-deep"></label>
                            <label class="block md:col-span-3"><span
                                    class="text-[11px] text-ink-700">{{ __('Message') }} <span
                                        class="text-ink-400">{{ __('(blank = default; {name} {shop} {url} {total})') }}</span></span>
                                <input type="text" name="cart_recovery_message" maxlength="1024"
                                    value="{{ old('cart_recovery_message', $rc['cart_recovery_message'] ?? '') }}"
                                    placeholder="Hi {name}! You left items in your cart at {shop}. Finish here: {url}"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] focus:outline-none focus:border-wa-deep"></label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-paper-200">
                        <label class="inline-flex items-center gap-2 text-[12.5px]">
                            <input type="hidden" name="enabled" value="0" />
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $sf->enabled))
                                class="rounded border-paper-200 text-wa-deep" />
                            Storefront live (unchecking takes it offline)
                        </label>
                        <button type="submit"
                            class="px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save') }}</button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <script>
        async function verifyDomain() {
            const r = await fetch(@json(route('user.store.storefront.verify')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    Accept: 'application/json'
                }
            });
            const j = await r.json();
            const el = document.getElementById('dns-result');
            el.textContent = j.message || (j.ok ? 'Verified ✓' : 'Not verified');
            el.className = 'text-[11px] ml-2 ' + (j.ok ? 'text-wa-deep' : 'text-accent-coral');
            if (j.ok) setTimeout(() => location.reload(), 800);
        }
    </script>
</x-layouts.user>
