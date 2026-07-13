## Summary
The Daraja sandbox scaffold is implemented in `zana-commerce`. The reason it is not currently visible in the local UI is that the global feature flag is off: `config('zana.enable_daraja_sandbox') === false`.

Current classification:
- A. Fully implemented but hidden by config/flag

## Daraja UI audit
| Daraja UI item | Exists in code? | File/View/Controller | Visible now? | Why/why not? | Notes |
|---|---|---|---|---|---|
| Storefront Daraja config section | Yes | `resources/views/user/store/storefront/edit.blade.php` | Partial | Full config fields only render when `config('zana.enable_daraja_sandbox')` is true | A small hidden-by-flag notice now renders when the flag is off. |
| `daraja_enabled` toggle | Yes | `resources/views/user/store/storefront/edit.blade.php`, `WaStorefrontController::update()` | No in current local runtime | Hidden with the rest of the config section because the global feature flag is off | Merchant-level enablement is separate from the global flag. |
| Shortcode field | Yes | `resources/views/user/store/storefront/edit.blade.php` | No in current local runtime | Same flag gate | Stored in `wa_storefronts.payment_config_json`. |
| Consumer key field | Yes | `resources/views/user/store/storefront/edit.blade.php` | No in current local runtime | Same flag gate | Input-only; saved encrypted. |
| Consumer secret field | Yes | `resources/views/user/store/storefront/edit.blade.php` | No in current local runtime | Same flag gate | Input-only; saved encrypted. |
| Passkey field | Yes | `resources/views/user/store/storefront/edit.blade.php` | No in current local runtime | Same flag gate | Input-only; saved encrypted. |
| Transaction type field | Yes | `resources/views/user/store/storefront/edit.blade.php` | No in current local runtime | Same flag gate | Supports Paybill and Buy Goods sandbox modes. |
| Callback toggle / callback URL guidance | Yes | `resources/views/user/store/storefront/edit.blade.php`, `App\Support\ZanaDarajaSandbox` | No in current local runtime | Same flag gate | Callback URL is shown only when a token exists and the flag is on. |
| Order-page Daraja status box | Yes | `resources/views/user/store/orders/show.blade.php` | Partial | Full scaffold box renders only when `ZanaDarajaSandbox::readiness($sf)['enabled']` is true | A hidden-by-flag note now renders when the flag is off. |
| `Send M-Pesa STK Push` action | Yes | `resources/views/user/store/orders/show.blade.php`, `WaOrderController::sendDarajaSandboxStk()` | No in current local runtime | Button shows only when `darajaReadiness['enabled']` is true, which currently fails because the global flag is off | Even with the flag on, the button is disabled until sandbox config is complete. |
| Daraja callback route | Yes | `routes/web.php`, `StorefrontPaymentController::darajaSandboxWebhook()` | Backend only | Public route does not depend on view visibility | Used after STK initiation for sandbox callback validation. |

## Visibility conditions
| Visibility condition | Where enforced | Current value/state | Effect |
|---|---|---|---|
| `config('zana.enable_daraja_sandbox')` must be true for storefront Daraja fields | `resources/views/user/store/storefront/edit.blade.php` | `false` locally | Full Daraja config panel is hidden; notice is shown instead. |
| `ZanaDarajaSandbox::enabled()` must be true | `App\Support\ZanaDarajaSandbox::enabled()` | `false` locally | Readiness returns `enabled=false`; order-page STK scaffold/button stay hidden. |
| Merchant storefront must exist | `WaStorefrontController::edit()` | True for merchant workspaces with storefront | Otherwise user is redirected to onboarding. |
| Merchant storefront `daraja_enabled` must be true | `ZanaDarajaSandbox::storefrontConfig()` / `hasRequiredConfig()` | Depends on storefront config | Required before STK initiation can be available. |
| Sandbox credentials must be complete | `ZanaDarajaSandbox::hasRequiredConfig()` | Usually false until merchant fills all fields | When incomplete, STK button can render only after global flag is on, but remains disabled via `can_initiate=false`. |
| Environment must remain `sandbox` while sandbox-only flag is true | `ZanaDarajaSandbox::initiateForOrder()` | `true` locally for `daraja_sandbox_only` | Prevents non-sandbox initiation through this scaffold. |

## Exact conditions
### For Daraja config fields to show on `/store/storefront/edit`
`config('zana.enable_daraja_sandbox')` must be `true`.

### For `Send M-Pesa STK Push` to show on `/store/orders/{id}`
`$darajaReadiness['enabled']` must be `true`, which currently means:
1. `config('zana.enable_daraja_sandbox') === true`

The button becomes enabled only when `can_initiate` is true, which additionally requires:
1. storefront `daraja_enabled === true`
2. environment is `sandbox`
3. shortcode is present
4. consumer key is present
5. consumer secret is present
6. passkey is present

## Exact steps to make Daraja appear in the UI
1. Set `ZANA_ENABLE_DARAJA_SANDBOX=true` in the local or staging `.env`.
2. Keep `ZANA_DARAJA_SANDBOX_ONLY=true` for this scaffold.
3. Run `php artisan config:clear`.
4. Open `/store/storefront/edit`.
5. Fill and save:
   - Enable Daraja sandbox for this merchant
   - Business short code
   - Consumer key
   - Consumer secret
   - Passkey
   - Transaction type
   - Reference prefix
   - Callback toggle if using public HTTPS callback testing
6. Open `/store/orders/{id}`.
7. The `Send M-Pesa STK Push` button appears; it becomes actionable once the saved config is complete.

## How it works after it appears
1. Merchant enters Daraja sandbox config on `/store/storefront/edit`.
2. Merchant opens `/store/orders/{id}` and clicks `Send M-Pesa STK Push`.
3. `WaOrderController::sendDarajaSandboxStk()` calls `ZanaDarajaSandbox::initiateForOrder()`.
4. The sandbox callback path is `route('storefront.pay.daraja-sandbox.webhook', ['token' => ...])`.
5. Expected timeline events:
   - `daraja_stk_initiated`
   - `daraja_callback_success` or `daraja_callback_failed`
   - plus normal payment-state updates on the order
