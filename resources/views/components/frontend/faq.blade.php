@props([
    /** Eyebrow above the headline. */
    'kicker' => 'FAQ',
    /** Big serif headline (HTML allowed for italic spans). */
    'headline' => null,
    /** Subtitle paragraph below the headline. */
    'subtitle' => null,
    /**
     * Items: array of ['q' => string, 'a' => string, 'open' => bool?].
     * If empty, a sensible default set ships so the component is usable
     * by any page without configuration.
     */
    'items' => null,
])

@php
    // Was a page-specific item set passed in (features / pricing)? Those
    // are editorial content owned by the page's Blade, so we do NOT mark
// them inline-editable — otherwise an edit would save under a shared
// faq.* key and not display. Only the default (home) set is editable.
$itemsAreCustom = $items !== null;

$kicker = fcp('faq.kicker_text', $kicker);
$headline = fcp('faq.headline', $headline ?? 'Frequently <span class="italic text-wa-deep">asked.</span>');
$subtitle = fcp(
    'faq.subtitle',
    $subtitle ?? __('Still unsure? Email :email — a real human replies inside 4 hours.', ['email' => brand_email('support')]),
);
$items = $items ?? [
    [
        'q' => fcp('faq.faq1_q', __('Do I need a WhatsApp Business API account to start?')),
        'a' => fcp(
            'faq.faq1_a',
            __(
                'No — :brand can provision a WABA on your behalf via Meta\'s embedded signup. If you already have one, connect it directly. Twilio and Unofficial API QR-pair are also supported.',
                    ['brand' => brand_name()],
                ),
            ),
            'open' => true,
        ],
        [
            'q' => fcp('faq.faq2_q', __('How long does template approval take?')),
            'a' => fcp('faq.faq2_a', __('Median 18 minutes. We pre-validate so the rejection rate stays under 4%.')),
        ],
        [
            'q' => fcp('faq.faq3_q', __('Can I migrate from AiSensy, Wati, Interakt, Gupshup?')),
            'a' => fcp(
                'faq.faq3_a',
                __('Yes — one-click importers for all four, plus free white-glove migration on Pro & Scale.'),
            ),
        ],
        [
            'q' => fcp('faq.faq4_q', __('What payment gateways are supported?')),
            'a' => fcp(
                'faq.faq4_a',
                __('22 gateways including Razorpay, Stripe, PayPal, Paystack, Flutterwave, Instamojo.'),
            ),
        ],
        [
            'q' => fcp('faq.faq5_q', __('Where is my data stored?')),
            'a' => fcp(
                'faq.faq5_a',
                __('SOC 2 Type II, ISO 27001, GDPR, HIPAA-eligible. EU, US, or India residency on Scale.'),
            ),
        ],
    ];
@endphp

<section class="bg-paper-50 hairline-t hairline-b" data-fc-section="faq">
    <div class="max-w-[1080px] mx-auto px-4 sm:px-6 lg:px-7 py-28">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-12">
            <div class="col-span-12 lg:col-span-4">
                <div class="badge-num">— <span data-fc="{{ fc_skey('faq.kicker_text') }}">{{ $kicker }}</span></div>
                <h2 class="serif text-[40px] sm:text-[52px] lg:text-[64px] leading-[0.95] mt-4" data-fc="{{ fc_skey('faq.headline') }}">
                    {!! $headline !!}</h2>
                <p class="text-[13px] text-ink-600 mt-3" data-fc="{{ fc_skey('faq.subtitle') }}">{{ $subtitle }}</p>
            </div>

            <div class="col-span-12 lg:col-span-8 reveal" style="--d:120ms">
                <div class="hairline rounded-2xl bg-white divide-y divide-paper-200">
                    @foreach ($items as $i => $item)
                        @php $isOpen = $item['open'] ?? false; @endphp
                        <details class="details group p-5" @if ($isOpen) open @endif>
                            <summary class="flex items-center justify-between">
                                <span class="text-[15px] font-medium"
                                    @unless ($itemsAreCustom) data-fc="{{ fc_skey('faq.faq' . $loop->iteration . '_q') }}" @endunless>{{ $item['q'] }}</span>
                                <span
                                    class="w-7 h-7 rounded-full hairline flex items-center justify-center shrink-0 group-open:bg-wa-deep group-open:text-paper-0 transition">
                                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M3 8h10" />
                                        <path d="M8 3v10" class="group-open:hidden" />
                                    </svg>
                                </span>
                            </summary>
                            <p class="text-[13px] text-ink-600 mt-3 leading-relaxed"
                                @unless ($itemsAreCustom) data-fc="{{ fc_skey('faq.faq' . $loop->iteration . '_a') }}" @endunless>
                                {{ $item['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
