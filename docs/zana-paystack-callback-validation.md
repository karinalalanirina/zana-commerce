## Summary
This pass adds the smallest safe Paystack merchant callback flow: one webhook route, exact-reference matching, merchant-secret signature verification, duplicate protection, and timeline/payment-state integration.

## Goal
Let generated merchant Paystack payments report back into Zana safely without rewriting checkout or replacing manual confirmation.

## Existing Paystack infrastructure reused
- [app/Support/ZanaPaystackMerchantLink.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaystackMerchantLink.php)
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)

## Callback model
- Route: [routes/web.php](/Users/karinachanmane/Projects/zana/zana-commerce/routes/web.php) -> `/webhooks/storefront-pay/paystack`
- Controller: [app/Http/Controllers/StorefrontPaymentController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/StorefrontPaymentController.php)
- Guard: [app/Support/ZanaPaystackCallbackGuard.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaystackCallbackGuard.php)

## Matching behavior
- Reference extracted from payload
- Order resolved by stored generated Paystack reference
- Storefront resolved from matched order
- Signature verified with merchant Paystack secret
- Callback fingerprint stored for idempotency

## Duplicate protection
- Fingerprint includes event, reference, status, gateway payment id, and amount
- Replayed callback increments duplicate count but does not create a second confirmation event

## Timeline behavior
Table 1 — Paystack callback flow
| Step | Trigger | Data stored | Where shown | Notes |
|---|---|---|---|---|
| Link generated | Merchant action | reference, link, access code | Order timeline | Existing pass reused. |
| Link sent/copied | WhatsApp send outcome | send channel, send result | Order timeline | Existing send flow reused. |
| Callback received | Signed callback, amount mismatch | callback status, amount, gateway payment id | Order timeline + verification state | Conservative review path. |
| Payment confirmed | Signed callback, exact amount match | confirmation status, amount, reference | Order timeline + order state | Auto-confirm only in exact-match case. |
| Payment failed | Signed non-success callback | failure status and callback payload snapshot | Order timeline | Honest failure recording. |
| Duplicate ignored | Replayed same callback | duplicate count | Order meta | No duplicate timeline confirmation. |

Table 2 — Callback handling
| Callback case | Expected behavior | Where stored/shown | Notes |
|---|---|---|---|
| `charge.success` exact match | Auto-confirm | Order status + payment meta + timeline | Signature must pass. |
| `charge.success` amount mismatch | Await verification | Payment meta + timeline | Merchant still reviews. |
| failed/non-success | Payment failed | Payment meta + timeline | No false success. |
| duplicate | Ignore | Payment meta only | Returns success with `duplicate=true`. |
| unmatched | Safe `202` | logs only | No mutation. |
| bad signature | Safe `400` | logs only | No mutation. |

## Verification queue behavior
- `paid_confirmed` moves into the current confirmed flow
- amount-mismatch success becomes `customer_says_paid`, which keeps the order visible for review
- failures surface as `payment_failed`

## Export/reporting impact
- Existing order CSV export now includes:
  - `paystack_status`
  - `paystack_reference`
  - `paystack_callback_received_at`
- No new reporting engine was added

## Known limitations
- This does not yet validate Paystack redirect/browser callback separately from webhooks
- It does not attempt refund reconciliation
- It uses exact stored reference matching and intentionally avoids fuzzy matching

## How to test
1. Generate a Paystack link for an order.
2. POST a signed `charge.success` webhook with matching amount.
3. Confirm order becomes paid and timeline records Paystack confirmation.
4. Replay the exact same webhook.
5. Confirm no duplicate confirmation event appears.
6. POST an unmatched or bad-signature payload and confirm safe failure.

Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaPaystackCallbackGuard.php` | Zana-only merchant callback guard | No, new file | Low | Yes |
| `app/Http/Controllers/StorefrontPaymentController.php` | Add minimal merchant Paystack webhook endpoint | Yes | Medium | Reused existing storefront payment webhook controller |
| `routes/web.php` | Add one minimal public webhook route | Yes | Medium | Kept route surface very small |
| `app/Support/ZanaManualPayment.php` | Add Paystack callback event labels/tones | Yes | Medium | Reused existing timeline model |
| `app/Http/Controllers/WaOrderController.php` | Extend CSV export with Paystack callback fields | Yes | Medium | Reused existing export path |
