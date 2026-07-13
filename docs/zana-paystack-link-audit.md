## Summary
This pass reuses the existing Zana merchant order-payment flow and adds Paystack link generation on top of it. The source of truth is the current `zana-commerce` codebase, not the older WADesk copy.

| Area | Existing support found? | File/Controller/Service | Subscription-only or reusable? | Notes |
|---|---|---|---|---|
| Admin Paystack billing gateway | Yes | [app/Services/Payment/Drivers/PaystackDriver.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Payment/Drivers/PaystackDriver.php) | Subscription-oriented reference only | Useful as API reference, but not reused directly for merchant storefront payments. |
| Merchant payment-link history | Yes | [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | Reusable | Existing timeline and metadata seams already support payment-link events. |
| Merchant order send path | Yes | [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Reusable | Existing WhatsApp send + template fallback + copy fallback path is the safest insertion point. |
| Storefront payment config seam | Yes | [app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php) | Reusable | Existing `payment_config_json` safely absorbs merchant Paystack config without schema changes. |
| Static/manual external payment link | Yes | [app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php) | Reusable | Existing manual link field remains intact and continues to work. |
| Existing order link generation route | Yes | [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | India/Razorpay-oriented | Left intact; this pass avoids broad route changes and adds Paystack inside the update-safe order action flow. |
| Merchant callback/webhook seam | Partial | [app/Http/Controllers/StorefrontPaymentController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/StorefrontPaymentController.php) | Later-reusable | Existing merchant callback patterns exist, but Paystack auto-confirmation is intentionally deferred in this pass. |
| Verification/export/reporting layer | Yes | [app/Support/ZanaPaymentVerification.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaWeeklyPaymentReport.php), [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Reusable | Paystack link events fit into the same metadata/state model without new reporting subsystems. |

| Merchant Paystack need | Existing support? | Best safe approach | Notes |
|---|---|---|---|
| Store merchant Paystack credentials | Partial | Use `wa_storefronts.payment_config_json` with encrypted secret storage | Keeps merchant rails separate from admin subscription billing gateways. |
| Generate order-specific Paystack checkout URL | No | New helper: [app/Support/ZanaPaystackMerchantLink.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaystackMerchantLink.php) | Small Zana-only seam using Paystack initialize API. |
| Send generated link on WhatsApp | Yes | Reuse existing `sendPaymentWorkflowMessage()` path | Preserves native send, template fallback, and copy fallback behavior. |
| Track generation/send/copy in timeline | Partial | Extend `ZanaManualPayment` events | Avoids creating a second payment event system. |
| Auto-confirm Paystack payment | Partial | Defer | Not built in this pass; manual confirmation remains the truth. |
