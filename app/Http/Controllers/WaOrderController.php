<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\WaOrder;
use App\Support\ZanaAfricaPayments;
use App\Support\ZanaDarajaSandbox;
use App\Support\ZanaKenyaPaymentShortcut;
use App\Support\ZanaManualPayment;
use App\Support\ZanaPaystackMerchantLink;
use App\Support\ZanaPaymentTemplateFallback;
use App\Support\ZanaPaymentVerification;
use App\Support\ZanaWeeklyPaymentReport;
use App\Services\WhatsAppDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaOrderController extends Controller
{
    public function __construct(private readonly WhatsAppDispatcher $dispatcher) {}

    public function index(Request $request): View|StreamedResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $status = $request->string('status')->toString() ?: 'all';
        $source = $request->string('source')->toString() ?: 'all';
        $paymentState = $request->string('payment_state')->toString() ?: 'all';
        $verificationState = $request->string('verification_state')->toString() ?: ZanaPaymentVerification::FILTER_ALL;
        $reportDays = max(7, min(30, (int) $request->integer('report_days', 7)));
        $export = $request->string('export')->toString() === 'csv';
        $q = trim((string) $request->string('q')->toString());

        $baseQuery = WaOrder::forWorkspace($wsId)
            ->when($status !== 'all', fn ($qq) => $qq->where('status', $status))
            ->when($source !== 'all', fn ($qq) => $qq->where('source', $source))
            ->when($paymentState !== 'all', fn ($qq) => $qq->where('meta_json->' . ZanaManualPayment::PAYMENT_KEY . '->status', $paymentState))
            ->orderByDesc('created_at');

        $filteredOrders = $baseQuery->get();
        if ($q !== '') {
            $filteredOrders = $filteredOrders->filter(
                fn (WaOrder $order) => ZanaPaymentVerification::matchesSearch($order, $q)
            )->values();
        }
        if ($verificationState !== ZanaPaymentVerification::FILTER_ALL) {
            $filteredOrders = $filteredOrders->filter(
                fn (WaOrder $order) => ZanaPaymentVerification::matchesFilter($order, $verificationState)
            )->values();
        }

        if ($export) {
            return response()->streamDownload(function () use ($filteredOrders) {
                $out = fopen('php://output', 'w');
                fputcsv($out, [
                    'order_id',
                    'order_reference',
                    'customer_name',
                    'customer_phone',
                    'order_status',
                    'payment_state',
                    'verification_state',
                    'payment_method',
                    'amount_due',
                    'amount_received',
                    'payment_reference',
                    'paystack_status',
                    'paystack_reference',
                    'paystack_callback_received_at',
                    'daraja_status',
                    'daraja_checkout_request_id',
                    'daraja_merchant_request_id',
                    'daraja_callback_received_at',
                    'payer_note',
                    'confirmed_at',
                    'updated_at',
                ]);

                foreach ($filteredOrders as $order) {
                    $paymentMeta = ZanaManualPayment::paymentMeta($order);
                    $paystack = ZanaManualPayment::paystackMeta($order);
                    $daraja = ZanaManualPayment::darajaMeta($order);
                    fputcsv($out, [
                        $order->id,
                        'ORDER-' . $order->id,
                        $order->customer_name,
                        $order->customer_phone,
                        $order->status,
                        ZanaManualPayment::statusLabel(ZanaManualPayment::paymentStatus($order)),
                        ZanaPaymentVerification::derivedLabel($order),
                        ZanaManualPayment::methodLabel($paymentMeta['payment_method'] ?? null),
                        strip_tags((string) $order->total_display),
                        ZanaManualPayment::amountReceivedDisplay($order) ?: '',
                        $paymentMeta['transaction_reference'] ?? '',
                        $paystack['status'] ?? '',
                        $paystack['reference'] ?? '',
                        ZanaManualPayment::displayAt($paystack['callback_received_at'] ?? null) ?: '',
                        $daraja['status'] ?? '',
                        $daraja['checkout_request_id'] ?? '',
                        $daraja['merchant_request_id'] ?? '',
                        ZanaManualPayment::displayAt($daraja['callback_received_at'] ?? null) ?: '',
                        $paymentMeta['payer_note'] ?? '',
                        ZanaManualPayment::displayAt($paymentMeta['confirmed_at'] ?? null) ?: '',
                        optional($order->updated_at)->format('Y-m-d H:i:s'),
                    ]);
                }

                fclose($out);
            }, 'zana-payment-orders.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 25;
        $rows = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredOrders->forPage($page, $perPage)->values(),
            $filteredOrders->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // One grouped query instead of N per-status counts — also picks up the
        // natural-language-ordering statuses (pending / processing / completed).
        $byStatus = WaOrder::forWorkspace($wsId)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $counts = ['all' => (int) $byStatus->sum()];
        foreach (WaOrder::STATUSES as $st) {
            $counts[$st] = (int) ($byStatus[$st] ?? 0);
        }

        $paymentOrders = WaOrder::forWorkspace($wsId)->get(['id', 'status', 'meta_json', 'total_minor', 'currency_code', 'updated_at', 'customer_phone', 'customer_name']);
        $paymentCounts = ['all' => $paymentOrders->count()];
        foreach (ZanaManualPayment::STATUSES as $state) {
            $paymentCounts[$state] = $paymentOrders->filter(
                fn (WaOrder $order) => ZanaManualPayment::paymentStatus($order) === $state
            )->count();
        }
        $reportingSummary = [
            'awaiting_payment' => $paymentCounts['awaiting_payment'] ?? 0,
            'customer_says_paid' => $paymentCounts['customer_says_paid'] ?? 0,
            'paid_confirmed' => $paymentCounts['paid_confirmed'] ?? 0,
            'payment_failed' => $paymentCounts['payment_failed'] ?? 0,
            'refunded' => $paymentCounts['refunded'] ?? 0,
            'with_reference' => $paymentOrders->filter(
                fn (WaOrder $order) => trim((string) (ZanaManualPayment::paymentMeta($order)['transaction_reference'] ?? '')) !== ''
            )->count(),
            'needs_review' => $paymentOrders->filter(function (WaOrder $order) {
                if (ZanaManualPayment::paymentStatus($order) !== 'customer_says_paid') {
                    return false;
                }

                $payment = ZanaManualPayment::paymentMeta($order);

                return trim((string) ($payment['transaction_reference'] ?? '')) === ''
                    || trim((string) ($payment['amount_received'] ?? '')) === '';
            })->count(),
        ];

        $weeklySummary = ZanaWeeklyPaymentReport::build($paymentOrders, $reportDays);

        return view('user.store.orders.index', compact(
            'rows',
            'counts',
            'status',
            'source',
            'q',
            'paymentState',
            'paymentCounts',
            'reportingSummary',
            'verificationState',
            'reportDays',
            'weeklySummary'
        ));
    }

    public function show(int $id): View
    {
        $wsId = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        return view('user.store.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:' . implode(',', WaOrder::STATUSES),
            'notes'  => 'nullable|string|max:1000',
            'payment_link' => 'nullable|url|max:1024',
            'payment_action' => 'nullable|in:send_instructions,send_mpesa_instructions,send_daraja_stk,generate_paystack_link,generate_paystack_link_send,send_reminder,customer_says_paid,paid_confirmed,payment_failed,resend_link,refunded',
            'zana_payment_status' => 'nullable|in:' . implode(',', ZanaManualPayment::STATUSES),
            'zana_payment_method' => 'nullable|in:' . implode(',', ZanaManualPayment::METHODS),
            'zana_payment_reference' => 'nullable|string|max:120',
            'zana_amount_received' => 'nullable|string|max:40',
            'zana_payer_note' => 'nullable|string|max:255',
            'zana_confirmation_note' => 'nullable|string|max:1000',
        ]);
        $actor = Auth::user();
        $paymentAction = (string) ($data['payment_action'] ?? '');
        $paymentActionState = ZanaManualPayment::applyAction($order, $paymentAction, $actor);

        $statusChanged = (string) $order->status !== (string) $data['status'];
        $newLink       = trim((string) ($data['payment_link'] ?? ''));
        $paymentAttributes = [
            'status' => (string) ($data['zana_payment_status'] ?? $paymentActionState['status'] ?? ZanaManualPayment::paymentStatus($order)),
            'payment_method' => (string) ($data['zana_payment_method'] ?? ZanaManualPayment::paymentMeta($order)['payment_method'] ?? ''),
            'transaction_reference' => trim((string) ($data['zana_payment_reference'] ?? '')),
            'amount_received' => ZanaManualPayment::parseAmount($data['zana_amount_received'] ?? null),
            'amount_received_currency' => $order->currency_code ?: 'KES',
            'payer_note' => trim((string) ($data['zana_payer_note'] ?? '')),
            'confirmation_note' => trim((string) ($data['zana_confirmation_note'] ?? '')),
        ];

        foreach (['customer_says_paid', 'customer_says_paid_at', 'customer_says_paid_by', 'confirmed_by', 'confirmed_at'] as $actionKey) {
            if (array_key_exists($actionKey, $paymentActionState)) {
                $paymentAttributes[$actionKey] = $paymentActionState[$actionKey];
            }
        }

        if ($paymentAction !== '' && !empty($paymentActionState['order_status'])) {
            $data['status'] = (string) $paymentActionState['order_status'];
            $statusChanged = (string) $order->status !== (string) $data['status'];
        }

        $eventType = null;
        if (!in_array($paymentAction, ['send_instructions', 'send_mpesa_instructions', 'send_daraja_stk', 'generate_paystack_link', 'generate_paystack_link_send', 'send_reminder', 'resend_link'], true)) {
            $eventType = (string) ($paymentActionState['event'] ?? '');
        }
        if ($eventType === '' && $paymentAttributes['transaction_reference'] !== '' && $paymentAttributes['transaction_reference'] !== (string) (ZanaManualPayment::paymentMeta($order)['transaction_reference'] ?? '')) {
            $eventType = 'reference_recorded';
        }
        if ($eventType === '' && array_filter($paymentAttributes, fn ($value) => $value !== null && $value !== '')) {
            $eventType = 'payment_details_updated';
        }

        $order->fill($data);
        $order->meta_json = ZanaManualPayment::mergeIntoOrder(
            $order,
            $paymentAttributes,
            $actor,
            $eventType !== '' ? $eventType : null
        );
        $order->save();

        $copyPayload = null;
        if ($paymentAction === 'generate_paystack_link_send') {
            $paystackOutcome = $this->handlePaystackLinkAction($order, $actor, $paymentAction);
            if (!empty($paystackOutcome['meta'])) {
                $order->meta_json = $paystackOutcome['meta'];
                $order->save();
            }
            if (!empty($paystackOutcome['payment_link'])) {
                $order->payment_link = $paystackOutcome['payment_link'];
                $order->save();
            }
            $copyPayload = $paystackOutcome['copy'] ?? null;
        } elseif (in_array($paymentAction, ['send_instructions', 'send_mpesa_instructions', 'send_reminder', 'resend_link'], true)) {
            $sendOutcome = $this->sendPaymentWorkflowMessage($order, $paymentAction, $actor, $newLink);
            if (!empty($sendOutcome['meta'])) {
                $order->meta_json = $sendOutcome['meta'];
                $order->save();
            }
            if (!empty($sendOutcome['copy'])) {
                $copyPayload = $sendOutcome['copy'];
            }
        } elseif ($paymentAction === 'generate_paystack_link') {
            $paystackOutcome = $this->handlePaystackLinkAction($order, $actor, $paymentAction);
            if (!empty($paystackOutcome['meta'])) {
                $order->meta_json = $paystackOutcome['meta'];
                $order->save();
            }
            if (!empty($paystackOutcome['payment_link'])) {
                $order->payment_link = $paystackOutcome['payment_link'];
                $order->save();
            }
            $copyPayload = $paystackOutcome['copy'] ?? null;
        } elseif ($paymentAction === 'send_daraja_stk') {
            $stkOutcome = $this->sendDarajaSandboxStk($order, $actor);
            if (!empty($stkOutcome['meta'])) {
                $order->meta_json = $stkOutcome['meta'];
                $order->save();
            }
            $copyPayload = $stkOutcome['copy'] ?? null;
        } else {
            // DM the BUYER directly for explicit state changes after the
            // payment metadata is saved so the customer sees the latest status.
            $this->notifyBuyer($order, $statusChanged, $newLink, $paymentAction);
        }

        $redirect = redirect()->route('user.store.orders.show', $order->id)
            ->with('status', $copyPayload ? ($copyPayload['status'] ?? 'Order updated.') : 'Order updated.');
        if ($copyPayload && !empty($copyPayload['text'])) {
            $redirect->with('zana_payment_copy', $copyPayload);
        }

        return $redirect;
    }

    /**
     * WhatsApp the buyer their order-status change and/or payment link directly.
     * Covers BOTH normal (1v1) and group orders — the group post is visibility,
     * the DM is the buyer's personal copy (a payment link can't live only in a
     * group). Localized to the customer's saved language. Best-effort; a send
     * failure never blocks the status save.
     */
    private function notifyBuyer(WaOrder $order, bool $statusChanged, string $paymentLink, string $paymentAction = ''): void
    {
        $phone = trim((string) $order->customer_phone);
        if ($phone === '' || (!$statusChanged && $paymentLink === '' && $paymentAction === '')) return;

        $lang = is_array($order->meta_json) ? ($order->meta_json['customer_lang'] ?? null) : null;
        $svc  = app(\App\Services\Ordering\OrderingService::class);
        $storefront = $order->storefront()->first();
        $manualInstructions = ZanaAfricaPayments::instructionsText($storefront, $order);
        $externalPaymentLink = ZanaAfricaPayments::externalPaymentLink($storefront, $order);

        $lines = [];
        if (in_array($paymentAction, ['send_instructions', 'send_reminder'], true) && $manualInstructions) {
            $prefix = $paymentAction === 'send_reminder'
                ? $svc->localizeTo("Friendly reminder: payment is still pending for order #{$order->id}.", $lang)
                : $svc->localizeTo("Here are your payment instructions for order #{$order->id}.", $lang);
            $lines[] = $prefix . "\n" . $manualInstructions;
        } elseif ($paymentAction === 'customer_says_paid') {
            $lines[] = $svc->localizeTo("Thanks — we've marked your order #{$order->id} as customer-paid and we're verifying the payment now.", $lang);
        } elseif ($paymentAction === 'paid_confirmed') {
            $lines[] = $svc->localizeTo("Payment confirmed for order #{$order->id}. We'll continue processing it now.", $lang);
        } elseif ($paymentAction === 'payment_failed') {
            $lines[] = $svc->localizeTo("We could not confirm payment for order #{$order->id}. Please reply if you need fresh payment instructions.", $lang);
        } elseif ($statusChanged) {
            $lines[] = $svc->localizeTo("Your order #{$order->id} is now: " . ucfirst((string) $order->status), $lang);
        }
        if ($paymentLink !== '') {
            $lines[] = $svc->localizeTo("Here's your payment link for {$order->total_display}:", $lang) . "\n" . $paymentLink;
        } elseif ($paymentAction === 'resend_link' && $externalPaymentLink) {
            $lines[] = $svc->localizeTo("Here's your payment link for {$order->total_display}:", $lang) . "\n" . $externalPaymentLink;
        }
        $body = trim(implode("\n\n", $lines));
        if ($body === '') return;

        try {
            $msg = \App\Models\Message::create([
                'user_id'   => Auth::id(),
                'direction' => 'out',
                'to_number' => $phone,
                'body'      => $body,
                'status'    => 'pending',
            ]);
            $result = $this->dispatcher->send($msg);
            $msg->status         = ($result['ok'] ?? false) ? 'sent' : 'failed';
            $msg->failure_reason = $result['error'] ?? null;
            $msg->sent_at        = $msg->status === 'sent' ? now() : null;
            $msg->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[ORDER] buyer status/payment DM failed: ' . $e->getMessage());
        }
    }

    public function sendPaymentLink(Request $request, int $id): JsonResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        $data = $request->validate(['payment_link' => 'required|url|max:1024']);

        $order->payment_link = $data['payment_link'];
        $order->save();

        $body = "Hi {$order->customer_name}, here's your payment link for *{$order->total_display}*:\n\n{$data['payment_link']}";
        $msg = \App\Models\Message::create([
            'user_id'     => Auth::id(),
            'direction'   => 'out',
            'to_number'   => $order->customer_phone,
            'body'        => $body,
            'status'      => 'pending',
            'workspace_id'=> $wsId,
        ]);
        $result = $this->dispatcher->send($msg);
        $nativeOk = (bool) ($result['ok'] ?? false) && empty($result['local_only']);
        $msg->status = $nativeOk ? 'sent' : 'failed';
        $msg->failure_reason = $result['error'] ?? null;
        $msg->sent_at = $msg->status === 'sent' ? now() : null;
        if (!empty($result['provider_id'])) {
            $existingMeta = is_array($msg->meta) ? $msg->meta : [];
            $msg->meta = array_merge($existingMeta, ['wa_message_id' => (string) $result['provider_id']]);
        }
        $msg->save();

        $actor = Auth::user();
        $eventType = $nativeOk ? 'payment_link_sent' : 'payment_link_copied';
        $meta = ZanaManualPayment::mergeIntoOrder($order, [
            'status' => 'payment_link_sent',
            'last_send_channel' => $nativeOk ? 'whatsapp' : 'copy',
            'last_send_result' => $nativeOk ? 'sent' : 'fallback_copy',
            'last_send_error' => $result['error'] ?? null,
            'last_payment_message' => $body,
            'last_payment_message_at' => now()->toIso8601String(),
            'last_payment_message_type' => 'payment_link',
            'last_payment_link' => $data['payment_link'],
        ], $actor, $eventType, [
            'message_id' => $msg->id,
            'send_platform' => $result['platform'] ?? null,
            'send_error' => $result['error'] ?? null,
        ]);
        $order->meta_json = $meta;
        $order->save();

        return response()->json([
            'ok' => $nativeOk,
            'message_id' => $msg->id,
            'fallback_text' => $nativeOk ? null : $body,
            'error' => $nativeOk ? null : ($result['error'] ?? 'Native send unavailable'),
        ]);
    }

    /**
     * S2 — mint a REAL Razorpay payment link with the merchant's own keys
     * (storefront settings) and WhatsApp it to the customer. Falls back with
     * a helpful message if API keys aren't configured.
     */
    public function generatePaymentLink(int $id): JsonResponse
    {
        $wsId  = Auth::user()->current_workspace_id;
        $order = WaOrder::forWorkspace($wsId)->findOrFail($id);
        $sf    = \App\Models\WaStorefront::where('workspace_id', $wsId)
            ->when($order->storefront_id, fn ($q) => $q->where('id', $order->storefront_id))
            ->first();

        $pay = app(\App\Services\Storefront\StorefrontPaymentService::class);
        if (!$sf || !$pay->supportsLinks($sf)) {
            return response()->json([
                'ok' => false,
                'message' => 'Add your Razorpay API keys in Storefront settings (Payment) to auto-generate links, or paste one manually.',
            ], 422);
        }

        $callback = ($sf->custom_domain_verified && $sf->custom_domain ? 'https://' . $sf->custom_domain : url('/s/' . $sf->slug))
            . '/order/' . $order->recovery_token;
        $link = $pay->mintLink($sf, $order, $callback);
        if (!$link || empty($link['url'])) {
            return response()->json(['ok' => false, 'message' => 'Could not create the payment link. Check your Razorpay keys.'], 502);
        }

        $order->forceFill([
            'payment_link' => $link['url'],
            'meta_json'    => array_merge(is_array($order->meta_json) ? $order->meta_json : [], ['payment_link_id' => $link['id'] ?? null]),
        ])->save();

        // Deliver via WhatsApp (same path as a manual link).
        $body = "Hi {$order->customer_name}, here's your secure payment link for *{$order->total_display}*:\n\n{$link['url']}";
        $msg = \App\Models\Message::create([
            'user_id'   => Auth::id(),
            'direction' => 'out',
            'to_number' => $order->customer_phone,
            'body'      => $body,
            'status'    => 'pending',
        ]);
        $result = $this->dispatcher->send($msg);
        $msg->status = ($result['ok'] ?? false) ? 'sent' : 'failed';
        $msg->failure_reason = $result['error'] ?? null;
        $msg->sent_at = $msg->status === 'sent' ? now() : null;
        if (!empty($result['provider_id'])) {
            $existingMeta = is_array($msg->meta) ? $msg->meta : [];
            $msg->meta = array_merge($existingMeta, ['wa_message_id' => (string) $result['provider_id']]);
        }
        $msg->save();

        return response()->json(['ok' => true, 'url' => $link['url'], 'sent' => $msg->status === 'sent']);
    }

    public function destroy(int $id): RedirectResponse
    {
        $wsId = Auth::user()->current_workspace_id;
        WaOrder::forWorkspace($wsId)->findOrFail($id)->delete();
        return redirect()->route('user.store.orders.index')->with('status', 'Order removed.');
    }

    private function handlePaystackLinkAction(WaOrder $order, $actor, string $paymentAction): array
    {
        $storefront = $order->storefront()->first();
        $result = $storefront
            ? ZanaPaystackMerchantLink::initializeForOrder($storefront, $order)
            : ['ok' => false, 'error' => 'No storefront is connected to this order yet.'];

        if (!($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'Paystack link generation failed.');
            $meta = ZanaManualPayment::mergeIntoOrder($order, [
                'payment_method' => 'payment_link',
                'last_send_channel' => 'copy',
                'last_send_result' => 'generation_failed',
                'last_send_error' => $error,
                'paystack' => [
                    'status' => 'generation_failed',
                    'last_error' => $error,
                    'attempted_at' => now()->toIso8601String(),
                ],
            ], $actor, 'paystack_link_generation_failed', [
                'provider' => 'paystack',
                'send_error' => $error,
            ]);

            return [
                'meta' => $meta,
                'copy' => [
                    'label' => null,
                    'text' => null,
                    'status' => $error,
                ],
            ];
        }

        $link = (string) ($result['url'] ?? '');
        $meta = ZanaManualPayment::mergeIntoOrder($order, [
            'status' => 'payment_link_sent',
            'payment_method' => 'payment_link',
            'last_payment_link' => $link,
            'paystack' => [
                'status' => 'link_generated',
                'provider' => 'paystack',
                'reference' => $result['reference'] ?? null,
                'access_code' => $result['access_code'] ?? null,
                'currency' => $result['currency'] ?? null,
                'amount_minor' => $result['amount_minor'] ?? null,
                'email_used' => $result['email_used'] ?? null,
                'redirect_url' => $result['redirect_url'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
        ], $actor, 'paystack_link_generated', [
            'provider' => 'paystack',
            'payment_link' => $link,
            'transaction_reference' => $result['reference'] ?? null,
        ]);

        if ($paymentAction === 'generate_paystack_link_send') {
            $order->payment_link = $link;
            $order->meta_json = $meta;
            $sendOutcome = $this->sendPaymentWorkflowMessage($order, 'generate_paystack_link_send', $actor, $link);

            return [
                'payment_link' => $link,
                'meta' => $sendOutcome['meta'] ?? $meta,
                'copy' => $sendOutcome['copy'] ?? null,
            ];
        }

        return [
            'payment_link' => $link,
            'meta' => $meta,
            'copy' => [
                'label' => 'Copy Paystack payment link',
                'text' => $link,
                'status' => 'Paystack link generated. Copy or send it from this order.',
            ],
        ];
    }

    private function sendDarajaSandboxStk(WaOrder $order, $actor): array
    {
        $storefront = $order->storefront()->first();
        $result = ZanaDarajaSandbox::initiateForOrder($storefront, $order);

        if (!($result['ok'] ?? false)) {
            $error = (string) ($result['error'] ?? 'Daraja sandbox STK initiation failed.');
            $meta = ZanaManualPayment::mergeIntoOrder($order, [
                'payment_method' => 'daraja_stk',
                'daraja' => [
                    'status' => 'initiation_failed',
                    'last_error' => $error,
                    'request_attempted_at' => now()->toIso8601String(),
                ],
            ], $actor, 'daraja_stk_initiation_failed', [
                'send_error' => $error,
            ]);

            return [
                'meta' => $meta,
                'copy' => [
                    'label' => null,
                    'text' => null,
                    'status' => $error . ' Manual M-Pesa instructions are still available for this order.',
                ],
            ];
        }

        $meta = ZanaManualPayment::mergeIntoOrder($order, [
            'status' => 'awaiting_payment',
            'payment_method' => 'daraja_stk',
            'daraja' => [
                'status' => 'initiated',
                'environment' => 'sandbox',
                'merchant_request_id' => $result['merchant_request_id'] ?? null,
                'checkout_request_id' => $result['checkout_request_id'] ?? null,
                'request_phone' => $result['request_phone'] ?? null,
                'request_amount' => $result['request_amount'] ?? null,
                'request_reference' => $result['request_reference'] ?? null,
                'request_attempted_at' => now()->toIso8601String(),
                'response_code' => $result['response_code'] ?? null,
                'response_description' => $result['response_description'] ?? null,
                'customer_message' => $result['customer_message'] ?? null,
                'callback_url' => $result['callback_url'] ?? null,
            ],
        ], $actor, 'daraja_stk_initiated', [
            'merchant_request_id' => $result['merchant_request_id'] ?? null,
            'checkout_request_id' => $result['checkout_request_id'] ?? null,
        ]);

        return [
            'meta' => $meta,
            'copy' => [
                'label' => null,
                'text' => null,
                'status' => 'Daraja sandbox STK push initiated. Wait for the callback or continue with manual confirmation if needed.',
            ],
        ];
    }

    private function sendPaymentWorkflowMessage(WaOrder $order, string $paymentAction, $actor, string $paymentLink = ''): array
    {
        [$body, $messageType] = $this->buildPaymentWorkflowMessage($order, $paymentAction, $paymentLink);
        $body = trim($body);
        if ($body === '') {
            return [
                'copy' => [
                    'label' => 'Copy payment message',
                    'text' => '',
                    'status' => 'No payment message could be prepared for this order yet.',
                ],
            ];
        }

        $eventMap = [
            'send_instructions' => ['success' => 'payment_instructions_sent', 'fallback' => 'payment_instructions_copied', 'template_success' => 'payment_instructions_template_sent', 'template_failed' => 'payment_instructions_template_failed'],
            'send_mpesa_instructions' => ['success' => 'payment_mpesa_instructions_sent', 'fallback' => 'payment_mpesa_instructions_copied', 'template_success' => 'payment_mpesa_instructions_template_sent', 'template_failed' => 'payment_mpesa_instructions_template_failed'],
            'send_reminder' => ['success' => 'payment_reminder_sent', 'fallback' => 'payment_reminder_copied', 'template_success' => 'payment_reminder_template_sent', 'template_failed' => 'payment_reminder_template_failed'],
            'resend_link' => ['success' => 'payment_link_sent', 'fallback' => 'payment_link_copied', 'template_success' => 'payment_link_template_sent', 'template_failed' => 'payment_link_template_failed'],
            'generate_paystack_link_send' => ['success' => 'paystack_link_sent', 'fallback' => 'paystack_link_copied', 'template_success' => 'paystack_link_template_sent', 'template_failed' => 'paystack_link_template_failed'],
        ];
        $eventType = $eventMap[$paymentAction] ?? ['success' => 'payment_details_updated', 'fallback' => 'payment_details_updated'];
        $phone = trim((string) $order->customer_phone);

        if ($phone === '') {
            $meta = ZanaManualPayment::mergeIntoOrder($order, [
                'last_send_channel' => 'copy',
                'last_send_result' => 'missing_phone',
                'last_send_error' => 'Missing customer phone on this order.',
                'last_payment_message' => $body,
                'last_payment_message_at' => now()->toIso8601String(),
                'last_payment_message_type' => $messageType,
                'last_payment_link' => $paymentLink !== '' ? $paymentLink : ZanaAfricaPayments::externalPaymentLink($order->storefront()->first(), $order),
            ], $actor, $eventType['fallback'], [
                'send_error' => 'Missing customer phone on this order.',
            ]);

            return [
                'meta' => $meta,
                'copy' => [
                    'label' => $messageType === 'payment_reminder' ? 'Copy payment reminder' : 'Copy payment instructions',
                    'text' => $body,
                    'status' => 'WhatsApp send is unavailable because this order has no customer phone. Copy the message below instead.',
                ],
            ];
        }

        $msg = Message::create([
            'user_id' => Auth::id(),
            'workspace_id' => (int) $order->workspace_id,
            'direction' => 'out',
            'to_number' => $phone,
            'body' => $body,
            'status' => 'pending',
            'meta' => [
                'zana_manual_payment_message' => true,
                'zana_manual_payment_type' => $messageType,
                'zana_order_id' => $order->id,
            ],
        ]);
        $result = $this->dispatcher->send($msg);
        $nativeOk = (bool) ($result['ok'] ?? false) && empty($result['local_only']);
        $msg->status = $nativeOk ? 'sent' : 'failed';
        $msg->failure_reason = $nativeOk ? null : (($result['error'] ?? null) ?: (($result['local_only'] ?? false) ? 'Native send unavailable in this workspace.' : null));
        $msg->sent_at = $nativeOk ? now() : null;
        if (!empty($result['provider_id'])) {
            $existingMeta = is_array($msg->meta) ? $msg->meta : [];
            $msg->meta = array_merge($existingMeta, ['wa_message_id' => (string) $result['provider_id']]);
        }
        $msg->save();

        if (!$nativeOk && ZanaPaymentTemplateFallback::shouldUseTemplateFallback($result)) {
            $templateResult = ZanaPaymentTemplateFallback::sendForOrder($order, $paymentAction, $body, $paymentLink);
            $templateOk = (bool) ($templateResult['ok'] ?? false);
            if ($templateOk && !empty($templateResult['wamid'])) {
                $existingMeta = is_array($msg->meta) ? $msg->meta : [];
                $msg->meta = array_merge($existingMeta, ['wa_message_id' => (string) $templateResult['wamid']]);
                $msg->status = 'sent';
                $msg->failure_reason = null;
                $msg->sent_at = $msg->sent_at ?: now();
                $msg->save();
            }
            $templateMeta = ZanaManualPayment::mergeIntoOrder($order, [
                'status' => match ($paymentAction) {
                    'send_reminder' => 'payment_reminder_sent',
                    'resend_link' => 'payment_link_sent',
                    'generate_paystack_link_send' => 'payment_link_sent',
                    default => 'awaiting_payment',
                },
                'last_send_channel' => $templateOk ? 'waba_template' : 'copy',
                'last_send_result' => $templateOk ? 'template_fallback_sent' : 'fallback_copy',
                'last_send_error' => $templateOk ? null : (($templateResult['error'] ?? null) ?: ($result['error'] ?? 'Template fallback failed')),
                'last_payment_message' => $body,
                'last_payment_message_at' => now()->toIso8601String(),
                'last_payment_message_type' => $messageType,
                'last_payment_link' => $paymentLink !== '' ? $paymentLink : ZanaAfricaPayments::externalPaymentLink($order->storefront()->first(), $order),
                'last_template_fallback_template_id' => $templateOk ? ($templateResult['template_id'] ?? null) : null,
                'last_template_fallback_template_name' => $templateOk ? ($templateResult['template_name'] ?? null) : null,
                'last_template_fallback_wamid' => $templateOk ? ($templateResult['wamid'] ?? null) : null,
            ], $actor, $templateOk ? $eventType['template_success'] : $eventType['template_failed'], [
                'message_id' => $msg->id,
                'send_platform' => 'waba_template',
                'send_error' => $templateOk ? null : ($templateResult['error'] ?? null),
                'template_id' => $templateResult['template_id'] ?? null,
                'template_name' => $templateResult['template_name'] ?? null,
                'fallback_reason' => '24h_window',
            ]);

            if ($templateOk) {
                return [
                    'meta' => $templateMeta,
                    'copy' => [
                        'label' => null,
                        'text' => null,
                        'status' => match ($paymentAction) {
                            'send_reminder' => 'Payment reminder sent with an approved WhatsApp template because the 24-hour window was closed.',
                            'resend_link' => 'Payment link sent with an approved WhatsApp template because the 24-hour window was closed.',
                            'generate_paystack_link_send' => 'Paystack link sent with an approved WhatsApp template because the 24-hour window was closed.',
                            default => 'Payment instructions sent with an approved WhatsApp template because the 24-hour window was closed.',
                        },
                    ],
                ];
            }

            if (($templateResult['reason'] ?? '') === 'not_configured') {
                $requiredEvent = match ($paymentAction) {
                    'send_reminder' => 'payment_reminder_template_required',
                    'resend_link' => 'payment_link_template_required',
                    'generate_paystack_link_send' => 'paystack_link_template_required',
                    default => 'payment_instructions_template_required',
                };
                if ($paymentAction === 'send_mpesa_instructions') {
                    $requiredEvent = 'payment_instructions_template_required';
                }
                $meta = ZanaManualPayment::mergeIntoOrder($order, [
                    'last_send_channel' => 'copy',
                    'last_send_result' => 'template_required_not_configured',
                    'last_send_error' => $templateResult['error'] ?? 'Template required but not configured.',
                    'last_payment_message' => $body,
                    'last_payment_message_at' => now()->toIso8601String(),
                    'last_payment_message_type' => $messageType,
                ], $actor, $requiredEvent, [
                    'message_id' => $msg->id,
                    'send_error' => $templateResult['error'] ?? null,
                    'fallback_reason' => '24h_window',
                ]);

                return [
                    'meta' => $meta,
                    'copy' => [
                        'label' => match ($paymentAction) {
                            'send_reminder' => 'Copy payment reminder',
                            'generate_paystack_link_send' => 'Copy Paystack payment link message',
                            default => 'Copy payment instructions',
                        },
                        'text' => $body,
                        'status' => 'Template required but not configured. Copy the payment message below instead.',
                    ],
                ];
            }
        }

        $meta = ZanaManualPayment::mergeIntoOrder($order, [
            'status' => match ($paymentAction) {
                'send_reminder' => 'payment_reminder_sent',
                'resend_link' => 'payment_link_sent',
                'generate_paystack_link_send' => 'payment_link_sent',
                default => 'awaiting_payment',
            },
            'last_send_channel' => $nativeOk ? 'whatsapp' : 'copy',
            'last_send_result' => $nativeOk ? 'sent' : ((ZanaPaymentTemplateFallback::shouldUseTemplateFallback($result) && ZanaPaymentTemplateFallback::requiresTemplateButIsNotConfigured($order, $paymentAction)) ? 'template_required_not_configured' : 'fallback_copy'),
            'last_send_error' => $nativeOk ? null : ($result['error'] ?? (($result['local_only'] ?? false) ? 'Native send unavailable in this workspace.' : 'Send failed')),
            'last_payment_message' => $body,
            'last_payment_message_at' => now()->toIso8601String(),
            'last_payment_message_type' => $messageType,
            'last_payment_link' => $paymentLink !== '' ? $paymentLink : ZanaAfricaPayments::externalPaymentLink($order->storefront()->first(), $order),
        ], $actor, $nativeOk ? $eventType['success'] : $eventType['fallback'], [
            'message_id' => $msg->id,
            'send_platform' => $result['platform'] ?? null,
            'send_error' => $result['error'] ?? null,
        ]);

        if ($nativeOk) {
            return [
                'meta' => $meta,
                'copy' => [
                    'label' => null,
                    'text' => null,
                    'status' => match ($paymentAction) {
                        'send_reminder' => 'Payment reminder sent on WhatsApp.',
                        'resend_link' => 'Payment link sent on WhatsApp.',
                        'generate_paystack_link_send' => 'Paystack link sent on WhatsApp.',
                        default => 'Payment instructions sent on WhatsApp.',
                    },
                ],
            ];
        }

        $fallbackReason = ZanaPaymentTemplateFallback::shouldUseTemplateFallback($result)
            ? 'The 24-hour window was closed and no compliant template send succeeded.'
            : 'WhatsApp send was unavailable.';

        return [
            'meta' => $meta,
            'copy' => [
                'label' => match ($paymentAction) {
                    'send_reminder' => 'Copy payment reminder',
                    'resend_link' => 'Copy payment link message',
                    'generate_paystack_link_send' => 'Copy Paystack payment link message',
                    'send_mpesa_instructions' => 'Copy M-Pesa instructions',
                    default => 'Copy payment instructions',
                },
                'text' => $body,
                'status' => match ($paymentAction) {
                    'send_reminder' => $fallbackReason . ' Copy the payment reminder below instead.',
                    'resend_link' => $fallbackReason . ' Copy the payment link message below instead.',
                    'generate_paystack_link_send' => $fallbackReason . ' Copy the Paystack payment link message below instead.',
                    'send_mpesa_instructions' => $fallbackReason . ' Copy the M-Pesa instructions below instead.',
                    default => $fallbackReason . ' Copy the payment instructions below instead.',
                },
            ],
        ];
    }

    private function buildPaymentWorkflowMessage(WaOrder $order, string $paymentAction, string $paymentLink = ''): array
    {
        $storefront = $order->storefront()->first();
        $instructions = ZanaAfricaPayments::instructionsText($storefront, $order) ?? '';
        $mpesaInstructions = ZanaKenyaPaymentShortcut::instructionText($storefront, $order) ?? $instructions;
        $externalPaymentLink = $paymentLink !== '' ? $paymentLink : (ZanaAfricaPayments::externalPaymentLink($storefront, $order) ?? '');

        return match ($paymentAction) {
            'send_reminder' => [trim("Friendly reminder: payment is still pending for order #{$order->id} ({$order->total_display}).\n\n{$instructions}"), 'payment_reminder'],
            'send_mpesa_instructions' => [trim($mpesaInstructions), 'payment_mpesa_instructions'],
            'resend_link' => [trim("Here is your payment link again for order #{$order->id} ({$order->total_display}).\n\n{$externalPaymentLink}"), 'payment_link'],
            'generate_paystack_link_send' => [trim("Here is your Paystack payment link for order #{$order->id} ({$order->total_display}).\n\n{$externalPaymentLink}"), 'payment_link'],
            default => [trim("Here are your payment instructions for order #{$order->id} ({$order->total_display}).\n\n{$instructions}"), 'payment_instructions'],
        };
    }
}
