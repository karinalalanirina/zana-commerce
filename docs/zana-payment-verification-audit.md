## Summary
This pass reused existing order `meta_json`, payment timeline events, storefront payment config, and `messages` delivery fields. No schema changes were needed.

| Area | Existing support found? | File/Model/View/Controller | Can reuse? | Notes |
|---|---|---|---|---|
| Payment reference code | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php` | Yes | Stored at `meta_json.zana_manual_payment.transaction_reference`. |
| Amount received | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php` | Yes | Stored at `meta_json.zana_manual_payment.amount_received`. |
| Customer says paid / paid confirmed | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php` | Yes | Stored as payment status plus timestamps / actor names. |
| Payment method | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php` | Yes | Stored at `meta_json.zana_manual_payment.payment_method`. |
| Payer phone / note clue | Partial | `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php` | Yes | Stored in `payer_note`; used as a general reconciliation clue field. |
| Timeline / verification history | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php` | Yes | Existing event list extended safely. |
| Order list payment filtering | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php` | Yes | Extended with verification filter + payer note/reference search. |
| Storefront fallback template config | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php` | Yes | Existing template IDs already stored in `payment_config_json`. |
| Template approval signal | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaTemplate.php` | Yes | Approval comes from `meta_status=APPROVED`. |

| Verification/reporting need | Existing data available? | Best safe approach | Notes |
|---|---|---|---|
| Awaiting verification queue | Yes | Derive from `customer_says_paid` without new DB status | Keeps backend workflow intact. |
| Missing reference review | Yes | Derive from empty `transaction_reference` while awaiting verification | No schema change needed. |
| Weekly payment summary | Yes | Aggregate order metadata in controller | Uses `updated_at` windowing. |
| Payment export | Yes | Reuse CSV stream download on `/store/orders` | No new route added. |
| Template readiness guidance | Yes | Read storefront template IDs + template approval state | Honest validation only. |
