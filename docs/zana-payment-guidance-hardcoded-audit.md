# Zana Payment Guidance Hardcoded Audit

## Summary

The `/store/orders/{id}` payment action buttons had already been moved into a small action presenter, but several adjacent guidance blocks were still assembled directly in `resources/views/user/store/orders/show.blade.php`. This pass audited those blocks and identified the real state sources already available in the Zana payment helpers.

## Hardcoded Blocks Found

| Block | Hardcoded today? | File/View | Real data source available already? | Notes |
|---|---:|---|---:|---|
| Current payment method chip | Partial | `resources/views/user/store/orders/show.blade.php` | Yes | The label was changed to use `ZanaPaymentStatusBlock`, but visibility was still gated by manual payment metadata. |
| Template fallback guidance | Yes | `resources/views/user/store/orders/show.blade.php` | Yes | `ZanaPaymentTemplateReadiness::forStorefront()` already exposes engine, template, and outside-24h readiness. |
| Paystack order-link option | Yes | `resources/views/user/store/orders/show.blade.php` | Yes | `ZanaPaystackMerchantLink::readiness()` and `storefrontConfig()` expose enabled/configured/missing field state. |
| Daraja sandbox scaffold | Yes | `resources/views/user/store/orders/show.blade.php` | Yes | `ZanaDarajaSandbox::readiness()` and `storefrontConfig()` expose flag/config/callback state. |
| Last Paystack/Daraja provider metadata | Partial | `resources/views/user/store/orders/show.blade.php` | Yes | Provider references and request IDs are already stored in order payment metadata. |

## Desired Dynamic Sources

| Guidance need | Source now used | Why this is safer |
|---|---|---|
| Current rail label | `ZanaPaymentStatusBlock::build()` via `ZanaOrderPaymentGuidance` | Handles manual, Paystack, and Daraja rails without relying only on old `payment_method`. |
| Template fallback state | `ZanaPaymentTemplateReadiness::forStorefront()` | Keeps template setup guidance tied to actual approved template readiness. |
| Paystack state | `ZanaPaystackMerchantLink::readiness()` and `storefrontConfig()` | Shows enabled/configured/missing state without exposing secret values. |
| Daraja state | `ZanaDarajaSandbox::readiness()` and `storefrontConfig()` | Reflects feature flag, merchant config, and callback readiness honestly. |
| Last provider identifiers | `ZanaManualPayment::paystackMeta()` and `darajaMeta()` | Keeps operational IDs visible only when they actually exist. |

## Conclusion

The hardcoding was acceptable during fast MVP assembly, but it was already becoming a UX-maintenance problem because the guidance could drift from real payment state. The safer path is a compact presenter that derives display panels from existing Zana payment helpers while leaving routes, controllers, checkout, and billing untouched.
