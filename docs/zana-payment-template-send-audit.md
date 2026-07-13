# Zana Payment Template Send Audit

| Area | Existing support found? | File/Controller/Service | Can reuse? | Notes |
|---|---|---|---|---|
| Official WABA template send service | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Waba/TemplateSender.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Waba/TemplateSender.php) | Yes | Already handles approved-template sending and Meta errors |
| Template availability per workspace | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaTemplate.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaTemplate.php) | Yes | `forCurrentWorkspace()` and `approved()` already exist |
| Existing official template send path in UI | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/TeamInboxController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/TeamInboxController.php), [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/ChatController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/ChatController.php) | Yes | Existing WABA template send logic is already workspace-aware |
| 24-hour-window error detection | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Waba/TemplateSender.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Waba/TemplateSender.php), [/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Waba/MetaError.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Waba/MetaError.php) | Yes | Meta `131047` and re-engagement language already recognized |
| Order-flow template seam | Partial | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentTemplateFallback.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentTemplateFallback.php) | Yes | Good place to keep payment-specific resolution out of the controller |

| Use case | Freeform works? | Template needed? | Existing template support? | Notes |
|---|---|---|---|---|
| Open 24-hour customer service window | Yes | No | Yes | Freeform remains preferred |
| Closed 24-hour window, approved template configured | No | Yes | Yes | Use approved WABA template |
| Closed 24-hour window, no template configured | No | Yes | Partial | Honest copy fallback should remain visible |
| No connected sender / local-only failure | No | No | Not necessarily | Copy/manual fallback remains safest |

