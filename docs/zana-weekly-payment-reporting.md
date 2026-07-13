## Summary
Weekly payment reporting stays intentionally lightweight: compact order-page summaries over a selectable 7-day or 30-day window using existing payment metadata.

| Reporting item | Added? | Where shown | How calculated | Notes |
|---|---|---|---|---|
| Awaiting Payment count | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php` | Orders in selected window with `awaiting_payment` | |
| Customer Says Paid count | Yes | Orders page weekly block | Orders in selected window with `customer_says_paid` | |
| Awaiting Verification count | Yes | Orders page weekly block | Derived from `customer_says_paid` | Merchant-facing overlay. |
| Missing Reference count | Yes | Orders page weekly block | Awaiting verification + blank reference | |
| Paid Confirmed count | Yes | Orders page weekly block | Orders in selected window with `paid_confirmed` | |
| Payment Failed count | Yes | Controller summary | Orders in selected window with `payment_failed` | |
| Refunded count | Yes | Controller summary | Orders in selected window with `refunded` | |
| Confirmed total | Yes | Orders page weekly block | Sum of confirmed `amount_received`, else order total | Safe fallback for manual-confirmed orders. |
