## Summary
Paystack callbacks are matched to merchant orders by the generated Paystack reference, verified with the matched merchant secret, and then mapped into the existing payment-state model.

| Callback case | Expected behavior | Matching method | What gets stored | Notes |
|---|---|---|---|---|
| Success + exact amount match | Auto-confirm safely | Stored `paystack.reference` | callback event, status, amount, currency, payment id, processed fingerprint | Order moves to `paid_confirmed` / `paid`. |
| Success + amount mismatch | Record success but require review | Stored `paystack.reference` | callback event, amount, currency, processed fingerprint | Order moves to `customer_says_paid` / `confirmed`. |
| Failed/non-success callback | Mark payment failed honestly | Stored `paystack.reference` | callback status, processed fingerprint | Keeps manual follow-up possible. |
| Duplicate callback | Ignore without duplicate confirmation | Stored callback fingerprint | duplicate counter, duplicate timestamp | No second timeline confirmation event. |
| Unmatched callback | Safe non-crashing response | Reference lookup fails | log only | Returns `202`, no order mutation. |
| Bad signature | Reject | Reference match first, then HMAC verify | nothing | Returns `400`, no order mutation. |

### Matching behavior
1. Extract Paystack reference from webhook payload.
2. Resolve order by the stored generated Paystack reference.
3. Resolve merchant storefront from that order.
4. Verify `x-paystack-signature` using the merchant Paystack secret.
5. Apply callback only after signature passes.
