# Zana Payment Reporting Basics

## Summary
Zana now exposes lightweight payment reporting signals directly on `/store/orders` and `/store/orders/{id}` without adding a full analytics engine.

## Goal
Give merchants immediate visibility into payment progress and reconciliation signals while staying update-safe and schema-light.

## Reporting scope included
- payment-state badges on the order list
- payment reference visibility on the order list
- payment method visibility on the order list
- amount-received visibility on the order list
- payment-state summary counts
- lightweight reconciliation signals
- payment-state filter on the order list
- per-order payment timeline

## Files changed
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [resources/views/user/store/orders/index.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)

## Files not changed
- No analytics routes
- No reporting tables
- No dashboard-wide reporting engine
- No billing reports

## Where reporting now appears
- `/store/orders` summary cards and row-level badges
- `/store/orders` payment-state filter and reference-aware search
- `/store/orders/{id}` payment timeline and current payment summary pills

## Known limitations
- Summary counts are derived from order metadata, not a reporting warehouse
- No date-range analytics yet
- No export dedicated to payment history yet
- Multi-currency reconciliation is still list-level and operational, not accounting-grade

## How to test
1. Open `/store/orders`
2. Confirm payment summary cards appear
3. Filter by payment state
4. Search by transaction/reference code
5. Open an order and verify timeline/history entries match the list-level badge

| Reporting item | Exists already? | Change made | Where shown | Notes |
|---|---|---|---|---|
| Payment-state badge per order | Partial | Added merchant-visible payment-state badge separate from backend order status | `/store/orders` rows | Uses `meta_json.zana_manual_payment.status` |
| Reference visibility | No | Added row-level reference snippet | `/store/orders` rows | Helps lightweight reconciliation |
| Method visibility | Partial | Added row-level payment-method label | `/store/orders` rows | Keeps manual M-Pesa vs bank transfer visible |
| Amount received visibility | Partial | Added row-level received-amount snippet when recorded | `/store/orders` rows | Reads from payment metadata |
| Payment summary counts | No | Added top summary cards for key payment states | `/store/orders` top area | No new reporting route |
| Reconciliation nudges | No | Added “needs review” and “refs recorded” signals in summary cards | `/store/orders` top area | Focused on customer-says-paid follow-up |
| Payment-state filter | No | Added `payment_state` filter | `/store/orders` filter bar | Uses existing index route |
| Reference search | No | Added reference-aware search in existing query box | `/store/orders` | Uses current list route |
| Per-order payment history | Partial | Added payment timeline/history block | `/store/orders/{id}` | Derived from order metadata |
