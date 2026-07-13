## Summary
This pass adds merchant-side Paystack order-link generation to the existing Zana order flow without replacing M-Pesa/manual payments, Daraja sandbox, or admin billing gateways.

## Goal
Let a merchant generate a Paystack checkout link for a specific order, optionally send it through the current WhatsApp send path, and record everything in the existing payment timeline.

## Existing Paystack support reused
- API reference only from [app/Services/Payment/Drivers/PaystackDriver.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Services/Payment/Drivers/PaystackDriver.php)
- Merchant payment timeline and metadata from [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)
- Existing merchant send/template/copy fallback flow from [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)

## Merchant configuration
Table 1 — Merchant Paystack config
| Field | Purpose | Where stored | Notes |
|---|---|---|---|
| `paystack_enabled` | Turns on order-link generation | `payment_config_json` | Keeps feature merchant-scoped. |
| `paystack_public_key` | Optional merchant-visible account field | `payment_config_json` | Not required for current server-side generation. |
| `paystack_secret_key` | Server-side Paystack auth | `payment_config_json` | Encrypted at rest. |
| `paystack_reference_prefix` | Reference prefix for generated links | `payment_config_json` | Used in the link helper. |
| `paystack_fallback_customer_email` | Backup email when order email is missing | `payment_config_json` | Required for safe generation. |
| `paystack_redirect_note` | Merchant setup note for post-checkout expectations | `payment_config_json` | Informational for now. |

## Order page behavior
- `Generate Paystack link`
- `Generate Paystack link + send`

Both live on `/store/orders/{id}` and reuse the current update form instead of adding new routes.

Table 2 — Order flow
| Step | Action | Result | Notes |
|---|---|---|---|
| 1 | Merchant opens order | Existing payment panel shows Paystack readiness | No routing change. |
| 2 | Click `Generate Paystack link` | Zana creates order-specific Paystack checkout URL | Link stored on the order and in payment meta. |
| 3 | Click `Generate Paystack link + send` | Link is generated, then sent via the existing WhatsApp path | Native send first, then template fallback, then copy fallback. |
| 4 | Native send unavailable | Copy-ready fallback is preserved | Manual payment confirmation flow still continues. |
| 5 | Merchant confirms payment later | Existing manual confirmation flow remains the source of truth | No fake auto-confirmation added. |

## Payment history behavior
Table 3 — Timeline events
| Event | Trigger | Stored how | Notes |
|---|---|---|---|
| `paystack_link_generated` | Successful Paystack link creation | `zana_payment_events` + `zana_manual_payment.paystack.*` | Records provider, link, and reference. |
| `paystack_link_sent` | Native WhatsApp send succeeds after generation | `zana_payment_events` | Reuses existing send infrastructure. |
| `paystack_link_copied` | Send path fails and copy fallback is shown | `zana_payment_events` | Honest merchant-facing fallback. |
| `paystack_link_generation_failed` | API config missing or Paystack call fails | `zana_payment_events` | No false success state. |
| `paystack_link_template_sent` | 24-hour compliant template fallback succeeds | `zana_payment_events` | Uses existing template fallback path. |
| `paystack_link_template_required` | Template needed but not configured | `zana_payment_events` | Manual copy fallback preserved. |

## Files changed
- [app/Support/ZanaPaystackMerchantLink.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaystackMerchantLink.php)
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)
- [app/Support/ZanaPaymentTemplateFallback.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentTemplateFallback.php)
- [app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php)
- [resources/views/user/store/orders/show.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php)
- [tests/Feature/ZanaManualPaymentWorkflowTest.php](/Users/karinachanmane/Projects/zana/zana-commerce/tests/Feature/ZanaManualPaymentWorkflowTest.php)
- [docs/zana-paystack-link-audit.md](/Users/karinachanmane/Projects/zana/zana-commerce/docs/zana-paystack-link-audit.md)
- [docs/zana-paystack-merchant-config.md](/Users/karinachanmane/Projects/zana/zana-commerce/docs/zana-paystack-merchant-config.md)

## Files not changed
- subscription billing controllers and gateways
- checkout architecture
- Daraja sandbox routes and helpers
- database schema

## Known limitations
- No Paystack auto-confirmation/webhook was added in this pass
- Merchant still confirms payment manually after customer checkout
- Existing legacy Razorpay generation route remains in place but is not the primary Africa-facing path

## How to test
1. Configure Paystack in `/store/storefront/edit`.
2. Open `/store/orders/{id}`.
3. Click `Generate Paystack link`.
4. Confirm link is stored and timeline shows `Paystack link generated`.
5. Click `Generate Paystack link + send`.
6. Confirm native send succeeds, or fallback copy appears honestly.

Table 4 — Update safety
| Changed file | Why changed | Could future WADesk update overwrite it? | Risk level | Safer alternative used? |
|---|---|---|---|---|
| `app/Support/ZanaPaystackMerchantLink.php` | New Zana-only helper for merchant link creation | No, new file | Low | Yes |
| `app/Http/Controllers/WaOrderController.php` | Add Paystack order actions through existing form flow | Yes | Medium | Reused existing send/timeline path instead of new routes |
| `app/Http/Controllers/WaStorefrontController.php` | Save merchant Paystack config in existing metadata seam | Yes | Medium | Reused `payment_config_json` |
| `resources/views/user/store/orders/show.blade.php` | Add merchant Paystack buttons and status copy | Yes | Medium | Minimal view extension only |
| `resources/views/user/store/storefront/edit.blade.php` | Add merchant Paystack setup fields | Yes | Medium | No schema or admin billing changes |
