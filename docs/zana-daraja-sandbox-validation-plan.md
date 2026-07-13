## Summary
This document now reflects the scaffold that actually exists in Zana: merchant sandbox config, order-page STK initiation, a minimal public callback route, safe callback matching, duplicate protection, and timeline/export integration.

## Goal
Validate the Kenya automation seam on staging without widening scope into production Daraja automation.

## What was actually scaffolded
- Merchant Daraja sandbox configuration on `/store/storefront/edit`
- Global sandbox feature flag and sandbox-only guard
- `Send M-Pesa STK Push` action on `/store/orders/{id}`
- Stored request metadata on order payment meta
- Public sandbox callback route with storefront-token lookup
- Callback-to-order matching using stored request IDs
- Duplicate callback protection
- Timeline/history integration through the existing payment event model
- Export support for Daraja identifiers and callback timestamp

## What still remains manual
- Merchant credential collection and staging rollout remain operator-managed
- Callback reachability still requires staging or a public tunnel
- Final payment confirmation remains part of the current merchant review flow
- No production Daraja signature hardening, live credential lifecycle, or automated reconciliation beyond sandbox proof yet

## Sandbox prerequisites
- Enable `ZANA_ENABLE_DARAJA_SANDBOX=true`
- Keep `ZANA_DARAJA_SANDBOX_ONLY=true`
- Storefront must have sandbox shortcode, consumer key, consumer secret, passkey
- Sandbox callback token is generated and callback must be publicly reachable
- Test customer phone must normalize to a Kenya mobile number

## Public callback URL requirements
- Daraja callback URL is storefront-specific and tokenized
- It must be reachable over public HTTPS
- Localhost alone is not sufficient

## Local/staging tunnel guidance
- Prefer staging for realistic callback testing
- If validating locally, use a public tunnel such as ngrok or Cloudflare Tunnel and map it to the app route
- Confirm the tunneled URL resolves to the same `storefront.pay.daraja-sandbox.webhook` route used in code

## Validation scenarios
Table 1 — Scaffolded Daraja pieces
| Item | Added? | Where in code | Notes |
|---|---|---|---|
| Global feature flag | Yes | [`config/zana.php`](/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php) | Off by default. |
| Merchant sandbox config | Yes | [`app/Http/Controllers/WaStorefrontController.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php), [`resources/views/user/store/storefront/edit.blade.php`](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php) | Stored in `payment_config_json`. |
| Sandbox readiness helper | Yes | [`app/Support/ZanaDarajaSandbox.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaDarajaSandbox.php) | Handles config read, readiness, phone normalization, initiation. |
| STK initiation action | Yes | [`app/Http/Controllers/WaOrderController.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php), [`resources/views/user/store/orders/show.blade.php`](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/show.blade.php) | Keeps manual fallback intact. |
| Callback route | Yes | [`routes/web.php`](/Users/karinachanmane/Projects/zana/zana-commerce/routes/web.php) | Minimal public route added only for sandbox callbacks. |
| Callback guard | Yes | [`app/Support/ZanaDarajaCallbackGuard.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaDarajaCallbackGuard.php) | Handles matching, duplicates, and safe failure modes. |
| Timeline integration | Yes | [`app/Support/ZanaManualPayment.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php) | New Daraja event labels/tones added. |
| Export integration | Yes | [`app/Http/Controllers/WaOrderController.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php) | CSV includes Daraja state and request IDs. |

Table 2 — Sandbox prerequisites
| Requirement | Needed? | Where configured | Notes |
|---|---|---|---|
| `ZANA_ENABLE_DARAJA_SANDBOX` | Yes | `.env` / [`config/zana.php`](/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php) | Must be on for any UI/action to appear. |
| `ZANA_DARAJA_SANDBOX_ONLY` | Recommended | `.env` / [`config/zana.php`](/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php) | Keeps this pass staging-only. |
| Shortcode | Yes | Storefront payment config | Required for STK request body. |
| Consumer key | Yes | Storefront payment config | Encrypted at rest. |
| Consumer secret | Yes | Storefront payment config | Encrypted at rest. |
| Passkey | Yes | Storefront payment config | Used to build STK password. |
| Callback enabled/token | For callback tests | Storefront payment config | Needed to receive and map callbacks. |
| Public HTTPS callback URL | Yes | Derived from route + callback token | Must be reachable by Safaricom sandbox. |
| Kenya phone | Yes | Order customer phone | Normalized to `2547XXXXXXXX` before request. |

Table 3 — Validation scenarios
| Scenario | Expected result | What should be stored | How to verify |
|---|---|---|---|
| Feature flag off | No Daraja UI or STK action visible | nothing | Load storefront edit + order detail and confirm Daraja scaffold is hidden. |
| Feature flag on, config incomplete | Safe guidance shown, no initiation | readiness label only | Load storefront edit and confirm `Sandbox config incomplete`. |
| STK initiation success | Order keeps manual flow but records pending Daraja attempt | request IDs, phone, amount, reference, response description, callback URL | Check order detail and `zana_manual_payment.daraja`. |
| STK initiation failure | Merchant sees honest failure and manual fallback remains available | `status=initiation_failed`, last error, request attempt timestamp | Check order timeline and flash message. |
| Success callback | Order moves to `customer_says_paid` and adds one Daraja success timeline event | receipt, amount, phone, callback result, processed fingerprint | Check order detail, verification queue, and payment meta. |
| Failed callback | Order moves to `payment_failed` and adds one failure timeline event | result code/desc, callback timestamp | Check order detail and filters. |
| Duplicate callback | No second confirmation event is added | `duplicate_callback_count`, `last_duplicate_callback_at` | Replay same payload and confirm timeline count remains unchanged. |
| Unmatched callback | Safe non-crashing response and no order mutation | warning log only | Post unknown request IDs and confirm `202` response. |

Table 4 — Remaining risks
| Risk | Severity | Why it matters | Mitigation |
|---|---|---|---|
| Tokenized callback route has no signature verification yet | Medium | Sandbox proof is fine, but production needs stronger callback trust controls | Keep staging-only and add production hardening later. |
| Storefront lookup scans merchant rows in memory | Low | Fine for sandbox validation, not ideal for scale | Replace with indexed lookup if productionized later. |
| Callback success does not auto-skip merchant review | Medium | Merchants still need to verify before final confirmation | Intentional for MVP safety. |
| Local callback testing without a tunnel will fail | Medium | Can look like the scaffold is broken | Validate on staging or with a public HTTPS tunnel. |

## What is sandbox-ready vs not production-ready yet
- Sandbox-ready:
  - merchant config capture
  - STK initiation
  - callback receipt
  - request-ID matching
  - duplicate protection
  - timeline/export integration
- Not production-ready yet:
  - live Daraja rollout
  - production callback trust model
  - full reconciliation automation
  - merchant self-serve onboarding
  - subscription/billing gateway integration

## Recommended next step after successful validation
Run a real staging validation with public callback reachability, verify one success callback and one duplicate callback end to end, then decide whether to harden the sandbox scaffold into a production Daraja integration seam.
