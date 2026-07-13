## Summary
Daraja sandbox is opt-in and off by default so the current merchant payment flow stays stable unless Zana explicitly enables sandbox validation.

| Flag | Default | Purpose | Notes |
|---|---|---|---|
| `ZANA_ENABLE_DARAJA_SANDBOX` | `false` | Turns on the merchant Daraja sandbox scaffold in storefront settings and order actions. | Read from [`config/zana.php`](/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php). |
| `ZANA_DARAJA_SANDBOX_ONLY` | `true` | Prevents any non-sandbox Daraja path from being used in this pass. | Keeps the rollout staging-only and avoids implying production readiness. |

### Behavior
- When `ZANA_ENABLE_DARAJA_SANDBOX=false`, merchants do not see Daraja sandbox config or STK actions.
- When `ZANA_ENABLE_DARAJA_SANDBOX=true`, merchants see sandbox-only guidance and can test only if all required config is present.
- No billing, subscription gateway, or checkout behavior changes behind these flags.
