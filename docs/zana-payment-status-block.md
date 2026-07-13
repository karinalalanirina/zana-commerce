## Summary
Added a compact merchant-facing payment status block on `/store/orders/{id}`. It sits above the existing payment history and summarizes the active rail, current payment status, provider state, amount-check state, send state, and the next recommended action.

## Goal
Help merchants understand the current payment situation at a glance without reading the full payment timeline.

## Existing payment signals reused
- `App\Support\ZanaManualPayment`
- `App\Support\ZanaPaymentVerification`
- Paystack callback metadata already stored on the order
- Daraja scaffold metadata already stored on the order
- Existing payment timeline/message delivery state

## Status block behavior
- Manual M-Pesa orders show `Manual M-Pesa` plus the current merchant-facing status and next action.
- Paystack orders prefer Paystack metadata and can show `Link generated`, `Callback received`, `Exact amount match`, or `Amount mismatch`.
- Daraja scaffold orders prefer Daraja metadata and can show `STK initiated`, `Callback received`, or `Callback failed`.
- Send state is shown only when the system actually knows it from the payment timeline or fallback result.
- The block does not replace the detailed payment history below it.

## Files changed
- `app/Support/ZanaPaymentStatusBlock.php`
- `resources/views/user/store/orders/show.blade.php`
- `tests/Feature/ZanaManualPaymentWorkflowTest.php`

## Files not changed
- Routes
- Billing/subscription payment logic
- Checkout logic
- Database schema

## Known limitations
- Daraja amount matching is not fully automated yet, so the block avoids claiming exact-match verification there.
- For generic manual rails, the block can summarize status and next action but cannot invent provider states that do not exist.
- The presenter is intentionally small and depends on current metadata shapes staying stable.

## How to test
1. Log in as `zuri.owner@example.com`.
2. Open `/store/orders/{id}` for a manual M-Pesa order.
3. Confirm the block shows payment rail, current status, and next recommended action.
4. Open a Paystack order with callback data and confirm provider state plus amount-check labels.
5. Open a Daraja scaffold order and confirm `STK initiated` or callback state appears when metadata exists.
6. Confirm the existing payment history still renders below the new block.

### Table 1 — Payment status block fields
| Field shown | Source | When shown | Notes |
|---|---|---|---|
| Payment rail | `payment_method` + Paystack/Daraja metadata | Always | Prefers Paystack/Daraja when those rails are active. |
| Current status | `ZanaManualPayment::paymentStatus()` | Always | Uses existing merchant-facing labels. |
| Provider state | `paystack.status` or `daraja.status` | When provider metadata exists | Hidden for generic manual flows. |
| Amount check | `paystack.amount_matches_order`, Daraja callback amount presence | When known | Avoids false exact-match claims. |
| Send state | Timeline delivery label or `last_send_result` | When known | Supports Sent, Delivered, Read, Failed, Copied instead, Template required. |
| Reference | `transaction_reference` | When recorded | Useful for reconciliation. |
| Next recommended action | Presenter derivation | Always | Pure UX guidance, no workflow mutation. |

### Table 2 — Payment rail summary behavior
| Payment rail | Summary items shown | Notes |
|---|---|---|
| Manual M-Pesa | Current status, reference, send state, next action | Best for Kenya manual-confirmation flow. |
| Paystack | Link/callback state, amount check, current status, next action | Reuses callback metadata already stored. |
| Daraja STK | STK/callback state, current status, next action | Works with current sandbox scaffold only. |
| Bank transfer / Payment link / Cash / Other | Current status, send state, reference, next action | Keeps summary useful for non-automated rails. |

### Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaPaymentStatusBlock.php` | New compact presenter | No existing vendor logic replaced, but app files can be overwritten by script updates | Medium | Yes, isolated new helper instead of controller rewrite |
| `resources/views/user/store/orders/show.blade.php` | Insert compact status block | Yes | Medium | Yes, limited view-only change |
| `tests/Feature/ZanaManualPaymentWorkflowTest.php` | Add render regression coverage | No runtime risk | Low | Yes |
