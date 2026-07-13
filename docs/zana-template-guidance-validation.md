## Summary
Template fallback already existed for compliant payment sends. This pass adds honest readiness guidance to storefront setup and order operations without inventing validation the system does not have.

| Area | Existing support found? | File/Controller/Service | Can reuse? | Notes |
|---|---|---|---|---|
| Stored instruction template ID | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php` | Yes | Stored in `payment_config_json.payment_instruction_template_id`. |
| Stored reminder template ID | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php` | Yes | Stored in `payment_config_json.payment_reminder_template_id`. |
| Template send fallback | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentTemplateFallback.php` | Yes | Used only when 24-hour restrictions require it. |
| Template approval state | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Models/WaTemplate.php` | Yes | Reads `meta_status=APPROVED`. |
| Workspace send-path guidance | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/WorkspaceEngine.php` | Yes | Used to explain WABA vs Twilio vs non-official paths honestly. |

| Use case | Freeform works? | Template needed? | Existing template support? | Notes |
|---|---|---|---|---|
| In-window payment instructions | Usually yes | No | Existing native send path | Uses current WhatsApp dispatcher first. |
| Outside-24h Cloud API instruction send | No | Yes | Yes for WABA | Falls back to approved Meta template when configured. |
| Outside-24h reminder send | No | Yes | Yes for WABA | Same fallback path. |
| Twilio official send path | Partial | Maybe | Not validated here | Guidance warns that WABA-style validation does not cover Twilio fallback. |
| No configured template | No | Yes | Copy fallback only | UI now says this explicitly. |
