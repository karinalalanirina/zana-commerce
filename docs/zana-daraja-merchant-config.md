## Summary
Daraja sandbox config is stored in storefront payment metadata so it stays merchant-scoped and separate from platform billing gateways.

| Field | Purpose | Where stored | Visible to merchant? | Notes |
|---|---|---|---|---|
| `daraja_enabled` | Enables sandbox STK for a storefront | `wa_storefronts.payment_config_json` | Yes | Must still be combined with the global feature flag. |
| `daraja_environment` | Keeps this pass locked to sandbox | `wa_storefronts.payment_config_json` | Yes | Current UI only exposes `sandbox`. |
| `daraja_shortcode` | Business short code used for STK requests | `wa_storefronts.payment_config_json` | Yes | Required before STK initiation can run. |
| `daraja_consumer_key` | OAuth credential for sandbox token retrieval | `wa_storefronts.payment_config_json` | Input only | Saved encrypted with existing storefront payment secret handling. |
| `daraja_consumer_secret` | OAuth secret for sandbox token retrieval | `wa_storefronts.payment_config_json` | Input only | Saved encrypted; never rendered back to the merchant. |
| `daraja_passkey` | STK password input component | `wa_storefronts.payment_config_json` | Input only | Saved encrypted; required for STK initiation. |
| `daraja_transaction_type` | Controls Paybill vs Buy Goods request mode | `wa_storefronts.payment_config_json` | Yes | Supports `CustomerPayBillOnline` and `CustomerBuyGoodsOnline`. |
| `daraja_callback_enabled` | Enables callback URL use for this storefront | `wa_storefronts.payment_config_json` | Yes | Can be disabled for initiation-only testing. |
| `daraja_reference_prefix` | Prefix for deterministic order references | `wa_storefronts.payment_config_json` | Yes | Used by `ZanaDarajaSandbox::buildReference()`. |
| `daraja_callback_token` | Storefront-specific callback lookup token | `wa_storefronts.payment_config_json` | No | Generated automatically and used only in the public callback URL. |
| Kenya phone guidance | Helps merchants format test numbers correctly | View-only in storefront edit | Yes | Explains accepted `2547`, `07`, and `7` inputs before normalization. |

### Notes
- Merchant config lives in [`app/Http/Controllers/WaStorefrontController.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php).
- Readiness and secret-aware access are centralized in [`app/Support/ZanaDarajaSandbox.php`](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaDarajaSandbox.php).
- This config is for merchant customer payments only, not Zana subscription billing.
