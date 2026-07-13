## Summary
The current merchant Paystack flow already had the right base: order-specific Paystack references, order-level payment metadata, timeline storage, and a reusable send/fallback path. This pass adds the smallest callback layer on top of that.

| Area | Existing support found? | File/Controller/Service | Can reuse? | Notes |
|---|---|---|---|---|
| Merchant Paystack link generation | Yes | [app/Support/ZanaPaystackMerchantLink.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaystackMerchantLink.php) | Yes | Already stores generated Paystack reference and order link metadata. |
| Merchant order payment timeline | Yes | [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | Yes | Existing timeline absorbs callback events cleanly. |
| Merchant order send flow | Yes | [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Existing send/copy/template fallback remains untouched. |
| Public storefront payment webhook controller | Yes | [app/Http/Controllers/StorefrontPaymentController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/StorefrontPaymentController.php) | Yes | Already hosts merchant-facing webhook seams for Razorpay and Daraja. |
| Order matching data | Yes | Order `zana_manual_payment.paystack.reference` | Yes | Strongest safe callback matching key in the new merchant Paystack flow. |
| Merchant secret storage | Yes | Storefront `payment_config_json.paystack_secret_key` | Yes | Encrypted at rest and reused for webhook signature verification. |
| Verification queue/reporting | Yes | [app/Support/ZanaPaymentVerification.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentVerification.php), [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Callback updates flow into the same payment-state model. |

| Paystack callback need | Existing support? | Best safe approach | Notes |
|---|---|---|---|
| Match callback to exact merchant order | Partial | Use stored Paystack reference first | Avoid weak heuristics. |
| Verify callback authenticity | Partial | HMAC-SHA512 using merchant Paystack secret after order resolution | Conservative and merchant-scoped. |
| Prevent duplicate confirmations | Partial | Store processed callback fingerprints in order meta | No schema change required. |
| Reflect callback in merchant ops | Yes | Extend existing timeline + payment status model | Reuses current UI and reporting seams. |
