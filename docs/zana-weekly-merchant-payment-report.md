## Summary
The weekly merchant payment report remains intentionally compact, but it now shows more operator-ready context on `/store/orders`.

## Goal
Give merchants a clearer weekly/30-day payment picture without creating a separate analytics system.

## Reporting scope included
The report now includes payment-state counts, confirmed totals, amount awaiting verification, payment method breakdown, and recent payment activity.

## Existing data reused
All metrics are derived from `wa_orders` plus `meta_json.zana_manual_payment`, existing verification helpers, and existing order timestamps.

## Files changed
- `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaWeeklyPaymentReport.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php`

## Files not changed
- Reporting routes
- Analytics engine
- Chart libraries
- Billing/checkout systems

## Known limitations
Totals are only as reliable as the existing manual-confirmation data. “Amount awaiting verification” is derived from received amount when present, otherwise from order total.

## How to test
Open `/store/orders`, switch between `Last 7 days` and `Last 30 days`, confirm counts/totals change, and verify that recent payment activity and method breakdown render safely.

Table 1 — Weekly report items
| Item | Added? | Where shown | How calculated | Notes |
|---|---|---|---|---|
| 7-day block | Yes | Orders summary area | Existing orders updated within 7 days | |
| 30-day block | Yes | Orders summary area | Existing orders updated within 30 days | |
| Confirmed total | Yes | Orders summary area | Sum of confirmed amount or order total fallback | |
| Amount awaiting verification | Yes | Orders summary area | Sum of awaiting-verification received amount or order total fallback | |
| Payment method breakdown | Yes | Orders summary area | Count by stored `payment_method` | |
| Recent payment activity | Yes | Orders summary area | Most recently updated payment-related orders in selected window | |

Table 2 — Reporting coverage
| Metric | Included? | Source | Notes |
|---|---|---|---|
| Awaiting Payment | Yes | `zana_manual_payment.status` | |
| Customer Says Paid | Yes | `zana_manual_payment.status` | |
| Awaiting Verification | Yes | `ZanaPaymentVerification::needsVerification()` | Derived merchant-facing state |
| Paid Confirmed | Yes | `zana_manual_payment.status` | |
| Payment Failed | Yes | `zana_manual_payment.status` | |
| Refunded | Yes | `zana_manual_payment.status` | |
| Confirmed amount total | Yes | `amount_received` or order total fallback | |
| Method breakdown | Yes | `payment_method` | Only non-zero buckets shown |

Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaWeeklyPaymentReport.php` | Encapsulate weekly report logic | Yes | Low | New Zana-specific helper |
| `app/Http/Controllers/WaOrderController.php` | Feed the orders page report block | Yes | Medium | Reused existing orders page |
| `resources/views/user/store/orders/index.blade.php` | Render report details | Yes | Medium | No new routes or report modules |
