# Zana Payment Message Status

## Summary
Zana now reuses the existing message-status infrastructure to show the best available delivery state on the order payment timeline.

## Goal
Give merchants honest visibility into whether payment messages were sent, delivered, read, failed, copied instead, or blocked by template requirements.

## Existing status infrastructure reused
- `messages.status`
- `messages.sent_at`
- `messages.delivered_at`
- `messages.read_at`
- Meta/Twilio status webhooks
- order payment event `message_id` linkage

## Files changed
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)

## Files not changed
- webhook routes
- billing logic
- checkout logic
- provider webhook subscriptions

## Timeline behavior
- If an order-payment event has a linked `message_id`, Zana reads the current `messages` row and shows the strongest proven state.
- For approved-template fallback sends, Zana can show `Submitted` even before delivered/read callbacks arrive.
- For copy fallback and template-required cases, Zana shows those exact operator-facing states instead of pretending a message was sent.

## Known limitations
- Delivery/read only appear when the provider/webhook actually sends those updates
- Template sends without a later status webhook may stay at `Submitted`
- Historical payment events created before `message_id` linkage will not have live delivery states

## How to test
1. Send payment instructions from `/store/orders/{id}`
2. Confirm the timeline shows `Sent` or `Submitted`
3. If provider callbacks are available, verify the timeline later changes to `Delivered` or `Read`
4. Trigger a failure/copy fallback and verify the timeline shows that honestly

## Table 1 — Timeline message status
| Event | Data source | When shown | Notes |
|---|---|---|---|
| Sent | `messages.status` / `sent_at` | Immediate successful native send | Best available accepted state |
| Submitted | template fallback result | Approved template accepted but no later status yet | WABA template path |
| Delivered | `messages.delivered_at` | After webhook callback | Only when actually known |
| Read | `messages.read_at` | After webhook callback | Only when actually known |
| Failed | `messages.status=failed` / `failure_reason` | On send failure or provider failure | Honest failure state |
| Copied instead | payment event metadata | No valid automated send path | Zana operator fallback |
| Template required but not configured | payment event metadata | 24-hour rule blocked freeform and no approved template was selected | Compliance-first state |

## Table 2 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaManualPayment.php` | Enrich timeline state from existing messages | Yes | Medium | Reused existing message model instead of schema changes |
| `app/Http/Controllers/WaOrderController.php` | Preserve provider message ids on order sends | Yes | Medium | No route changes |
| `resources/views/user/store/orders/show.blade.php` | Show honest send-state details | Yes | Medium | View-only extension |

