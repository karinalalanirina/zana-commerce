## Summary
This pass improves merchant verification operations on the existing order pages without changing routes, schema, billing, or checkout.

## Goal
Make it easier for a merchant/operator to find, review, and confirm payment-related orders quickly.

## Existing verification flow reused
The work reuses `zana_manual_payment` order metadata, the existing verification queue, payment-state filtering, and the order detail timeline.

## UI/polish changes made
Order search now matches order reference, payment reference, customer phone, payer note, and amounts. The orders list now shows an explicit order reference column, richer verification cues, weekly verification totals, method breakdown, and recent payment activity. The order detail page now has a stronger manual-review block.

## Files changed
- `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentVerification.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaWeeklyPaymentReport.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php`

## Files not changed
- Billing/subscription payment gateway code
- Checkout flow
- Storefront route structure
- Database schema

## Known limitations
Search is now richer but still collection-based after the workspace order query, which is acceptable for current pilot scale and avoids risky schema/index changes.

## How to test
Open `/store/orders`, search by `ORDER-{id}`, payment reference, payer note, and amount. Open `/store/orders/{id}` and verify the manual review cues appear for `customer_says_paid` orders.

Table 1 — Verification polish
| Item | Added/Improved? | Where shown | Notes |
|---|---|---|---|
| Order reference visibility | Added | Orders list + order detail | Uses `ORDER-{id}` convention. |
| Search by order ref | Improved | `/store/orders` | Collection-level search to stay update-safe. |
| Search by amount | Improved | `/store/orders` | Matches amount due and recorded amount received. |
| Missing reference visibility | Improved | Orders list + order detail | Clearer manual-review cues. |
| Payer clue visibility | Improved | Orders list + order detail | Surfaces note closer to verification actions. |
| Awaiting verification amount | Added | Weekly report block | Derived from awaiting-verification orders. |

Table 2 — Payment states
| Merchant-facing state | Source of truth | Where shown | Notes |
|---|---|---|---|
| Awaiting Payment | `ZanaManualPayment::paymentStatus()` | Orders list, filters, detail | Backend-safe overlay. |
| Customer Says Paid | `ZanaManualPayment::paymentStatus()` | Orders list, detail, weekly report | Distinct from verification queue label. |
| Awaiting Verification | `ZanaPaymentVerification::derivedState()` | Orders list, queue, detail | Derived from `customer_says_paid`. |
| Paid Confirmed | `ZanaManualPayment::paymentStatus()` | Orders list, detail, weekly report | |
| Payment Failed | `ZanaManualPayment::paymentStatus()` | Orders list, filters, weekly report | |
| Refunded | `ZanaManualPayment::paymentStatus()` | Orders list, filters, weekly report | |

Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Http/Controllers/WaOrderController.php` | Extend filters/search/reporting | Yes | Medium | Kept changes localized to existing order controller |
| `app/Support/ZanaPaymentVerification.php` | Centralize verification logic | Yes | Low | New Zana-specific helper |
| `app/Support/ZanaWeeklyPaymentReport.php` | Centralize report aggregation | Yes | Low | New Zana-specific helper |
| `resources/views/user/store/orders/index.blade.php` | Merchant reporting/filter polish | Yes | Medium | Reused existing page instead of adding route |
| `resources/views/user/store/orders/show.blade.php` | Manual-review cues | Yes | Medium | Reused existing detail page |
