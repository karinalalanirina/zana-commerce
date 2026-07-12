# Zana Official Cloud API Default

## Status

| Item | Status | Notes |
|---|---|---|
| Official Meta Cloud API setup exists | Yes | Controllers, provider config model, and webhook flow are present. |
| Official Meta Cloud API is current default locally | Yes | `default_send_method=waba`. |
| Tenant can connect own WABA | Yes | Per-workspace rows in `wa_provider_configs`. |
| Template sync behavior | Yes | Template lifecycle is handled in `WaWebhookController`; sync sweeper exists in `app/Services/Waba/TemplateSyncSweeper.php`. |
| Webhook requirements | Yes | GET verify token + POST `X-Hub-Signature-256` HMAC required. |
| Embedded signup readiness | Partial | `WaConnectWabaController` supports official connection work, but launch should remain managed/manual. |

## Dashboard Path

- Platform provider settings: `/admin/settings/general`
- Workspace WABA connection surfaces: provider/device connection flows in WABA-related controllers and workspace configuration

## Tenant WABA Support

WABA support is multi-tenant and multi-number friendly:

- credentials live in `wa_provider_configs`
- each row carries `workspace_id`
- multiple provider rows per workspace are supported
- `is_primary` controls default sender

## Template Sync Behavior

- webhook updates are processed in `app/Http/Controllers/WaWebhookController.php`
- sweep/refresh safety exists in `app/Services/Waba/TemplateSyncSweeper.php`
- templates should still be treated as official/Meta-governed, not freeform local-only truth

## Webhook Requirements

- Verify endpoint: `GET /webhooks/whatsapp/inbound`
- Receive endpoint: `POST /webhooks/whatsapp/inbound`
- Verify token: stored in `system_settings.waba_webhook_verify_token`
- App secret: stored in `system_settings.waba_app_secret`
- Signature header: `X-Hub-Signature-256`

## Embedded Signup Readiness Summary

Embedded signup / official onboarding is not the recommended launch path yet.

- Good enough for managed onboarding: Yes
- Good enough for broad self-serve pilot launch: No

## Limitations

- Keep onboarding manual for first 3 to 5 clients
- Do not expose unofficial alternatives
- Do not market embedded signup until the full onboarding flow, validation, and support playbook are polished
