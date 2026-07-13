# Zana Payment Guidance Dynamic Panels

## Summary

The order detail payment guidance below the Kenya shortcuts now comes from `App\Support\ZanaOrderPaymentGuidance` instead of being assembled as static Blade copy. The underlying payment behavior did not change.

## Goal

Keep `/store/orders/{id}` merchant guidance aligned with real payment state for templates, Paystack, and Daraja while preserving the existing order update form, payment actions, timeline, billing logic, checkout logic, and routes.

## Dynamic Panels

| Panel | Source | When shown | Notes |
|---|---|---|---|
| Payment rail chip | `ZanaPaymentStatusBlock::build()` | Always when a label can be derived | Shows Manual payment, Manual M-Pesa, Paystack, Daraja STK, or another known rail. |
| Template fallback readiness | `ZanaPaymentTemplateReadiness::forStorefront()` | Always in payment guidance | Shows engine, instruction template state, reminder template state, and fallback mode. |
| Paystack order links | `ZanaPaystackMerchantLink::readiness()` and `storefrontConfig()` | Always in payment guidance | Shows whether Paystack is off, ready, or missing setup. Secrets are summarized as saved/missing only. |
| Daraja STK sandbox | `ZanaDarajaSandbox::readiness()` and `storefrontConfig()` | Always in payment guidance | Shows hidden-by-flag, incomplete config, or STK-ready state. |
| Last provider metadata | `ZanaManualPayment::paystackMeta()` and `darajaMeta()` | Only when present | Displays last reference/status/request IDs without inventing provider state. |

## Readiness Conditions

| Readiness area | Ready condition | Incomplete condition | Safe fallback |
|---|---|---|---|
| Template fallback | Template fallback supported and both instruction/reminder templates are ready | Official/template path exists but approved templates are missing | Copy/manual fallback remains visible. |
| Paystack | Merchant enabled Paystack and has secret key plus fallback email | Merchant enabled Paystack but required values are missing | Guidance names missing setup without exposing secrets. |
| Daraja | Global flag is on, merchant sandbox is enabled, and shortcode/key/secret/passkey are saved | Flag is on but one or more required values are missing | Guidance lists missing requirements; STK action remains controlled by existing action readiness. |

## Files Changed

| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---:|---|---|
| `app/Support/ZanaOrderPaymentGuidance.php` | New small presenter for payment guidance panels | Yes | Medium | Zana-specific helper isolates custom logic from the Blade view. |
| `resources/views/user/store/orders/show.blade.php` | Replaced static guidance blocks with a presenter-driven loop | Yes | Medium | Minimal view seam; routes/controllers/actions unchanged. |
| `tests/Feature/ZanaManualPaymentWorkflowTest.php` | Added focused render coverage for Paystack and Daraja guidance states | Yes | Low | Tests validate behavior without browser automation. |

## Files Not Changed

| File/layer | Reason |
|---|---|
| Routes | No new endpoints were needed. |
| Billing/subscription logic | This is merchant order payment guidance only. |
| Checkout logic | Storefront checkout behavior was not changed. |
| Database schema | Existing order/storefront metadata was reused. |
| Paystack/Daraja send/callback services | Existing readiness and metadata helpers were reused. |

## Known Limitations

The guidance panels summarize current configuration and last known metadata only. They do not replace the payment timeline, do not validate live provider credentials, and do not make Paystack/Daraja actions available unless the existing action readiness logic allows it.

## How To Test

1. Open `/store/orders/{id}` for an order with manual payment metadata and confirm the current rail chip appears.
2. Enable Paystack in storefront payment settings and confirm the Paystack panel shows ready or missing setup based on saved config.
3. Enable `ZANA_ENABLE_DARAJA_SANDBOX=true`, leave Daraja merchant fields incomplete, and confirm the Daraja panel lists missing fields.
4. Confirm payment buttons, payment history, billing pages, and storefront checkout still behave as before.
