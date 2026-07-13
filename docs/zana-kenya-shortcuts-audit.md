# Zana Kenya Shortcuts Audit

| Area | Existing support found? | File/View/Controller | Can reuse? | Notes |
|---|---|---|---|---|
| Order payment actions | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Yes | Payment instructions, reminders, paid-state actions already existed |
| Order payment action handling | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | Yes | Existing update action already handled send + confirmation flow |
| Africa payment setup fields | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php), [/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php) | Yes | Till, Paybill, business name, reference format already stored in `payment_config_json` |
| Current payment instruction text | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaAfricaPayments.php) | Yes | Good generic base, but not Kenya-optimized |
| Customer says paid / paid confirmed flow | Yes | [/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | Yes | Backend mapping already stable; mostly a UX prominence issue |
| Kenya-specific M-Pesa shortcut seam | Partial | new helper seam | Yes | Best added as a Zana helper on top of existing order send flow |

