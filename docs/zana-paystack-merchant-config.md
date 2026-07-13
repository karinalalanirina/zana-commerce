## Summary
Merchant Paystack settings are stored in the existing storefront payment config seam and do not touch admin subscription billing gateways.

| Field | Purpose | Where stored | Visible to merchant? | Notes |
|---|---|---|---|---|
| `paystack_enabled` | Turns on merchant-side Paystack order-link generation | `wa_storefronts.payment_config_json` | Yes | Off by default unless merchant enables it. |
| `paystack_public_key` | Optional visible merchant record of the Paystack account in use | `wa_storefronts.payment_config_json` | Yes | Not required for server-side link initialization today, but useful for merchant setup clarity. |
| `paystack_secret_key` | Authenticates order-link creation with Paystack | `wa_storefronts.payment_config_json` | Input only | Stored encrypted; never rendered back into the form. |
| `paystack_reference_prefix` | Prefix for order-specific Paystack references | `wa_storefronts.payment_config_json` | Yes | Used by the new helper to build deterministic references. |
| `paystack_fallback_customer_email` | Used when an order has no customer email but Paystack still requires one | `wa_storefronts.payment_config_json` | Yes | Required for safe generation when customer email is missing. |
| `paystack_redirect_note` | Merchant reminder for what customer sees after checkout | `wa_storefronts.payment_config_json` | Yes | Stored for context and future polish, not yet used as a workflow engine. |

### Notes
- Merchant config controller: [app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php)
- Merchant config view: [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php)
- Secret encryption helper: [app/Support/ZanaPaystackMerchantLink.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaystackMerchantLink.php)
