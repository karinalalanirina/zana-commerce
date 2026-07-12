# Zana Native Payment Send

## Summary
Payment instructions and payment reminders now attempt a real WhatsApp send first through the existing dispatcher. When native delivery is unavailable, Zana falls back safely to copy-ready merchant text instead of pretending the message was sent.

## Goal
Use the existing send/dispatch path so merchants can trigger real WhatsApp payment messages from `/store/orders/{id}` while keeping a safe operational fallback.

## Existing send path reused
- `Message` row creation
- `WhatsAppDispatcher::send(...)`
- existing workspace/provider resolution
- existing error/local-only reporting from the dispatcher

## Files changed
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)
- [tests/Feature/ZanaManualPaymentWorkflowTest.php](/Users/karinachanmane/Projects/zana/zana-commerce/tests/Feature/ZanaManualPaymentWorkflowTest.php)

## Files not changed
- Dispatcher internals
- WhatsApp provider route structure
- Campaign send architecture
- Subscription billing send/billing logic

## Success/failure behavior
- Success: payment event is recorded as sent, merchant sees success flash, order metadata stores last send context
- Failure/unavailable: no crash, payment event is recorded as copied instead, merchant sees copy-ready fallback block

## Copy fallback behavior
Fallback is used when:
- customer phone is missing
- dispatcher returns failure
- dispatcher returns `local_only`
- workspace/provider setup cannot actually deliver the message

## Payment history integration
Every payment send attempt now records a lightweight order payment event such as:
- `payment_instructions_sent`
- `payment_instructions_copied`
- `payment_reminder_sent`
- `payment_reminder_copied`
- `payment_link_sent`
- `payment_link_copied`

## Known limitations
- No template auto-switching for 24-hour window restrictions yet
- No dedicated delivery analytics for these payment messages yet
- Uses freeform order-to-customer body generation, not a new template engine

## How to test
1. Open `/store/orders/{id}`
2. Click `Send payment instructions`
3. Verify a success message if native send is available
4. If native send is unavailable, verify the copy fallback box appears
5. Repeat with `Send payment reminder`
6. Verify the payment history block records the event

## Table 1 — Native send actions
| Action | Send path used | Success behavior | Failure fallback | Notes |
|---|---|---|---|---|
| Send payment instructions | `Message` + `WhatsAppDispatcher::send` | Records sent event, stores last send metadata, success flash | Shows copy-ready payment instructions | Uses storefront-configured payment details |
| Send payment reminder | `Message` + `WhatsAppDispatcher::send` | Records reminder-sent event | Shows copy-ready reminder | Includes order amount/reference context |
| Resend payment link | Existing payment-link send path | Records payment-link-sent event | Returns copy-ready link text | No new route added |

## Table 2 — Send dependencies
| Dependency | Required? | If missing, what happens? | Notes |
|---|---|---|---|
| Customer phone on order | Yes | Copy fallback is shown | No crash |
| Workspace/provider connectivity | Yes for native send | Copy fallback is shown | Dispatcher result drives this |
| Storefront payment settings | Recommended | Message may still be basic if sparse | Zana helper fills defaults |
| Existing dispatcher availability | Yes | Native send cannot happen | Copy fallback preserves merchant flow |

## Table 3 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | Zana-specific tracking of send outcomes | No, new file | Low | Yes |
| [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Reused existing order send path | Yes | Medium | No new backend routes added |
| [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Shows send feedback + copy fallback | Yes | Medium | Added onto current page |
