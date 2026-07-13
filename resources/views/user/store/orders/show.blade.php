<x-layouts.user :title="__('Order') . ' #' . $order->id" nav-key="connect" page="user-store-orders-show">
    @php
        $u = auth()->user();
        $cfg = $u?->current_workspace_id
            ? \App\Models\WaProviderConfig::query()->forWorkspace($u->current_workspace_id)->first()
            : null;
        $sf = $u?->current_workspace_id
            ? \App\Models\WaStorefront::where('workspace_id', $u->current_workspace_id)->first()
            : null;
        $items = $order->items_json ?? [];
        $hideIndiaMerchantPayments = \App\Support\ZanaAfricaPayments::hidesIndiaMerchantPayments();
        $orderMeta = is_array($order->meta_json) ? $order->meta_json : [];
        $paymentMeta = \App\Support\ZanaManualPayment::paymentMeta($order);
        $zanaPaymentStep = \App\Support\ZanaManualPayment::paymentStatus($order);
        $paymentInstructions = \App\Support\ZanaAfricaPayments::instructionsText($sf, $order);
        $mpesaInstructions = \App\Support\ZanaKenyaPaymentShortcut::instructionText($sf, $order);
        $hasMpesaShortcut = \App\Support\ZanaKenyaPaymentShortcut::hasMpesaDetails($sf);
        $storedExternalPaymentLink = \App\Support\ZanaAfricaPayments::externalPaymentLink($sf, $order);
        $paymentStepLabel = \App\Support\ZanaManualPayment::statusLabel($zanaPaymentStep);
        $paymentTimeline = \App\Support\ZanaManualPayment::timeline($order);
        $paymentCopy = session('zana_payment_copy');
        $paymentAmountDisplay = \App\Support\ZanaManualPayment::amountReceivedDisplay($order);
        $verificationState = \App\Support\ZanaPaymentVerification::derivedState($order);
        $verificationLabel = \App\Support\ZanaPaymentVerification::derivedLabel($order);
        $paymentTemplateReadiness = \App\Support\ZanaPaymentTemplateReadiness::forStorefront($sf, $u?->current_workspace_id);
        $darajaReadiness = \App\Support\ZanaDarajaSandbox::readiness($sf);
        $darajaMeta = \App\Support\ZanaManualPayment::darajaMeta($order);
        $paystackReadiness = \App\Support\ZanaPaystackMerchantLink::readiness($sf);
        $paystackMeta = \App\Support\ZanaManualPayment::paystackMeta($order);
        $paymentStatusBlock = \App\Support\ZanaPaymentStatusBlock::build($order);
        $paymentGuidance = \App\Support\ZanaOrderPaymentGuidance::build($order, $sf, $u?->current_workspace_id, $paymentStatusBlock, $paymentTemplateReadiness, $paystackReadiness, $darajaReadiness);
        $paymentActions = \App\Support\ZanaOrderPaymentActions::build([
            'has_mpesa_shortcut' => $hasMpesaShortcut,
            'hide_india_merchant_payments' => $hideIndiaMerchantPayments,
            'stored_external_payment_link' => $storedExternalPaymentLink,
            'order_payment_link' => $order->payment_link,
            'paystack_readiness' => $paystackReadiness,
            'daraja_readiness' => $darajaReadiness,
        ]);
    @endphp
    <main class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">
        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            @include('user.store._sidebar', ['current' => 'orders', 'cfg' => $cfg, 'sf' => $sf])

            <section class="space-y-5 max-w-4xl min-w-0">
                <div class="flex items-end justify-between gap-4 flex-wrap">
                    <div>
                        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">
                            <a href="{{ route('user.store.orders.index') }}"
                                class="hover:text-wa-deep">{{ __('Orders') }}</a> / #{{ $order->id }}
                        </div>
                        <h1 class="font-serif text-[26px] sm:text-[34px] leading-tight tracking-[-0.02em]">Order #{{ $order->id }}
                        </h1>
                        @if (($order->payment_method ?? 'prepaid') === 'cod')
                            @php
                                $band = $order->rto_band ?? 'low';
                                $bc = $band === 'high' ? 'bg-accent-coral/15 text-accent-coral' : ($band === 'medium' ? 'bg-accent-amber/15 text-accent-amber' : 'bg-wa-mint text-wa-deep');
                            @endphp
                            <div class="mt-2 flex items-center gap-2 flex-wrap">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono bg-paper-50 text-ink-700 border border-paper-200">{{ __('COD') }}</span>
                                @if ($order->rto_score !== null)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono {{ $bc }}"
                                        title="{{ __('Return-to-origin risk') }}">{{ __('RTO risk') }}:
                                        {{ ucfirst($band) }} ({{ $order->rto_score }})</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="text-[11px] text-ink-500 font-mono">{{ $order->source }} ·
                            {{ $order->created_at->format('M d, Y H:i') }}</div>
                    </div>
                </div>

                @if (session('status'))
                    <div
                        class="bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">
                        {{ session('status') }}</div>
                @endif
                @if ($paymentCopy && !empty($paymentCopy['text']))
                    <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div>
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ $paymentCopy['label'] ?? __('Copy fallback') }}</div>
                                <div class="text-[12px] text-ink-600 mt-1">{{ __('Native send was unavailable, so this copy-ready message is preserved here for manual use.') }}</div>
                            </div>
                            <button type="button" onclick="copyPaymentFallback()" class="px-3 py-1.5 rounded-full border border-wa-deep/30 text-wa-deep text-[12px] font-semibold hover:bg-wa-mint/40">{{ __('Copy text') }}</button>
                        </div>
                        <textarea id="zana-payment-copy-text" rows="6" readonly
                            class="mt-3 w-full px-3 py-2 border border-paper-200 rounded-xl text-[12.5px] bg-paper-50 text-ink-700 focus:outline-none">{{ $paymentCopy['text'] }}</textarea>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">
                    <!-- Items + status -->
                    <div class="space-y-5 min-w-0">
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card">
                            <h3 class="font-serif text-[18px] mb-3">{{ __('Items') }}</h3>
                            <div class="divide-y divide-paper-200">
                                @php $orderCur = $order->currency ?? \App\Models\SystemSetting::get('default_currency', 'USD'); @endphp
                                @foreach ($items as $item)
                                    <div class="py-3 flex items-center gap-3">
                                        @if (!empty($item['image']))
                                            <img src="{{ $item['image'] }}"
                                                class="w-12 h-12 rounded-lg object-cover" />
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-paper-100"></div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium">{{ $item['name'] ?? 'Item' }}</div>
                                            <div class="text-[11px] text-ink-500 font-mono">qty:
                                                {{ $item['qty'] ?? 1 }} · {!! \App\Support\FormatSettings::formatIn(($item['price_minor'] ?? 0) / 100, $orderCur) !!}</div>
                                        </div>
                                        <div class="font-semibold">{!! \App\Support\FormatSettings::formatIn(
                                            (((int) ($item['price_minor'] ?? 0)) * ((int) ($item['qty'] ?? 1))) / 100,
                                            $orderCur,
                                        ) !!}</div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="border-t border-paper-200 mt-3 pt-3 flex items-center justify-between">
                                <span class="font-mono text-[11px] text-ink-500">{{ __('TOTAL') }}</span>
                                <span class="font-serif text-[24px]">{{ $order->total_display }}</span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('user.store.orders.update', $order->id) }}"
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-5 shadow-card space-y-3">
                            @csrf @method('PUT')
                            <input type="hidden" name="payment_action" id="payment-action-input" value="">
                            <h3 class="font-serif text-[18px]">{{ __('Update status') }}</h3>
                            <div class="flex items-center gap-2 flex-wrap">
                                @if ($paymentStepLabel)
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-wa-green/30 bg-wa-mint/40 text-[11px] font-semibold text-wa-deep">{{ $paymentStepLabel }}</div>
                                @endif
                                @if (!empty($paymentGuidance['payment_rail']['label']))
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-paper-200 bg-paper-50 text-[11px] font-semibold text-ink-700">{{ $paymentGuidance['payment_rail']['label'] }}</div>
                                @endif
                                @if ($paymentAmountDisplay)
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-paper-200 bg-paper-50 text-[11px] font-semibold text-ink-700">{{ __('Received:') }} {{ $paymentAmountDisplay }}</div>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Status') }}</span>
                                    <select name="status"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                        @foreach (\App\Models\WaOrder::STATUSES as $s)
                                            <option value="{{ $s }}" @selected($order->status === $s)>
                                                {{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Payment link') }}
                                        <span class="text-ink-500 font-normal">(optional)</span></span>
                                    <input type="url" name="payment_link" maxlength="1024"
                                        value="{{ $order->payment_link ?: $storedExternalPaymentLink }}" placeholder="https://paystack.com/pay/..."
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                </label>
                            </div>
                            <div class="rounded-2xl border border-paper-200 bg-paper-50/60 p-4 space-y-3">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div>
                                        <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Manual payment confirmation') }}</div>
                                        <div class="text-[12px] text-ink-600 mt-1">{{ __('Record the payment method, reference, amount, and confirmation note.') }}</div>
                                    </div>
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $verificationState === 'awaiting_verification' ? 'bg-[#FFF4D8] text-[#9A6B00]' : ($verificationState === 'paid_confirmed' ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-700') }} text-[11px] font-semibold">{{ $verificationLabel }}</div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11.5px] text-ink-600">
                                    <div class="rounded-xl border border-paper-200 bg-paper-0 px-3 py-2">
                                        <span class="font-semibold text-ink-700">{{ __('Order reference') }}:</span>
                                        <span class="font-mono text-ink-700">{{ \App\Support\ZanaPaymentVerification::orderReference($order) }}</span>
                                    </div>
                                    <div class="rounded-xl border border-paper-200 bg-paper-0 px-3 py-2">
                                        <span class="font-semibold text-ink-700">{{ __('Expected amount') }}:</span>
                                        <span class="text-ink-700">{{ $order->total_display }}</span>
                                    </div>
                                    <div class="rounded-xl border border-paper-200 bg-paper-0 px-3 py-2">
                                        <span class="font-semibold text-ink-700">{{ __('Manual review cue') }}:</span>
                                        @if (\App\Support\ZanaPaymentVerification::missingReference($order))
                                            <span class="text-ink-700">{{ __('Ask the customer for the M-Pesa confirmation code before confirming payment.') }}</span>
                                        @elseif (\App\Support\ZanaPaymentVerification::referenceRecorded($order))
                                            <span class="text-ink-700">{{ __('Reference recorded; verify evidence, then click Paid confirmed.') }}</span>
                                        @else
                                            <span class="text-ink-700">{{ __('Send instructions, mark customer says paid, then confirm once verified.') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Merchant payment status') }}</span>
                                        <select name="zana_payment_status"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                            @foreach (\App\Support\ZanaManualPayment::STATUSES as $paymentState)
                                                <option value="{{ $paymentState }}" @selected($zanaPaymentStep === $paymentState)>{{ \App\Support\ZanaManualPayment::statusLabel($paymentState) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Payment method') }}</span>
                                        <select name="zana_payment_method"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">
                                            <option value="">{{ __('Select method') }}</option>
                                            @foreach (\App\Support\ZanaManualPayment::METHODS as $paymentMethod)
                                                <option value="{{ $paymentMethod }}" @selected(($paymentMeta['payment_method'] ?? '') === $paymentMethod)>{{ \App\Support\ZanaManualPayment::methodLabel($paymentMethod) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Transaction / reference code') }}</span>
                                        <input type="text" name="zana_payment_reference" maxlength="120"
                                            value="{{ old('zana_payment_reference', $paymentMeta['transaction_reference'] ?? '') }}"
                                            placeholder="RFG123ABC"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="block">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Amount received') }}</span>
                                        <input type="text" name="zana_amount_received" maxlength="40"
                                            value="{{ old('zana_amount_received', $paymentMeta['amount_received'] ?? '') }}"
                                            placeholder="2500.00"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] font-mono focus:outline-none focus:border-wa-deep" />
                                    </label>
                                    <label class="block sm:col-span-2">
                                        <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Payer phone / payer note') }}</span>
                                        <input type="text" name="zana_payer_note" maxlength="255"
                                            value="{{ old('zana_payer_note', $paymentMeta['payer_note'] ?? '') }}"
                                            placeholder="+2547..., paid from spouse phone, branch deposit, or other clue"
                                            class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep" />
                                    </label>
                                </div>
                                <label class="block">
                                    <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Internal confirmation note') }}</span>
                                    <textarea name="zana_confirmation_note" rows="3" maxlength="1000"
                                        class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">{{ old('zana_confirmation_note', $paymentMeta['confirmation_note'] ?? '') }}</textarea>
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[12px] text-ink-600">
                                    <div class="rounded-xl border border-paper-200 bg-paper-0 px-3 py-2">
                                        <div class="font-semibold text-ink-700">{{ __('Customer says paid') }}</div>
                                        <div class="mt-1">{{ !empty($paymentMeta['customer_says_paid_at']) ? \App\Support\ZanaManualPayment::displayAt($paymentMeta['customer_says_paid_at']) : __('Not recorded yet') }}</div>
                                        @if (!empty($paymentMeta['customer_says_paid_by']))
                                            <div class="text-[11px] text-ink-500">{{ __('Recorded by') }} {{ $paymentMeta['customer_says_paid_by'] }}</div>
                                        @endif
                                    </div>
                                    <div class="rounded-xl border border-paper-200 bg-paper-0 px-3 py-2">
                                        <div class="font-semibold text-ink-700">{{ __('Paid confirmed') }}</div>
                                        <div class="mt-1">{{ !empty($paymentMeta['confirmed_at']) ? \App\Support\ZanaManualPayment::displayAt($paymentMeta['confirmed_at']) : __('Not confirmed yet') }}</div>
                                        @if (!empty($paymentMeta['confirmed_by']))
                                            <div class="text-[11px] text-ink-500">{{ __('Confirmed by') }} {{ $paymentMeta['confirmed_by'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <details class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2 text-[12px] text-ink-600">
                                <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                                    <span class="font-semibold text-ink-700">{{ __('Payment instructions & workflow') }}</span>
                                    <span class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('View details') }}</span>
                                </summary>
                                <div class="mt-2 border-t border-paper-200 pt-2 space-y-2">
                                    <p>{{ __('Backend-safe labels: Awaiting Payment → pending, Customer Says Paid → confirmed, Paid Confirmed → paid, Payment Failed / Refunded → cancelled. Native send is attempted first, approved templates are used when required, and copy fallback appears only when no valid send path succeeds.') }}</p>
                                    @if ($hasMpesaShortcut && $mpesaInstructions)
                                        <label class="block">
                                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Kenya M-Pesa instruction preview') }}</span>
                                            <textarea rows="4" readonly
                                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] bg-paper-0 text-ink-700 focus:outline-none">{{ $mpesaInstructions }}</textarea>
                                        </label>
                                    @elseif ($paymentInstructions)
                                        <label class="block">
                                            <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Current payment instructions') }}</span>
                                            <textarea rows="3" readonly
                                                class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[12.5px] bg-paper-0 text-ink-700 focus:outline-none">{{ $paymentInstructions }}</textarea>
                                        </label>
                                    @endif
                                </div>
                            </details>
                            <label class="block">
                                <span class="text-[11.5px] font-semibold text-ink-700">{{ __('Notes') }}</span>
                                <textarea name="notes" rows="2" maxlength="1000"
                                    class="mt-1 w-full px-3 py-2 border border-paper-200 rounded-lg text-[13px] focus:outline-none focus:border-wa-deep">{{ $order->notes }}</textarea>
                            </label>
                            <div class="rounded-2xl border border-paper-200 bg-paper-50/60 p-4">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Kenya payment shortcuts') }}</div>
                                <div class="text-[12px] text-ink-600 mt-1">{{ __('Move through the common Kenya flow quickly: send M-Pesa instructions, wait for the customer’s confirmation code, then confirm the payment once you verify the reference.') }}</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($paymentActions['kenya_shortcuts'] as $action)
                                        <button type="button"
                                            onclick="{{ $action['handler'] ?? "submitPaymentAction('{$action['id']}')" }}"
                                            class="{{ $action['classes'] }}"
                                            @disabled(!empty($action['disabled']))
                                            @if(!empty($action['reason'])) title="{{ $action['reason'] }}" @endif>{{ __($action['label']) }}</button>
                                    @endforeach
                                </div>
                                <details class="mt-3 rounded-xl border border-paper-200 bg-paper-0 px-3 py-2 text-[12px] text-ink-600">
                                    <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                                        <span class="font-semibold text-ink-700">{{ __('Payment setup / provider readiness') }}</span>
                                        <span class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Secondary') }}</span>
                                    </summary>
                                    <div class="mt-3 border-t border-paper-200 pt-3 space-y-3">
                                        @foreach ($paymentGuidance['panels'] as $panel)
                                            @php
                                                $toneClasses = match ($panel['tone'] ?? 'neutral') {
                                                    'success' => 'border-wa-green/30 bg-wa-mint/20',
                                                    'warning' => 'border-[#F3D58B] bg-[#FFF8E8]',
                                                    default => 'border-paper-200 bg-paper-0',
                                                };
                                                $badgeClasses = match ($panel['tone'] ?? 'neutral') {
                                                    'success' => 'bg-wa-mint text-wa-deep',
                                                    'warning' => 'bg-[#FFF0C2] text-[#805C00]',
                                                    default => 'bg-paper-100 text-ink-700',
                                                };
                                            @endphp
                                            <div class="rounded-xl border {{ $toneClasses }} px-3 py-3">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="font-semibold text-ink-700">{{ __($panel['title']) }}</div>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold {{ $badgeClasses }}">{{ __($panel['status_label']) }}</span>
                                                </div>
                                                <div class="mt-1">{{ __($panel['body']) }}</div>
                                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                                                    @foreach ($panel['rows'] as $row)
                                                        <div class="rounded-lg border border-paper-200/80 bg-paper-0/70 px-2.5 py-2">
                                                            <div class="font-mono uppercase tracking-[0.12em] text-[9.5px] text-ink-500">{{ __($row['label']) }}</div>
                                                            <div class="mt-0.5 text-ink-700 break-all">{{ $row['value'] }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                @if (!empty($panel['hint']))
                                                    <div class="mt-2 text-[11px] text-ink-500">{{ __($panel['hint']) }}</div>
                                                @endif
                                                @if (($panel['key'] ?? '') === 'paystack')
                                                    @if (!empty($paystackMeta['reference']))
                                                        <div class="mt-2 text-[11px] text-ink-500">{{ __('Last Paystack reference') }}: <span class="font-mono">{{ $paystackMeta['reference'] }}</span></div>
                                                    @endif
                                                    @if (!empty($paystackMeta['status']))
                                                        <div class="mt-1 text-[11px] text-ink-500">{{ __('Last Paystack state') }}: {{ $paystackMeta['status'] }}</div>
                                                    @endif
                                                @endif
                                                @if (($panel['key'] ?? '') === 'daraja')
                                                    @if (!empty($darajaMeta['status']))
                                                        <div class="mt-2 text-[11px] text-ink-500">{{ __('Last Daraja state') }}: {{ $darajaMeta['status'] }}</div>
                                                    @endif
                                                    @if (!empty($darajaMeta['checkout_request_id']))
                                                        <div class="mt-1 text-[11px] text-ink-500">{{ __('CheckoutRequestID') }}: <span class="font-mono">{{ $darajaMeta['checkout_request_id'] }}</span></div>
                                                    @endif
                                                    @if (!empty($darajaMeta['merchant_request_id']))
                                                        <div class="mt-1 text-[11px] text-ink-500">{{ __('MerchantRequestID') }}: <span class="font-mono">{{ $darajaMeta['merchant_request_id'] }}</span></div>
                                                    @endif
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            </div>
                            <div class="flex justify-end gap-2 pt-2 border-t border-paper-200 flex-wrap">
                                @foreach (['provider_actions', 'payment_messaging', 'payment_state_updates'] as $group)
                                    @foreach ($paymentActions[$group] as $action)
                                        <button type="button"
                                            onclick="{{ $action['handler'] ?? "submitPaymentAction('{$action['id']}')" }}"
                                            class="{{ $action['classes'] }}"
                                            @disabled(!empty($action['disabled']))
                                            @if(!empty($action['reason'])) title="{{ $action['reason'] }}" @endif>{{ __($action['label']) }}</button>
                                    @endforeach
                                @endforeach
                                @foreach ($paymentActions['primary_submit'] as $action)
                                    <button type="submit"
                                        class="{{ $action['classes'] }}">{{ __($action['label']) }}</button>
                                @endforeach
                            </div>
                        </form>
                    </div>

                    <!-- Customer + meta -->
                    <aside class="space-y-3">
                        {{-- WhatsApp Pay — native in-chat charge (India) --}}
                        @unless ($hideIndiaMerchantPayments)
                        @php
                            $wps = $order->wa_payment_status;
                            $wpsCls = match ($wps) {
                                'captured' => 'bg-wa-mint text-wa-deep border-wa-green/40',
                                'failed'   => 'bg-red-50 text-red-700 border-red-200',
                                'refunded' => 'bg-paper-50 text-ink-500 border-paper-200',
                                'pending'  => 'bg-amber-50 text-amber-700 border-amber-200',
                                default    => null,
                            };
                        @endphp
                        <form method="POST" action="{{ route('user.store.orders.whatsapp-pay', $order->id) }}"
                            class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            @csrf
                            <div class="flex items-center justify-between gap-2">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('WhatsApp Pay') }}</div>
                                @if ($wpsCls)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono border {{ $wpsCls }}">{{ ucfirst($wps) }}</span>
                                @endif
                            </div>
                            <p class="text-[12px] text-ink-600 mt-1.5 leading-relaxed">{{ __('Send a native in-chat payment request. The customer pays without leaving WhatsApp (India only).') }}</p>
                            @if ($order->wa_payment_reference_id)
                                <div class="mt-2 font-mono text-[10.5px] text-ink-500 break-all">ref: {{ $order->wa_payment_reference_id }}</div>
                            @endif
                            <button type="submit"
                                class="mt-3 w-full px-4 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">
                                {{ $wps === 'captured' ? __('Re-send order details') : __('Request payment on WhatsApp') }}</button>
                            <a href="{{ route('user.store.payments.index') }}" class="block mt-2 text-center text-[11px] text-wa-deep hover:underline">{{ __('Configure WhatsApp Pay →') }}</a>
                        </form>
                        @endunless

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Africa payment flow') }}</div>
                            <div class="mt-1 text-[12px] text-ink-600 leading-relaxed">
                                {{ __('Use manual payment instructions, pasted payment links, and manual confirmation as the launch flow. Native automated rails can be added later without changing the storefront/order foundation.') }}
                            </div>
                            <a href="{{ route('user.store.storefront.edit') }}" class="mt-3 inline-flex text-[11px] font-semibold text-wa-deep hover:underline">{{ __('Edit storefront payment setup →') }}</a>
                        </div>

                        <details class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card text-[12px] text-ink-600">
                            <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                                <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Template readiness') }}</span>
                                <span class="font-semibold text-ink-700">{{ $paymentTemplateReadiness['engine_label'] }}</span>
                            </summary>
                            <div class="mt-3 border-t border-paper-200 pt-3 space-y-2">
                                <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2">
                                    <div class="font-semibold text-ink-700">{{ __('Payment instructions template') }}</div>
                                    <div class="mt-1">{{ $paymentTemplateReadiness['instruction']['label'] ?? __('Unknown') }}</div>
                                    <div class="mt-1 text-[11px] text-ink-500">{{ $paymentTemplateReadiness['instruction']['notes'] ?? '' }}</div>
                                </div>
                                <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2">
                                    <div class="font-semibold text-ink-700">{{ __('Payment reminder template') }}</div>
                                    <div class="mt-1">{{ $paymentTemplateReadiness['reminder']['label'] ?? __('Unknown') }}</div>
                                    <div class="mt-1 text-[11px] text-ink-500">{{ $paymentTemplateReadiness['reminder']['notes'] ?? '' }}</div>
                                </div>
                            </div>
                        </details>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Customer') }}</div>
                            <div class="font-serif text-[18px] mt-1">{{ $order->customer_name ?: 'Anonymous' }}</div>
                            <div class="font-mono text-[11px] text-ink-700 mt-1">{{ $order->customer_phone }}</div>
                            @if ($order->customer_email)
                                <div class="font-mono text-[11px] text-ink-500">{{ $order->customer_email }}</div>
                            @endif
                            <a href="{{ url('/chat') }}"
                                class="mt-3 inline-flex items-center gap-1 text-[11.5px] text-wa-deep font-semibold hover:underline">
                                {{ __('Reply on WhatsApp →') }}
                            </a>
                        </div>

                        {{-- Shipping address — captured by the order flow (Jessica #2).
                             customer_name/address live on wa_orders; company rides in
                             meta_json.ship_company. Shows "not captured" when empty so
                             the operator can see whether the flow saved it. --}}
                        @php
                            $oMeta       = (array) ($order->meta_json ?? []);
                            $shipAddr    = trim((string) ($order->customer_address ?? ''));
                            $shipCompany = trim((string) ($oMeta['ship_company'] ?? ''));
                        @endphp
                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Shipping address') }}</div>
                            @if ($shipAddr !== '' || $shipCompany !== '')
                                @if ($order->customer_name)
                                    <div class="text-[13px] font-semibold mt-1.5">{{ $order->customer_name }}</div>
                                @endif
                                @if ($shipCompany !== '')
                                    <div class="text-[11.5px] text-ink-700">{{ $shipCompany }}</div>
                                @endif
                                @if ($shipAddr !== '')
                                    <div class="text-[11.5px] text-ink-700 mt-1 whitespace-pre-line leading-relaxed">{{ $shipAddr }}</div>
                                @endif
                            @else
                                <div class="text-[11.5px] text-ink-400 mt-1.5 italic">{{ __('No address captured for this order.') }}</div>
                            @endif
                        </div>

                        {{-- Voice / Photo order (Jessica #3) — the ORIGINAL voice note or
                             photo the customer sent, so the merchant can replay/inspect
                             it. Path + transcript live on meta_json; media_url() is
                             cloud-aware (local public disk or configured cloud storage). --}}
                        @php
                            $omPath  = trim((string) ($oMeta['order_media_path'] ?? ''));
                            $omType  = (string) ($oMeta['order_media_type'] ?? '');
                            $omText  = trim((string) ($oMeta['order_media_transcript'] ?? ''));
                            $omUrl   = $omPath !== '' ? media_url($omPath) : '';
                        @endphp
                        @if ($omUrl !== '')
                            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 flex items-center gap-1.5">
                                    @if ($omType === 'voice')
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
                                        {{ __('Voice order') }}
                                    @else
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                        {{ __('Photo order') }}
                                    @endif
                                </div>
                                @if ($omType === 'voice')
                                    <audio controls preload="none" class="w-full mt-2.5" src="{{ $omUrl }}"></audio>
                                @else
                                    <a href="{{ $omUrl }}" target="_blank" rel="noopener">
                                        <img src="{{ $omUrl }}" alt="{{ __('Customer photo') }}" class="mt-2.5 rounded-xl border border-paper-200 max-h-64 w-auto object-contain">
                                    </a>
                                @endif
                                @if ($omText !== '')
                                    <div class="mt-2.5 text-[11.5px] text-ink-600 italic whitespace-pre-line leading-relaxed">
                                        <span class="not-italic font-mono text-[9.5px] uppercase tracking-wider text-ink-400">{{ __('AI transcript') }}</span><br>
                                        “{{ $omText }}”
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">
                                {{ __('Order meta') }}</div>
                            <dl class="text-[11.5px] mt-2 space-y-1">
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ __('Order ID') }}</dt>
                                    <dd class="font-mono">#{{ $order->id }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ __('Source') }}</dt>
                                    <dd class="font-mono">{{ $order->source }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-ink-500">{{ __('Channel msg id') }}</dt>
                                    <dd class="font-mono truncate ml-2">{{ $order->wa_message_id ?: '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Payment status') }}</div>
                            <div class="mt-1 text-[12px] text-ink-600">{{ __('Quick view of the active payment rail, callback state, send state, and what the merchant should do next.') }}</div>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-[12px]">
                                <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Payment rail') }}</div>
                                    <div class="mt-1 font-semibold text-ink-900">{{ $paymentStatusBlock['rail'] }}</div>
                                </div>
                                <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                                    <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Current status') }}</div>
                                    <div class="mt-1 font-semibold text-ink-900">{{ $paymentStatusBlock['status'] }}</div>
                                </div>
                                @if (!empty($paymentStatusBlock['provider']))
                                    <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Provider state') }}</div>
                                        <div class="mt-1 font-semibold text-ink-900">{{ $paymentStatusBlock['provider'] }}</div>
                                    </div>
                                @endif
                                @if (!empty($paymentStatusBlock['amount_check']))
                                    <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Amount check') }}</div>
                                        <div class="mt-1 font-semibold text-ink-900">{{ $paymentStatusBlock['amount_check'] }}</div>
                                    </div>
                                @endif
                                @if (!empty($paymentStatusBlock['send_state']))
                                    <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Send state') }}</div>
                                        <div class="mt-1 font-semibold text-ink-900">{{ $paymentStatusBlock['send_state'] }}</div>
                                    </div>
                                @endif
                                @if (!empty($paymentStatusBlock['reference']))
                                    <div class="rounded-xl border border-paper-200 bg-paper-50 px-3 py-2.5">
                                        <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Reference') }}</div>
                                        <div class="mt-1 font-semibold text-ink-900 font-mono break-all">{{ $paymentStatusBlock['reference'] }}</div>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-3 rounded-xl border border-wa-green/20 bg-wa-mint/30 px-3 py-3">
                                <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500">{{ __('Next recommended action') }}</div>
                                <div class="mt-1 text-[12.5px] font-semibold text-wa-deep">{{ $paymentStatusBlock['next_action'] }}</div>
                            </div>
                        </div>

                        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('Payment history') }}</div>
                                    <div class="text-[12px] text-ink-600 mt-1">{{ __('Track instructions, reminders, customer-paid claims, references, and merchant confirmation in one place.') }}</div>
                                </div>
                            </div>
                            @if (count($paymentTimeline))
                                <div class="mt-3 space-y-3">
                                    @foreach ($paymentTimeline as $event)
                                        @php
                                            $tone = \App\Support\ZanaManualPayment::eventTone((string) ($event['type'] ?? ''));
                                            $toneClasses = match ($tone) {
                                                'success' => 'border-wa-green/30 bg-wa-mint/30',
                                                'danger' => 'border-accent-coral/20 bg-accent-coral/10',
                                                'info' => 'border-[#D9E5F2] bg-[#F3F8FD]',
                                                default => 'border-paper-200 bg-paper-50',
                                            };
                                        @endphp
                                        <div class="rounded-xl border {{ $toneClasses }} px-3 py-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="font-semibold text-[12.5px] text-ink-900">{{ $event['label'] ?? \App\Support\ZanaManualPayment::eventLabel((string) ($event['type'] ?? '')) }}</div>
                                                <div class="text-[10.5px] font-mono text-ink-500">{{ \App\Support\ZanaManualPayment::displayAt($event['at'] ?? null) ?? ($event['at'] ?? '') }}</div>
                                            </div>
                                            <div class="mt-1 text-[11.5px] text-ink-600 space-y-1">
                                                @if (!empty($event['payment_status']))
                                                    <div>{{ __('State:') }} {{ \App\Support\ZanaManualPayment::statusLabel($event['payment_status']) }}</div>
                                                @endif
                                                @if (!empty($event['payment_method']))
                                                    <div>{{ __('Method:') }} {{ \App\Support\ZanaManualPayment::methodLabel($event['payment_method']) }}</div>
                                                @endif
                                                @if (!empty($event['transaction_reference']))
                                                    <div>{{ __('Reference:') }} <span class="font-mono">{{ $event['transaction_reference'] }}</span></div>
                                                @endif
                                                @if (!empty($event['amount_received_display']))
                                                    <div>{{ __('Amount:') }} {{ $event['amount_received_display'] }}</div>
                                                @endif
                                                @if (!empty($event['send_error']))
                                                    <div>{{ __('Send note:') }} {{ $event['send_error'] }}</div>
                                                @endif
                                                @if (!empty($event['message_delivery_label']))
                                                    <div>{{ __('Send state:') }} {{ $event['message_delivery_label'] }}</div>
                                                @endif
                                                @if (!empty($event['message_delivered_at']))
                                                    <div>{{ __('Delivered:') }} {{ \App\Support\ZanaManualPayment::displayAt($event['message_delivered_at']) }}</div>
                                                @endif
                                                @if (!empty($event['message_read_at']))
                                                    <div>{{ __('Read:') }} {{ \App\Support\ZanaManualPayment::displayAt($event['message_read_at']) }}</div>
                                                @endif
                                                @if (!empty($event['note']))
                                                    <div>{{ __('Note:') }} {{ $event['note'] }}</div>
                                                @endif
                                                @if (!empty($event['actor_name']))
                                                    <div class="text-[11px] text-ink-500">{{ __('By') }} {{ $event['actor_name'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-3 rounded-xl border border-paper-200 bg-paper-50 px-3 py-3 text-[12px] text-ink-500">
                                    {{ __('No payment events recorded yet. Send instructions, record a reference, or confirm payment to start the timeline.') }}
                                </div>
                            @endif
                        </div>
                    </aside>
                </div>
            </section>
        </div>
    </main>

    <script>
        function copyPaymentFallback() {
            const el = document.getElementById('zana-payment-copy-text');
            if (!el) return;
            el.select();
            el.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(el.value);
        }

        function submitPaymentAction(action) {
            const input = document.getElementById('payment-action-input');
            if (input) {
                input.value = action;
            }
            input?.form?.submit();
        }

        async function sendPaymentLink() {
            const link = document.querySelector('[name=payment_link]').value;
            if (!link) return alert('Save the payment link first.');
            const r = await fetch(@json(route('user.store.orders.payment-link', $order->id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    Accept: 'application/json'
                },
                body: JSON.stringify({
                    payment_link: link
                }),
            });
            const j = await r.json();
            if (j.ok) {
                alert('Payment link sent.');
                return;
            }
            if (j.fallback_text) {
                await navigator.clipboard.writeText(j.fallback_text);
                alert((j.error || 'Native send unavailable.') + ' The payment link message has been copied for manual sending.');
                return;
            }
            alert('Failed to send.');
        }
        async function generatePaymentLink() {
            const r = await fetch(@json(route('user.store.orders.generate-payment-link', $order->id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    Accept: 'application/json'
                },
            });
            const j = await r.json().catch(() => ({}));
            if (j.ok) {
                alert('Payment link generated' + (j.sent ? ' and sent on WhatsApp.' : '.'));
                location.reload();
            } else {
                alert(j.message || 'Could not generate link.');
            }
        }
    </script>
</x-layouts.user>
