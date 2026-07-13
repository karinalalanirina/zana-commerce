## Summary
Payment-related orders can now be exported directly from `/store/orders` with the current filters, without adding a new route.

| Export field | Included? | Source | Notes |
|---|---|---|---|
| order_id | Yes | `wa_orders.id` | |
| order_reference | Yes | Derived `ORDER-{id}` | Keeps export human-friendly. |
| customer_name | Yes | `wa_orders.customer_name` | |
| customer_phone | Yes | `wa_orders.customer_phone` | |
| order_status | Yes | `wa_orders.status` | |
| payment_state | Yes | `ZanaManualPayment::paymentStatus()` | Exported as merchant-facing label. |
| verification_state | Yes | `ZanaPaymentVerification::derivedLabel()` | |
| payment_method | Yes | `meta_json.zana_manual_payment.payment_method` | Exported as label. |
| amount_due | Yes | `wa_orders.total_minor/currency_code` | Uses existing formatted total display. |
| amount_received | Yes | `meta_json.zana_manual_payment.amount_received` | Uses existing display formatter. |
| payment_reference | Yes | `meta_json.zana_manual_payment.transaction_reference` | |
| payer_note | Yes | `meta_json.zana_manual_payment.payer_note` | |
| confirmed_at | Yes | `meta_json.zana_manual_payment.confirmed_at` | |
| updated_at | Yes | `wa_orders.updated_at` | |
