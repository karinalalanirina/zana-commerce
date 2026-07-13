# Zana Payment Template Send

## Summary
Zana now resolves payment sends in a safer order: freeform first when valid, approved template next when the 24-hour rule requires it, and copy/manual fallback only when neither send path can succeed.

## Goal
Keep the existing native send path, but make official Cloud API cases more compliant and more transparent for merchants.

## Existing template infrastructure reused
- `WaTemplate`
- `TemplateSender`
- workspace-scoped approved-template lookup
- Meta 24-hour-window error detection

## Files changed
- [app/Support/ZanaPaymentTemplateFallback.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentTemplateFallback.php)
- [app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php)
- [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)

## Files not changed
- billing engine
- checkout routing
- provider webhook routes
- template schema

## Send method resolution behavior
- freeform native send stays preferred
- approved template is used only when needed and configured
- if the 24-hour rule blocks freeform and no template is configured, Zana says so clearly
- copy/manual fallback is still preserved

## Known limitations
- Template selection is storefront-level, not per-order
- Zana does not auto-create or auto-approve templates
- A workspace still needs a real official provider/template setup for compliant reopen sends

## How to test
1. Configure approved fallback templates on `/store/storefront/edit`
2. Send payment instructions/reminders from `/store/orders/{id}`
3. Confirm freeform sends still work in an open session
4. Reproduce a 24-hour-window failure and confirm the template path is used
5. Remove the configured template and confirm the UI falls back honestly with `Template required but not configured`

## Table 1 — Send method resolution
| Case | Preferred send method | Fallback | Notes |
|---|---|---|---|
| Freeform allowed | Native freeform WhatsApp send | Copy/manual if native send still fails | Default path |
| Freeform blocked by 24-hour rule and approved template exists | Approved WABA template | Copy/manual if template send fails | Compliance-first reopen |
| Freeform blocked by 24-hour rule and no template configured | None | Copy/manual with explicit warning | Honest operator feedback |
| No connected sender / local-only environment | None | Copy/manual | Good for pilots and local environments |

## Table 2 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaPaymentTemplateFallback.php` | Isolate order-payment template logic | No, custom file | Low | Yes |
| `app/Http/Controllers/WaStorefrontController.php` | Reuse existing storefront payment config | Yes | Medium | No new route/table |
| `resources/views/user/store/storefront/edit.blade.php` | Let merchants pick approved fallback templates | Yes | Medium | View-only addition |
| `app/Http/Controllers/WaOrderController.php` | Resolve send path per order action | Yes | Medium | No routing change |

