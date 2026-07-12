# Zana Africa Merchant Payments UI Hide

## Summary
Zana now hides the India-specific merchant `Payments` workspace entry points in the Africa-first merchant experience without deleting the underlying `/store/payments` backend. The route still works, but unsupported markets now see a safe fallback state that points them to storefront payment setup and storefront orders instead of India-only WhatsApp Pay setup.

| UI Entry Point | Route/Location | Hidden? | How hidden | Backend still intact? | Notes |
|---|---|---|---|---|---|
| Store sidebar `Payments` tab | [resources/views/user/store/_sidebar.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/_sidebar.blade.php) | Yes | Skipped when `config('zana.hide_india_merchant_payments')` is true | Yes | Uses `ZANA_HIDE_INDIA_MERCHANT_PAYMENTS` with a default of `true` |
| Order detail `Configure WhatsApp Pay` card | [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Yes | Wrapped in `@unless($hideIndiaMerchantPayments)` | Yes | India-only native in-chat charge UI remains available for later markets |
| India native charge button path | `POST /store/orders/{id}/whatsapp-pay` via [app/Http/Controllers/WhatsAppPayController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WhatsAppPayController.php) | Partial | Controller blocks unsupported markets and returns a safe message | Yes | Keeps route and controller intact |
| Direct `/store/payments` page access | `GET /store/payments` | No | Fallback state shown instead of India setup UI | Yes | Safer than a hard 404 because merchants who guess the URL still land somewhere useful |
| `/store/payments` create/remove actions | `POST /store/payments`, `DELETE /store/payments/{id}` | Partial | Redirects unsupported markets back to orders | Yes | No config is written for Africa workspaces |

| Item | Keep for later? | Why |
|---|---|---|
| `/store/payments` page | Yes | The route stays as the natural home for India-native WhatsApp Pay and any future market-specific native payment surfaces |
| India WhatsApp Pay UI | Yes | It is still valid for India-supported WABA setups and should remain available for future markets or region-specific rollout |
| Razorpay auto-link flow | Yes | It already exists as a later optional path and can stay dormant for Africa while storefront orders use manual link and instruction flows |
| Storefront payment link support | Yes | This is part of the Africa launch path because merchants can paste reusable external links today |

## Files Touched
- [config/zana.php](/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php)
- [app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php)
- [app/Http/Controllers/WhatsAppPayController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WhatsAppPayController.php)
- [resources/views/user/store/_sidebar.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/_sidebar.blade.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)
- [resources/views/user/store/payments/index.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/payments/index.blade.php)

## Notes
- No backend routes were removed.
- No subscription billing gateway logic was touched.
- No admin payment gateway configuration was changed.
