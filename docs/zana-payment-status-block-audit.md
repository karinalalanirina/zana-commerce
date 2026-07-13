## Summary
This audit traced the existing payment signals already available on `/store/orders/{id}` so the compact status block could be built without new routes, schema changes, or payment-flow rewrites.

| Payment signal | Exists? | Source file/model/meta | Can reuse directly? | Notes |
|---|---|---|---|---|
| Merchant payment status | Yes | `App\Support\ZanaManualPayment::paymentStatus()` from `wa_orders.meta_json.zana_manual_payment.status` | Yes | Source of truth for Awaiting Payment, Customer Says Paid, Paid Confirmed, Failed, Refunded. |
| Payment method/rail | Yes | `wa_orders.meta_json.zana_manual_payment.payment_method` | Yes | Supports M-Pesa, Daraja STK, bank transfer, payment link, cash, other. |
| Transaction reference | Yes | `wa_orders.meta_json.zana_manual_payment.transaction_reference` | Yes | Reused for quick reference display and verification cues. |
| Amount received | Yes | `wa_orders.meta_json.zana_manual_payment.amount_received` | Yes | Already formatted via `ZanaManualPayment::amountReceivedDisplay()`. |
| Paystack link state | Yes | `wa_orders.meta_json.zana_manual_payment.paystack.status` | Yes | Values already include `link_generated`, `confirmed`, `awaiting_verification`, `failed`. |
| Paystack callback receipt | Yes | `wa_orders.meta_json.zana_manual_payment.paystack.callback_received_at` | Yes | Safe to summarize as callback received when present. |
| Paystack amount match | Yes | `wa_orders.meta_json.zana_manual_payment.paystack.amount_matches_order` | Yes | Stored as `yes` or `no` by signed callback handling. |
| Daraja STK initiation | Yes | `wa_orders.meta_json.zana_manual_payment.daraja.status` | Yes | Current scaffold stores `initiated`, `callback_success`, `callback_failed`, `initiation_failed`. |
| Daraja callback receipt | Yes | `wa_orders.meta_json.zana_manual_payment.daraja.callback_received_at` | Yes | Safe to summarize when callback exists. |
| Verification state | Yes | `App\Support\ZanaPaymentVerification` | Yes | Reused to support next-action cues such as review reference. |
| WhatsApp send state | Yes | `App\Support\ZanaManualPayment::timeline()` plus `last_send_result` | Yes | Existing timeline already derives Submitted, Delivered, Read, Failed, Copied instead, Template required. |

| Summary item we want | Existing source available? | Best safe derivation approach | Notes |
|---|---|---|---|
| Payment rail/method | Yes | Derive from `payment_method`, but prefer Paystack or Daraja when their metadata exists | Avoids showing generic “Payment Link” when the active rail is Paystack. |
| Current status | Yes | Reuse `ZanaManualPayment::statusLabel()` | No guessing needed. |
| Provider/request state | Yes | Map Paystack/Daraja metadata to short merchant labels | Kept intentionally compact. |
| Amount check | Partial | Use Paystack `amount_matches_order`; for Daraja only show known callback amount capture | Do not fabricate exact-match claims for Daraja/manual rails. |
| Send state | Yes | Prefer latest timeline delivery label, fallback to `last_send_result` | Preserves honesty when only copy/template fallback is known. |
| Next recommended action | Partial | Safely derive from current status + provider state + send state | Pure presenter logic; no backend workflow change. |
