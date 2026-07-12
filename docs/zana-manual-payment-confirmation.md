# Zana Manual Payment Confirmation

## Summary
Zana now supports a structured manual payment confirmation workflow on `/store/orders/{id}` without changing schema or billing logic. Merchants can record payment status, method, reference, amount received, payer note, and confirmation notes, while keeping backend-compatible order statuses intact.

## Goal
Make manual customer-payment handling trustworthy and operational for Kenya/Africa MVP merchants using the existing storefront + order flow.

## Files changed
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)
- [resources/views/user/store/orders/index.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php)
- [tests/Feature/ZanaManualPaymentWorkflowTest.php](/Users/karinachanmane/Projects/zana/zana-commerce/tests/Feature/ZanaManualPaymentWorkflowTest.php)

## Files not changed
- Subscription billing gateways
- Checkout architecture
- Storefront checkout route design
- Database schema / migrations
- Admin payment gateway configuration

## Storage approach used
Structured manual payment data is stored in `wa_orders.meta_json` under:
- `zana_manual_payment`
- `zana_payment_events`

This avoids schema changes and keeps the implementation update-safe.

## Order payment flow
1. Order is created.
2. Merchant opens `/store/orders/{id}`.
3. Merchant can send instructions or reminder, or resend a saved payment link.
4. Merchant records what the customer said and what payment evidence exists.
5. Merchant marks customer says paid or paid confirmed.
6. Payment history/timeline is visible on the same page.

## Merchant-visible statuses
- Awaiting Payment
- Payment Link Sent
- Payment Reminder Sent
- Customer Says Paid
- Paid Confirmed
- Payment Failed
- Refunded

These map safely onto current backend order statuses instead of replacing them.

## Payment history behavior
The order page now keeps a lightweight timeline inside order metadata. It records events such as:
- payment instructions sent or copied
- payment reminder sent or copied
- payment link sent or copied
- customer says paid
- reference recorded
- paid confirmed
- payment failed
- refunded

## Known limitations
- No automatic M-Pesa confirmation yet
- No Daraja yet
- No Paystack/Flutterwave auto-generation yet
- Timeline is order-meta based, not a dedicated reporting table

## How to test
1. Log in as `zuri.owner@example.com / password`
2. Open `/store/orders`
3. Open an order detail page
4. Fill the manual payment confirmation fields
5. Mark `Customer Says Paid`
6. Save and confirm the payment history updates
7. Mark `Paid Confirmed`
8. Re-open the order list and verify payment-state visibility

## Table 1 — Payment confirmation fields
| Field | Purpose | Where stored | Notes |
|---|---|---|---|
| Merchant payment status | Merchant-friendly payment state | `meta_json.zana_manual_payment.status` | Backend order status stays separately mapped |
| Payment method | Shows how customer paid | `meta_json.zana_manual_payment.payment_method` | M-Pesa / bank / link / cash / other |
| Transaction/reference code | Tracks payment evidence | `meta_json.zana_manual_payment.transaction_reference` | Searchable on the order list |
| Amount received | Captures manual reconciliation amount | `meta_json.zana_manual_payment.amount_received` | Stored without migration |
| Payer phone/name/note | Human clue for reconciliation | `meta_json.zana_manual_payment.payer_note` | Free text |
| Internal confirmation note | Merchant-only note | `meta_json.zana_manual_payment.confirmation_note` | Free text |
| Customer says paid stamp | Shows when claim was recorded | `meta_json.zana_manual_payment.customer_says_paid_at/by` | Set from action |
| Confirmed by / confirmed at | Shows verification ownership | `meta_json.zana_manual_payment.confirmed_at/by` | Set from action |

## Table 2 — Payment event history
| Event | Trigger | Where shown | Notes |
|---|---|---|---|
| Payment instructions sent | Native WhatsApp send succeeds | Order detail payment history | Uses existing dispatcher |
| Payment instructions copied instead | Native send unavailable | Order detail payment history + copy fallback block | Safe manual fallback |
| Payment reminder sent | Native reminder send succeeds | Order detail payment history | Reuses same path |
| Payment reminder copied instead | Native reminder send unavailable | Order detail payment history + copy fallback block | Safe manual fallback |
| Customer says paid | Merchant clicks action | Order detail payment history | Also stamps actor/time |
| Reference recorded | Reference value added/changed | Order detail payment history | Structured and searchable |
| Paid confirmed | Merchant clicks action | Order detail payment history | Maps to backend `paid` |
| Payment failed | Merchant clicks action | Order detail payment history | Maps to backend `cancelled` |
| Refunded | Merchant clicks action | Order detail payment history | Merchant-visible only, mapped safely |

## Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | Zana-specific payment metadata/timeline helper | No, new file | Low | Yes |
| [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Reused existing order update/send seam | Yes | Medium | Kept route/controller changes narrow |
| [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Merchant payment form, copy fallback, timeline | Yes | Medium | Added onto existing page instead of new route/page |
| [resources/views/user/store/orders/index.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php) | Payment-state visibility/reporting basics | Yes | Medium | Small page-level additions only |
