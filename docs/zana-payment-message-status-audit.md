# Zana Payment Message Status Audit

| Area | Existing support found? | File/Model/Controller/Service | Can reuse? | Notes |
|---|---|---|---|---|
| Outbound message model | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/Message.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/Message.php) | Yes | Stores `status`, `sent_at`, `delivered_at`, `read_at`, `failure_reason`, `meta` |
| Order payment sends create message rows | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Payment instructions/reminders already create outbound `messages` rows |
| Meta status webhook handling | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaWebhookController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaWebhookController.php) | Yes | Advances message status to `sent`, `delivered`, `read`, `failed` when `wa_message_id`/`wamid` is known |
| Twilio status handling | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/TwilioStatusController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/TwilioStatusController.php) | Yes | Also stamps delivered/read status where supported |
| Order timeline storage | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | Yes | Timeline events already accept `message_id` and send metadata |
| Safe order->message link | Partial | `message_id` in payment event metadata | Yes | Works well once order-payment sends stamp the provider `wa_message_id` |

| Status type | Exists today? | Stored where? | Can show on order timeline? | Notes |
|---|---|---|---|---|
| queued / pending | Yes | `messages.status=pending` | Yes | Best-available local state before send confirmation |
| sent | Yes | `messages.status=sent`, `messages.sent_at` | Yes | Immediate local/accepted state |
| submitted | Partial | WABA template fallback result only | Yes | Used honestly when template accepted but no later webhook yet |
| delivered | Yes | `messages.delivered_at` | Yes | Only when webhook/provider confirms |
| read | Yes | `messages.read_at` | Yes | Only when webhook/provider confirms |
| failed | Yes | `messages.status=failed`, `failure_reason` | Yes | Safe to surface |
| copied instead | Partial | order payment timeline metadata | Yes | Zana-specific fallback state |
| template required but not configured | Partial | order payment timeline metadata | Yes | Zana-specific compliance state |

