<x-layouts.guest :title="__('Pick a plan / Step 3')" page="auth-register-step3">
    @php $__brandName = (string) brand_name(); @endphp

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
                                <path d="M2 8l6-5 6 5M3.5 7v6h9V7" />
                                <path d="M6.5 13V9.5h3V13" />
                            </svg>
                        </span>
                        <div>
                            <div class="text-[14px] font-semibold leading-tight">{{ __('Your plan, your allowance') }}
                            </div>
                            <div class="text-[12px] text-paper-0/75 leading-snug mt-1">
                                {{ __('Every plan includes a monthly message allowance. Sends are free until you reach it.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-paper-0/70 mb-3">
                    {{ __('Plan-first billing') }}</div>
                <h1 class="font-serif text-[42px] leading-[1.05] tracking-[-0.01em]">{{ __('Pick a plan,') }} <span
                        class="italic text-wa-green">{{ __('start sending') }}</span>.</h1>
                <p class="mt-3 text-[13px] text-paper-0/85 leading-relaxed">
                    {{ __('Choose the plan that fits your team. Your monthly message allowance covers your sends — wallet credits only kick in for overflow while your plan is active. After the plan ends, sending pauses until you renew.') }}
                </p>

                <!-- How billing works -->
                <div class="grid grid-cols-2 gap-3 mt-5">
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M2 4h12v8H2zM5 4v8M11 4v8" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Monthly allowance') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Your plan includes a set number of messages every month — free to send.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <circle cx="8" cy="8" r="5.5" />
                                    <path d="M8 5v3l2 2" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Stays active') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('The allowance applies until your plan end date — then top-up credits take over.') }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <rect x="2" y="4" width="12" height="9" rx="1.5" />
                                    <circle cx="11" cy="9" r="1" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Wallet for overflow') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('Past the allowance, 1 wallet credit = 1 message. No surprise lock-outs.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="w-7 h-7 rounded-lg bg-wa-green/25 text-wa-green grid place-items-center"><svg
                                    viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path d="M3 8a5 5 0 0 1 9-3M13 8a5 5 0 0 1-9 3M12 2v3h-3M4 14v-3h3" />
                                </svg></span>
                            <div class="text-[12.5px] font-semibold">{{ __('Auto-refund') }}</div>
                        </div>
                        <div class="text-[11px] text-paper-0/70 leading-snug">
                            {{ __('If WhatsApp rejects a send, the credit goes back to your wallet.') }}</div>
                    </div>
                </div>

                <div class="rounded-2xl bg-paper-0/8 border border-paper-0/15 backdrop-blur-sm p-4 mt-4">
                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-paper-0/70 mb-2">
                        {{ __('How billing works') }}</div>
                    @php $cpm = max(1, (int) \App\Models\SystemSetting::get('credits_per_message', 1)); @endphp
                    <div class="text-[11.5px] text-paper-0/85 leading-relaxed">
                        {{ __('Your plan includes a monthly message allowance until your plan end date. When the monthly allowance is used up while your plan is active, extra sends use wallet credits at') }}
                        <span class="font-semibold">{{ $cpm }}
                            {{ $cpm === 1 ? __('credit') : __('credits') }}/{{ __('message') }}</span>.
                        {{ __('After your plan ends, sending pauses until you renew.') }}</div>
                </div>
            </div>

            <div class="relative z-10 text-[11px] text-paper-0/60 font-mono mt-6 text-right">2026 {{ $__brandName }} /
                Mumbai, India</div>
        </aside>

        <!-- RIGHT: form -->
        <main class="flex flex-col justify-center px-6 py-6 lg:px-10 overflow-y-auto">
            <div class="w-full max-w-[440px] mx-auto">

                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">
                    {{ __('Step 3 of 3 / Plan') }}</div>
                <h2 class="font-serif text-[30px] leading-tight tracking-[-0.01em]">{{ __('Pick a') }} <span
                        class="italic text-wa-deep">{{ __('plan') }}</span>.</h2>
                <p class="text-[12.5px] text-ink-600 mt-1.5">
                    {{ __('Each plan includes a monthly message allowance. Start free, or pick a plan to unlock more.') }}
                </p>

                <ol class="flex items-center gap-2 mt-3 mb-4 text-[10.5px] font-mono uppercase tracking-wider">
                    <li class="text-ink-500 flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center"><svg
                                viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg></span>Account</li>
                    <li class="w-4 h-px bg-wa-deep"></li>
                    <li class="text-ink-500 flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-wa-mint text-wa-deep grid place-items-center"><svg
                                viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M3 8l3 3 7-7" />
                            </svg></span>Workspace</li>
                    <li class="w-4 h-px bg-wa-deep"></li>
                    <li class="text-wa-deep flex items-center gap-1.5"><span
                            class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px]">3</span>Plan
                    </li>
                </ol>

                {{-- Billing-model explainer (matches the verified plan-first behaviour). --}}
                <div class="rounded-xl bg-wa-mint/40 border border-wa-green/30 p-3.5 mb-4">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="w-6 h-6 rounded-lg bg-wa-green/25 text-wa-deep grid place-items-center shrink-0 mt-0.5"><svg
                                viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                stroke-width="1.7">
                                <circle cx="8" cy="8" r="6" />
                                <path d="M8 5.5v.5M7.5 8h.5v3h.5" />
                            </svg></span>
                        <div class="text-[11.5px] text-ink-700 leading-relaxed">
                            @php $cpm = max(1, (int) \App\Models\SystemSetting::get('credits_per_message', 1)); @endphp
                            {{ __('Your plan includes a set number of messages/month until your plan end date. When the monthly allowance is used up while your plan is active, extra sends use wallet credits at') }}
                            <span class="font-semibold">{{ $cpm }}
                                {{ $cpm === 1 ? __('credit') : __('credits') }}/{{ __('message') }}</span>.
                            {{ __('After your plan ends, sending pauses until you renew.') }}
                        </div>
                    </div>
                </div>

                <!-- Plan list -->
                <div class="space-y-2.5 max-h-[440px] overflow-y-auto pr-1">
                    @forelse ($packages as $p)
                        @php
                            $isFree = $p->free || (float) $p->plan_amount === 0.0;
                            $isCustom = $p->is_custom_quote;
                            $hot = $p->is_highlighted;

                            // Mirror /account/plans price handling: convert to the active currency.
                            $amount = $p->plan_amount;
                            if ($p->currency && strtoupper((string) $p->currency) !== strtoupper((string) $currency)) {
                                $amount = \App\Support\FormatSettings::convert(
                                    $p->plan_amount,
                                    $p->currency,
                                    $currency,
                                );
                            }
                            // Prefer the admin-set offer price when present (the discounted rate).
                            $offer = $p->offer_price;
                            if (
                                $offer !== null &&
                                (float) $offer > 0 &&
                                $p->currency &&
                                strtoupper((string) $p->currency) !== strtoupper((string) $currency)
                            ) {
                                $offer = \App\Support\FormatSettings::convert($p->offer_price, $p->currency, $currency);
                            }
                            $hasOffer =
                                !$isFree &&
                                !$isCustom &&
                                $offer !== null &&
                                (float) $offer > 0 &&
                                (float) $offer < (float) $amount;
                            $priceHuman = $isCustom
                                ? __('Custom')
                                : ($isFree
                                    ? __('Free')
                                    : \App\Support\FormatSettings::currency($hasOffer ? $offer : $amount));
                            $wasHuman = $hasOffer ? \App\Support\FormatSettings::currency($amount) : null;

                            // Monthly message allowance: 0 / null = Unlimited.
                            $msgLimit = (int) ($p->monthly_messages_limit ?? 0);
                            $msgHuman =
                                $msgLimit <= 0 ? __('Unlimited') : number_format($msgLimit) . ' ' . __('messages');

                            // Billing period — "/ month" or "/ 12 months" etc.
                            $unit = trim((string) $p->plan_unit);
                            $duration = (int) ($p->plan_duration ?? 0);
                            $periodHuman =
                                $isFree || $isCustom
                                    ? null
                                    : trim(
                                        ($duration > 1 ? $duration . ' ' : '') .
                                            ($unit !== ''
                                                ? \Illuminate\Support\Str::plural($unit, max(1, $duration))
                                                : __('month')),
                                    );

                            // A few headline capability flags to surface on the card.
                            $flagMap = [
                                'access_analytics' => __('Analytics'),
                                'autoflow' => __('Automation'),
                                'access_ai_agents' => __('AI assistants'),
                                'integration_woocommerce' => __('WooCommerce'),
                                'integration_shopify' => __('Shopify'),
                                'access_waba_calling' => __('WhatsApp calling'),
                                'remove_branding' => __('White-label'),
                            ];
                            $flags = [];
                            foreach ($flagMap as $col => $label) {
                                if (!empty($p->$col)) {
                                    $flags[] = $label;
                                }
                                if (count($flags) >= 3) {
                                    break;
                                }
                            }

                            $borderCls = $hot ? 'border-wa-deep ring-2 ring-wa-deep/15' : 'border-paper-200';
                            $bgCls = $hot ? 'bg-wa-mint/30' : 'bg-paper-0';

                            // PLAN checkout — same route /account/plans uses. Free / custom plans
                            // route to the free start / sales, never to a paid checkout.
                            if ($isFree) {
                                $href = route('register.plan.skip');
                            } elseif ($isCustom) {
                                $href = url('/support');
                            } else {
                                $href = route('user.checkout.show', $p->id);
                            }
                        @endphp
                        <a href="{{ $href }}"
                            class="block border {{ $borderCls }} {{ $bgCls }} rounded-xl p-3.5 hover:border-wa-deep transition relative">
                            @if ($hot)
                                <span
                                    class="absolute -top-2 left-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-deep text-paper-0 text-[10px] font-mono"><span
                                        class="w-1.5 h-1.5 rounded-full bg-wa-green"></span>{{ __('MOST POPULAR') }}</span>
                            @endif
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-serif text-[18px] leading-tight">{{ $p->pname }}</div>
                                    @if ($p->subtitle)
                                        <div class="text-[11px] text-ink-500 leading-snug mt-0.5">{{ $p->subtitle }}
                                        </div>
                                    @endif
                                    <div
                                        class="text-[12px] text-wa-deep font-medium mt-1.5 inline-flex items-center gap-1.5">
                                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none"
                                            stroke="currentColor" stroke-width="1.6">
                                            <path d="M2 4h12v8H2zM5 4v8M11 4v8" />
                                        </svg>{{ $msgHuman }}{{ $msgLimit > 0 ? ' / ' . __('month') : '' }}
                                    </div>
                                    @if (!empty($flags))
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            @foreach ($flags as $f)
                                                <span
                                                    class="px-1.5 py-0.5 rounded bg-paper-100 text-ink-600 text-[10px] font-mono">{{ $f }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    @if ($wasHuman)
                                        <div class="text-[11px] text-ink-400 line-through leading-none">
                                            {!! $wasHuman !!}</div>
                                    @endif
                                    <div class="font-serif text-[22px] leading-none text-wa-deep mt-0.5">
                                        {!! $priceHuman !!}</div>
                                    @if ($periodHuman)
                                        <div class="text-[10.5px] text-ink-500 mt-1 font-mono">/ {{ $periodHuman }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div
                            class="bg-paper-50 border border-dashed border-paper-200 rounded-xl p-5 text-center text-[12.5px] text-ink-600">
                            {{ __('No plans configured yet. The admin can add some at') }} <span
                                class="font-mono">/admin/pricing</span>.
                            {{ __('You can still continue free below.') }}
                        </div>
                    @endforelse
                </div>

                <a href="{{ route('register.plan.skip') }}"
                    class="block w-full mt-4 px-4 py-2.5 rounded-full border border-paper-200 bg-paper-0 hover:bg-paper-50 text-ink-700 text-[13px] font-semibold text-center">
                    {{ __('Start free / skip for now') }}
                </a>

                <p class="text-[11.5px] text-ink-500 text-center mt-3">
                    {{ __('Need extra sends past your allowance?') }}
                    @if (!empty($creditPackages) && $creditPackages->isNotEmpty())
                        <a href="{{ route('user.checkout.credits.show', $creditPackages->first()->slug) }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Top up message credits') }}</a>
                    @else
                        <a href="{{ url('/account?tab=wallet') }}"
                            class="text-wa-deep font-semibold hover:underline">{{ __('Top up message credits') }}</a>
                    @endif
                </p>
            </div>
        </main>

    </div>

</x-layouts.guest>
