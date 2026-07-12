# Zana Africa Merchant Payment Flow

## Summary
The safest Africa-first launch path is still storefront + orders + payment request + payment follow-up. Zana should not market `/store/payments` as the primary merchant payment experience for Africa yet. The order detail screen and storefront payment setup now provide the commercial base for Kenya-first rollout.

## Mode 1 — Manual payment instruction

| Setting | Needed for | Existing support? | Where set? | Notes |
|---|---|---|---|---|
| Business payment name | M-Pesa and bank instruction identity | Yes | [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php) | Stored in `wa_storefronts.payment_config_json.mpesa_business_name` |
| Till / Paybill | Kenya manual payment instructions | Yes | Storefront edit | Stored in `payment_config_json` |
| Bank transfer instructions | Manual transfer path | Yes | Storefront edit | Free-text instructions |
| Transfer reference format | Reconciliation guidance | Yes | Storefront edit | Merchant-defined |
| Payment instructions template | Reusable order message copy | Yes | Storefront edit | Rendered via [app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php) |
| Supported payment methods text | Customer-facing payment summary | Yes | Storefront edit | Visible fallback exists even when blank |

Flow:
1. Customer orders from storefront or WhatsApp-assisted flow.
2. Order lands in `/store/orders`.
3. Merchant opens order detail and sends manual payment instructions.
4. Customer pays manually by M-Pesa, bank transfer, or another local rail.
5. Merchant marks customer says paid, then manually confirms payment.
6. Zana can follow up again if payment remains incomplete.

## Mode 2 — Manual external payment link

| Payment Link Type | Existing support? | Manual or auto? | Where configured? | Can send from order? | Notes |
|---|---|---|---|---|---|
| Paystack link pasted manually | Yes | Manual | Storefront edit `external_payment_link` or order `payment_link` | Yes | No auto-generation yet |
| Flutterwave link pasted manually | Yes | Manual | Storefront edit or order | Yes | Same reusable path as Paystack |
| Stripe payment link pasted manually | Yes | Manual | Storefront edit or order | Yes | Works as an external URL only |
| PayPal.me or other wallet link | Yes | Manual | Storefront edit or order | Yes | Generic URL path |
| Order-specific payment link | Partial | Manual | Order detail `payment_link` field | Yes | Stored per order |
| Multiple saved payment links | No | Manual | Not modeled natively | Partial | One reusable storefront link plus one per-order override today |

## Mode 3 — Auto payment link later

| Future Gateway | What is needed | Existing seam in code? | Risk level | Best insertion point |
|---|---|---|---|---|
| Paystack generate + send | Merchant secret/public keys, payment-link create API call, order reference, callback verification, status update | Partial | Medium | Add a new gateway branch near [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) payment-link actions and helper service under `app/Services/Storefront` |
| Flutterwave generate + send | Merchant API credentials, hosted payment link creation, callback verification, order-to-payment matching | Partial | Medium | Same order-flow seam as Paystack |
| Generic external-link gateway adapters | Merchant credentials, reusable instruction rendering, optional webhook processor | Partial | Medium | Extend [app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php) and add focused gateway services rather than rewriting storefront checkout |

## Mode 4 — Kenya automation later

| Kenya Automation Need | Required? | Existing support? | Where would it fit? | Notes |
|---|---|---|---|---|
| Merchant short code / Till / Paybill | Yes | Partial | Storefront payment config | Manual fields already exist |
| Daraja consumer key / secret | Later | No | New secure credential storage under storefront payment config or merchant gateway config | Do not add yet |
| Callback URL handling | Later | No | New callback controller/service alongside storefront payment services | Must fail closed and verify origin/signature |
| Reference handling | Yes | Partial | Existing `payment_reference_format` plus future reconciliation service | Start manual, automate later |
| Order-to-payment reconciliation | Later | Partial | `wa_orders.meta_json` plus future payment events table/service | Manual today |
| Automatic confirmation | Later | No | Future Daraja callback processor updating order status | Should reuse the same order payment states already surfaced now |

## Africa MVP Confirmation
This remains the safest launch path:

Kenya merchant MVP:
- storefront
- cart / orders
- M-Pesa Till / Paybill instructions
- manual payment confirmation
- manual or pasted payment links if merchant has them
- payment reminders
- order/payment statuses

Other African markets MVP:
- storefront
- cart / orders
- manual payment instructions
- pasted payment links
- manual confirmation
- payment follow-up

## What To Build Next
1. M-Pesa manual confirmation workflow refinement and reporting.
2. Paystack pasted-link templates and optional merchant-specific shortcuts.
3. Flutterwave pasted-link templates.
4. Daraja/STK/callback automation only after the manual MVP is stable.
