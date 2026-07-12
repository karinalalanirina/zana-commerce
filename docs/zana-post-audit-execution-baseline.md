# Zana Post-Audit Execution Baseline

This file converts the completed WADesk audit into the execution baseline for Zana. It reflects the verified local state in `/Users/karinachanmane/Projects/zana/zana-commerce` as of 2026-07-12 and avoids broad re-audit.

| Audit finding | Confirmed baseline? | Action needed now? | Priority | Notes |
|---|---|---|---|---|
| WADesk is a usable managed SaaS base | Yes | No | P0 | Laravel app is bootable locally; core admin, inbox, storefront, contacts, campaigns, and orders routes exist. |
| Commerce is incomplete out of the box | Yes | Yes | P0 | Orders/storefront exist, but Kenya-first payment flow, delivery logic, and weekly reporting remain product work. |
| Official Meta Cloud API support is real | Yes | Configure | P0 | `WaWebhookController`, `WaConnectWabaController`, provider settings, and `wa_provider_configs` confirm official WABA support. |
| Twilio support is real | Yes | Configure | P1 | `TwilioStatusController` and provider settings confirm official Twilio path. |
| Unofficial/Baileys paths exist | Yes | Restrict | P0 | `node/package.json` includes Baileys; provider settings still support `baileys`; must stay disabled for pilots/paying clients. |
| Shared inbox / contacts / campaigns / flows are usable building blocks | Yes | Configure | P0 | Existing workspace-scoped routes and models support pilot workflows. |
| Official-only enforcement is required for production | Yes | Yes | P0 | Current platform setting already narrows sends to `["waba"]`; document and keep guarded. |
| M-Pesa / Daraja is a gap | Yes | Plan, not full build yet | P0 | No native Kenya payment flow found; phased rollout required. |
| Tenant/compliance hardening is required | Yes | Yes | P0 | Webhook security is good; payment secret exposure still needs fixing before paying clients. |
| AI exists but should launch only in supervised form | Yes | Scope only | P1 | Do not build new AI now; use drafting/review use cases only. |
| Advanced features must be hidden/deferred | Yes | Yes | P0 | Hotel/restro, aggressive blasting, Baileys UI, and broad self-serve should not be pilot-visible. |
| Update-safe implementation is mandatory | Yes | Yes | P0 | Prefer dashboard, plans, settings, env, and isolated seam files over vendor rewrites. |
| Local setup was incomplete | Yes | Fixed | P0 | `.env`, `node/.env`, and `storage/installed` were corrected locally so the app now boots. |
| Contact tags were broken locally | Yes | Fixed | P0 | Ran only `2026_07_05_000000_create_contact_tag_table` to restore `/contacts/{id}/tags` without applying unrelated pending migrations. |

## Already Good Enough

| Item | Evidence | Execution note |
|---|---|---|
| Laravel app boots | `php artisan about`, `/login` returns `200` | Good enough for local/staging work. |
| Admin/settings surfaces are extensive | `routes/admin.php`, `AdminPagesController`, `AppearanceController`, `SecurityController` | Use settings first before code. |
| Official WABA webhook security is fail-closed | `app/Http/Controllers/WaWebhookController.php` | Safe baseline for pilot webhook setup. |
| Workspace model exists | `workspaces`, `users.current_workspace_id`, scoped controllers | Good enough for controlled pilots. |

## Must Configure

| Item | Evidence | Execution note |
|---|---|---|
| Official provider defaults | `system_settings.allowed_send_methods`, `default_send_method` | Keep `waba` only for pilot/paying workspaces. |
| Branding | `system_settings.app_name`, footer, logos, appearance settings | Most Zana branding is already DB-backed. |
| Plans/package visibility | `admin/packages`, package analytics, provider settings | Use plans to reduce visible scope. |
| Security policy switches | `admin/security` | Enable template review and moderate guardrails for launch. |

## Must Harden

| Item | Evidence | Execution note |
|---|---|---|
| Payment gateway secret exposure | `app/Http/Controllers/Api/App/BillingController.php::paymentGatewaySettings` | Must be fixed before paying clients. |
| Tenant isolation smoke coverage | Local route tests only cover sample surfaces | Expand smoke tests before staging sign-off. |
| Pending migrations unrelated to commerce | `php artisan migrate:status` | Avoid bulk migration until each pending migration is reviewed. |

## Must Customize

| Item | Evidence | Execution note |
|---|---|---|
| Manual M-Pesa confirmation workflow | No native Kenya payment flow | Add later through isolated Zana commerce/payment seam. |
| Delivery zones/fees | No Kenya-first delivery model found | Plan after MVP mapping. |
| Weekly merchant reporting | Basic analytics exist, but not Zana client report format | Build as thin reporting layer later. |

## Must Hide / Defer

| Item | Evidence | Execution note |
|---|---|---|
| Baileys/unofficial send options | Provider settings + `node/` bridge | Hide from plans/UI for pilots. |
| Hotel/restro surfaces | Product scope decision | Keep out of launch messaging and plans. |
| Aggressive bulk/blast flows | Broadcast/campaign modules are powerful | Keep throttled and operator-managed only. |
| Broad self-serve onboarding | Current launch is managed SaaS | Keep manual onboarding for first clients. |

Safest implementation philosophy for Zana:
dashboard/settings first, config-first, minimal code seams, deep fork not recommended.
