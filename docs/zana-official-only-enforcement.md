# Zana Official-Only Enforcement

Production rule:

Pilot and paying Zana workspaces must use only official WhatsApp providers.

Primary flag:

```env
ZANA_ALLOW_UNOFFICIAL_WHATSAPP=false
```

Default remains `false`.

| Requirement | Solved by dashboard? | Dashboard path | Code needed? | File changed | Notes |
|---|---|---|---|---|---|
| Disable unofficial provider from normal send resolution | Yes | `/admin/settings/general` providers section | Reinforced | `app/Services/WorkspaceEngine.php`, `app/Services/WhatsAppDispatcher.php`, `app/Services/InboxDispatcher.php` | `allowed_send_methods` is filtered through `ZanaWhatsAppPolicy`. |
| Set official default provider | Yes | `/admin/settings/general` providers section | Reinforced | `app/Services/WorkspaceEngine.php` | Unsafe defaults are sanitized to official providers. |
| Prevent normal workspace selection of Baileys | Partial by dashboard alone | `/admin/settings/general` providers section | Yes | `app/Http/Controllers/AdminPagesController.php` | Posted disallowed providers are rejected server-side, not just hidden in UI. |
| Block unofficial device creation / QR / pairing flows | No | N/A | Yes | `app/Http/Controllers/Api/App/DeviceController.php`, `app/Http/Controllers/DevicesController.php`, `app/Http/Controllers/WaConnectController.php` | Manipulated requests now get `422` plus a clean blocked message. |
| Block stale unofficial sends if old data/config remains | No | N/A | Yes | `app/Services/WhatsAppDispatcher.php`, `app/Services/InboxDispatcher.php` | Pinned/stale Baileys sends return a blocked result instead of silently routing. |
| Block Baileys-only group operations | No | N/A | Yes | `app/Http/Controllers/Api/App/GroupController.php` | Group endpoints now short-circuit with official-only block response. |

## Verified Evidence

- `config/zana.php` reads `ZANA_ALLOW_UNOFFICIAL_WHATSAPP`
- `app/Support/ZanaWhatsAppPolicy.php` centralizes allow/deny, sanitization, and clean logging
- `app/Exceptions/ZanaUnofficialWhatsAppBlocked.php` gives a controlled failure path
- local `system_settings.allowed_send_methods = ["waba"]`
- local `system_settings.default_send_method = "waba"`
- `php artisan test --filter='PaymentGatewaySecurityTest|ZanaOfficialWhatsAppGuardTest'` passes with `15` tests / `43` assertions

## Recommendation

For staging and pilot work, keep:

- `allowed_send_methods=["waba"]` or `["waba","twilio"]`
- `default_send_method="waba"`
- `ZANA_ALLOW_UNOFFICIAL_WHATSAPP=false`

Do not delete Baileys code. The current implementation is update-safer: it leaves vendor paths in place but prevents production use through a small policy layer plus focused controller/dispatcher guards.
