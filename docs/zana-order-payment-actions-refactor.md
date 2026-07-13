## Summary
Refactored `/store/orders/{id}` payment button rendering into a small Zana presenter/action map without changing controller branching, action IDs, payment semantics, routes, or schema.

## Goal
Reduce Blade complexity while preserving the exact current payment workflow behavior.

## Existing behavior preserved
- `payment_action` values remain unchanged
- `WaOrderController::updateStatus()` remains the source of truth
- readiness gating for Paystack and Daraja remains unchanged
- Kenya shortcut flow remains unchanged
- payment-link visibility remains unchanged
- form submit/save flow remains unchanged

## Presenter/action-map structure
New helper:
- `app/Support/ZanaOrderPaymentActions.php`

It builds grouped action definitions for:
- `kenya_shortcuts`
- `provider_actions`
- `payment_messaging`
- `payment_state_updates`
- `primary_submit`

Each action carries:
- `id`
- `label`
- `disabled`
- `variant`
- `classes`
- `reason`
- `handler`
- `type`

## Files changed
- `app/Support/ZanaOrderPaymentActions.php`
- `resources/views/user/store/orders/show.blade.php`

## Files not changed
- `app/Http/Controllers/WaOrderController.php`
- routes
- billing logic
- checkout logic
- database schema

## Known limitations
- The presenter is intentionally view-focused and does not centralize controller logic.
- Button help text and disabled reasons are still minimal.
- Some non-`payment_action` JS-only buttons still use handler strings rather than a deeper command abstraction.

## How to test
1. Open `/store/orders/{id}`.
2. Confirm the same payment buttons appear in the same scenarios as before.
3. Confirm Paystack buttons still disable when readiness is missing.
4. Confirm Daraja STK stays hidden/disabled according to the same readiness rules.
5. Confirm Kenya shortcuts still submit the same hidden `payment_action`.
6. Confirm Save still performs a normal form submit.

### Table 1 — Action map
| Action ID | Label | Group | Visible | Disabled | Notes |
|---|---|---|---|---|---|
| `send_mpesa_instructions` | Send M-Pesa instructions | `kenya_shortcuts` | When M-Pesa shortcut exists | No | Kenya-first shortcut |
| `customer_says_paid` | Customer says paid | `kenya_shortcuts` | Yes | No | Manual verification flow |
| `paid_confirmed` | Paid confirmed | `kenya_shortcuts` | Yes | No | Manual confirmation flow |
| `generate_paystack_link` | Generate Paystack link | `provider_actions` | Yes | Based on Paystack readiness | Preserved |
| `generate_paystack_link_send` | Generate Paystack link + send | `provider_actions` | Yes | Based on Paystack readiness | Preserved |
| `send_daraja_stk` | Send M-Pesa STK Push | `provider_actions` | When Daraja is enabled | Based on Daraja readiness | Preserved |
| `send_instructions` | Send general payment instructions / Send payment instructions | `payment_messaging` | Yes | No | Label preserved |
| `send_reminder` | Send payment reminder | `payment_messaging` | Yes | No | Preserved |
| `resend_link` | Resend payment link | `payment_messaging` | When payment link exists | No | Preserved |
| `payment_failed` | Mark payment failed | `payment_state_updates` | Yes | No | Preserved |
| `refunded` | Mark refunded | `payment_state_updates` | Yes | No | Preserved |
| form submit | Save | `primary_submit` | Yes | No | Preserved |

### Table 2 — Existing behavior preservation
| Behavior | Preserved? | Notes |
|---|---|---|
| Existing action IDs | Yes | No IDs changed |
| Existing controller validation | Yes | `WaOrderController::updateStatus()` untouched |
| Existing readiness gating | Yes | Presenter consumes existing readiness values |
| Existing JS submit wiring | Yes | Still uses `submitPaymentAction(...)` |
| Existing provider-specific flow branching | Yes | Untouched |
| Existing payment timeline/history | Yes | Untouched |

### Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaOrderPaymentActions.php` | Add isolated presenter | Yes | Medium | Yes, new helper instead of controller rewrite |
| `resources/views/user/store/orders/show.blade.php` | Replace handwritten buttons with presenter loop | Yes | Medium | Yes, minimal Blade-only refactor |
