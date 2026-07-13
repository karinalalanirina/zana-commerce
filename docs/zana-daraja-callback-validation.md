## Summary
The Daraja sandbox callback path matches callbacks to merchant orders using stored request IDs first, records the outcome on the existing payment timeline, and handles duplicates idempotently.

| Callback case | Expected behavior | How matched | What gets stored | Notes |
|---|---|---|---|---|
| Success callback | Order moves to `customer_says_paid` unless already confirmed | `checkout_request_id` first, then `merchant_request_id` | receipt number, amount, phone, result code/desc, callback timestamp, processed fingerprint | Keeps manual confirmation path intact. |
| Failed callback | Order moves to `payment_failed` | `checkout_request_id` first, then `merchant_request_id` | failure result code/desc, callback timestamp, processed fingerprint | No false payment confirmation. |
| Duplicate callback | Returns success without creating a second timeline event | Stored callback fingerprint on the matched order | `duplicate_callback_count`, `last_duplicate_callback_at` | Same callback does not double-confirm or double-log. |
| Unmatched callback | Returns safe non-crashing response | No order matched by stored request IDs | warning log only | Current controller responds `202` for unmatched callbacks. |
| Invalid payload | Rejected safely | Payload shape check on `Body.stkCallback` | nothing | Controller returns `422`; no order mutation. |

### Matching rules
1. Resolve storefront from `daraja_callback_token`.
2. Extract `MerchantRequestID` and `CheckoutRequestID`.
3. Search only that storefront’s orders.
4. Prefer stored `checkout_request_id`; fall back to stored `merchant_request_id`.
5. Use a callback fingerprint to detect repeats.

### Storage
- Callback matching and idempotency live in [`app/Support/ZanaDarajaCallbackGuard.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaDarajaCallbackGuard.php).
- Daraja metadata is merged into the existing `zana_manual_payment` order meta via [`app/Support/ZanaManualPayment.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php).
- The minimal public route is [`routes/web.php`](/Users/karinachanmane/Projects/zana/zana-commerce/routes/web.php) -> `storefront.pay.daraja-sandbox.webhook`.
