# Zana Kenya Payment Shortcuts

## Summary
Zana now adds a Kenya-oriented shortcut layer on top of the existing Africa payment MVP, without changing the underlying order flow or billing system.

## Goal
Make the common Kenya merchant sequence faster:
1. Send M-Pesa instructions
2. Customer says paid
3. Merchant checks the reference
4. Merchant confirms payment

## Kenya shortcuts added
- `Send M-Pesa instructions`
- more prominent `Customer says paid`
- more prominent `Paid confirmed`

## M-Pesa template behavior
- If Till or Paybill is configured, Zana builds a stronger Kenya-specific M-Pesa message.
- If M-Pesa details are missing, Zana safely falls back to the generic Africa payment instructions.

## Files changed
- [app/Support/ZanaKenyaPaymentShortcut.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaKenyaPaymentShortcut.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)

## Files not changed
- billing flows
- subscription gateways
- checkout flow
- payment schema
- storefront checkout routing

## Fallback behavior
- native send first
- approved template path when required and configured
- copy/manual fallback when no valid send path succeeds

## Payment history impact
- M-Pesa send actions now record explicit payment timeline events
- order timeline can show stronger send-state context

## Known limitations
- No Daraja automation yet
- No automatic payment-reference verification yet
- Delivery/read events depend on the provider/webhook actually reporting them

## How to test
1. Configure Till and/or Paybill on `/store/storefront/edit`
2. Open `/store/orders/{id}`
3. Click `Send M-Pesa instructions`
4. Confirm the generated copy includes business name, Till/Paybill, reference, and confirmation-code request
5. Click `Customer says paid`
6. Click `Paid confirmed`
7. Confirm the timeline reflects the sequence

## Table 1 — Kenya shortcuts
| Shortcut | Added? | Behavior | Native send? | Fallback? | Notes |
|---|---|---|---|---|---|
| Send M-Pesa instructions | Yes | Builds Kenya-specific M-Pesa instructions | Yes | Yes | Uses Till/Paybill/business name/reference if configured |
| Customer says paid | Partial | Same backend action, now promoted in UI | N/A | N/A | Faster operator flow |
| Paid confirmed | Partial | Same backend action, now promoted in UI | N/A | N/A | Faster operator flow |

## Table 2 — M-Pesa message template
| Field used | Required? | Source | Notes |
|---|---|---|---|
| Customer name | No | `wa_orders.customer_name` | Falls back safely |
| Order reference | Yes | order id / reference format | Uses configured format when present |
| Total amount | Yes | `wa_orders.total_minor` display | Uses current order currency display |
| Business name | No | `payment_config_json.mpesa_business_name` | Falls back to storefront name |
| Till number | No | `payment_config_json.mpesa_till_number` | Included when configured |
| Paybill number | No | `payment_config_json.mpesa_paybill_number` | Included when configured |
| Reference format | No | `payment_config_json.payment_reference_format` | Included when configured |

## Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaKenyaPaymentShortcut.php` | Isolate Kenya logic | No, custom file | Low | Yes |
| `app/Http/Controllers/WaOrderController.php` | Reuse current order action flow | Yes | Medium | Kept edits narrow |
| `resources/views/user/store/orders/show.blade.php` | Improve merchant UX only | Yes | Medium | No route changes |

