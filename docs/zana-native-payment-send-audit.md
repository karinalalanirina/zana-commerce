# Zana Native Payment Send Audit

| Area | Existing support found? | File/Service/Controller | Can reuse? | Notes |
|---|---|---|---|---|
| Existing outbound dispatcher | Yes | [app/Services/WhatsAppDispatcher.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/WhatsAppDispatcher.php) | Yes | Main send seam already supports workspace/provider resolution |
| Existing order-to-customer send path | Yes | [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Order payment-link flow already created `Message` rows and dispatched them |
| Existing message persistence | Yes | [app/Models/Message.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/Message.php) | Yes | Stores body, status, timestamps, failure reason, and workspace scope |
| Existing provider restriction handling | Yes | [app/Services/WhatsAppDispatcher.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/WhatsAppDispatcher.php), [app/Support/SendGate.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/SendGate.php) | Yes | Native send can fail due to no provider/device, policy, or guardrails |
| Existing storefront payment settings source | Yes | [app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php) | Yes | Reused for payment instruction text generation |
| Existing customer phone source | Yes | [app/Models/WaOrder.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaOrder.php) | Yes | Order already carries the destination phone |
| Existing fallback semantics | Partial | [app/Services/WhatsAppDispatcher.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/WhatsAppDispatcher.php) | Yes | Dispatcher already reports `ok`, `error`, and `local_only`; Zana now interprets `local_only` as not truly sent for payment workflow |

| Send requirement | Existing support? | Best safe approach | Notes |
|---|---|---|---|
| Send payment instructions | Partial | Reuse `WhatsAppDispatcher` via `Message` rows from order controller | Needed Zana-specific success/fallback handling and payment-event recording |
| Send payment reminder | Partial | Same send path as instructions | Same provider dependencies and fallback behavior |
| Detect native send success | Partial | Treat `ok=true` and `local_only=false` as real native send | Important because local-only dev/providerless mode should not be presented as delivered |
| Handle missing phone | Partial | Fail safely and surface copy fallback | Merchant can still operate |
| Handle session/WABA/device/provider restrictions | Yes | Use dispatcher result/error and keep copy fallback | No route change needed |
| Record send history | No dedicated payment log | Save lightweight event entries in order `meta_json` | Update-safe |

## Conclusion
The existing send infrastructure was sufficient. The safest implementation path was:
1. generate payment text from storefront settings
2. create a normal `Message` row
3. dispatch through `WhatsAppDispatcher`
4. record a Zana payment event and fallback copy text when native delivery is unavailable
