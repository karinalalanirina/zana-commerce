# Zana Manual Payment Confirmation Audit

| Area | Existing support found? | File/Model/View | Can reuse? | Notes |
|---|---|---|---|---|
| Core order statuses | Yes | [app/Models/WaOrder.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaOrder.php) | Yes | Existing backend statuses are `new`, `pending`, `confirmed`, `paid`, `processing`, `completed`, `shipped`, `cancelled` |
| Per-order metadata | Yes | [app/Models/WaOrder.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaOrder.php) | Yes | `meta_json` is already cast as array and is the safest storage seam for manual payment state/history |
| Operator notes on orders | Yes | [app/Models/WaOrder.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaOrder.php), [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Yes | `notes` already exists, but it is not structured enough for payment workflow on its own |
| Customer phone mapping | Yes | [app/Models/WaOrder.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaOrder.php) | Yes | `customer_phone` is already on the order and is the primary recipient for merchant payment messages |
| Existing order update action | Yes | [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Existing update route was already the best place to add manual payment confirmation fields without new routes |
| Existing payment-link action | Yes | [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Already saves a link and attempts WhatsApp send |
| Existing payment-related merchant actions | Partial | [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Yes | Buttons existed or were partially mapped, but payment evidence, payment method, and timeline were not structured |
| Existing order history/timeline | Partial | [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Partial | No dedicated payment timeline existed; safest path was to add a Zana-specific event array inside `meta_json` |

| Payment data needed | Existing place to store it? | Best safe storage approach | Notes |
|---|---|---|---|
| Merchant-visible payment state | Partial | `wa_orders.meta_json.zana_manual_payment.status` | Avoids changing backend enum or schema |
| Payment method used | No structured field | `wa_orders.meta_json.zana_manual_payment.payment_method` | Keeps launch flexible across Kenya/Africa |
| Transaction/reference code | No structured field | `wa_orders.meta_json.zana_manual_payment.transaction_reference` | Searchable/reportable later |
| Amount received | No structured field | `wa_orders.meta_json.zana_manual_payment.amount_received` | Stored as normalized string amount to avoid schema change |
| Payer phone/name/note | No structured field | `wa_orders.meta_json.zana_manual_payment.payer_note` | Lightweight and operational |
| Confirmation note | No structured field | `wa_orders.meta_json.zana_manual_payment.confirmation_note` | Internal-only |
| Customer says paid stamp | No structured field | `wa_orders.meta_json.zana_manual_payment.customer_says_paid_at/by` | Used for merchant timeline |
| Confirmed by / confirmed at | No structured field | `wa_orders.meta_json.zana_manual_payment.confirmed_at/by` | Used for final confirmation visibility |
| Payment events/history | No dedicated model/table | `wa_orders.meta_json.zana_payment_events[]` | Safest lightweight timeline without migration |

## Conclusion
The safest manual payment confirmation path was to reuse:
- `wa_orders.status` for backend-compatible state mapping
- `wa_orders.meta_json` for structured merchant payment metadata
- existing `/store/orders/{id}` update flow for form submission

No schema change was required.
