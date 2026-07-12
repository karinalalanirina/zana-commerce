# Zana Africa Storefront Payment Setup

## Summary
The storefront and order detail flow now frame payments the way Zana will launch in Africa: storefront order creation first, then payment request, manual or pasted payment link, and manual confirmation. This keeps the existing storefront/order architecture intact while making merchant-facing setup clearer for M-Pesa, bank transfer, and external payment links.

## Table 1 — Merchant payment settings
| Field | Purpose | Existing storage/support | Where shown | Notes |
|---|---|---|---|---|
| `payment_provider` | Selects the broad payment setup mode | Native `wa_storefronts.payment_provider` | [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php) | Expanded to include manual instructions, external link, bank transfer, cash on delivery, and later gateway paths |
| `payment_handle` | Keeps the legacy primary payment detail/handle field usable | Native `payment_config_json.handle` via storefront edit controller merge | Storefront edit | Useful as a generic reusable detail without schema changes |
| `mpesa_business_name` | Merchant name shown in instructions | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Africa-facing copy only; no automation implied |
| `mpesa_till_number` | M-Pesa Till instructions | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Manual MVP |
| `mpesa_paybill_number` | M-Pesa Paybill instructions | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Manual MVP |
| `payment_reference_format` | Payment/account/reference guidance | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Can be a static instruction or merchant-defined pattern |
| `bank_transfer_instructions` | Bank transfer text block | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Free-text to stay flexible across markets |
| `external_payment_link` | Reusable pasted checkout link | `wa_storefronts.payment_config_json` | Storefront edit and order detail payment link field | Supports manual Paystack/Flutterwave/Stripe/etc. links today |
| `accepted_payment_methods_text` | Merchant-facing accepted methods copy | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Defaults visibly if blank |
| `default_payment_instructions_template` | Reusable payment instructions template | `wa_storefronts.payment_config_json` | Storefront edit and order detail helper output | Supports placeholders without adding a new message-template subsystem |
| `razorpay_key_id`, `razorpay_key_secret`, `razorpay_webhook_secret` | Kept for later India/optional auto-link flow | Existing encrypted storage in `payment_config_json` | Storefront edit under “later / optional” block | Not removed, only deprioritized |

## Table 2 — Order payment actions
| Action | Route/Page | Existing support? | Change made | Notes |
|---|---|---|---|---|
| Send payment instructions | `PUT /store/orders/{id}` on [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Partial | Added `payment_action=send_instructions` mapping to a safe backend status and WhatsApp notification copy | Uses storefront payment setup text |
| Send payment reminder | Same order update route | Partial | Added `payment_action=send_reminder` | Keeps backend intact; only adds merchant-friendly intent |
| Send payment link | Existing `POST /store/orders/{id}/payment-link` plus order action buttons | Yes | Surfaced alongside Africa payment actions | Works when order or storefront has a payment link |
| Resend payment link | Same order update route | Partial | Added `payment_action=resend_link` that reuses saved link copy | No new route required |
| Mark customer says paid | Same order update route | Partial | Added visible action mapped to existing `confirmed` backend status | Good interim state before manual verification |
| Mark paid confirmed | Same order update route | Yes | Added explicit merchant action mapped to `paid` | Matches MVP manual confirmation flow |
| Mark payment failed | Same order update route | Partial | Added explicit merchant action mapped to `cancelled` | Safe placeholder until richer payment states are introduced |
| Generate Razorpay link + send | Existing `POST /store/orders/{id}/generate-payment-link` | Yes | Hidden in Africa-first mode | Kept for later markets, not removed |
| Request WhatsApp Pay in chat | Existing `POST /store/orders/{id}/whatsapp-pay` | Yes | Hidden/blocked for unsupported markets | Route and controller remain |

## Table 3 — MVP payment flow
| Step | Merchant action | Customer action | System state | Notes |
|---|---|---|---|---|
| 1. Order created | Merchant reviews storefront or chat-created order | Customer submits order | Backend order status can remain `new` or be moved to `pending` | No checkout rewrite required |
| 2. Awaiting Payment | Merchant sends payment instructions | Customer receives M-Pesa/bank/link instructions | Visible Zana step `Awaiting Payment` | Mapped safely onto existing status model |
| 3. Payment Link Sent | Merchant sends or resends a saved payment link | Customer opens external link | Visible Zana step `Payment Link Sent` | Stored in `meta_json.zana_payment_step` |
| 4. Customer Says Paid | Merchant records the claim | Customer says they have paid | Visible Zana step `Customer Says Paid` and backend `confirmed` | Manual verification stage |
| 5. Paid Confirmed | Merchant verifies payment manually | Customer is acknowledged | Visible Zana step `Paid Confirmed` and backend `paid` | Launch-safe Kenya MVP |
| 6. Payment Failed | Merchant marks payment failed | Customer can be followed up again later | Visible Zana step `Payment Failed` and backend `cancelled` | Can later evolve into richer failure/retry handling |

## Files Touched
- [app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php)
- [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)
