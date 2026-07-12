# Zana Dashboard Settings Execution

This file captures what can already be handled through dashboard/admin/settings before making code changes.

| Area | Dashboard path | Action taken | Solved without code? | Notes |
|---|---|---|---|---|
| App name | `/admin/settings/general` | Verified `app_name=Zana` | Yes | Read by `App\Support\Brand::name()`. |
| Footer branding | `/admin/settings/footer` | Verified Zana/Fluxxeo footer values | Yes | DB-backed `system_settings`. |
| Logos/favicon | `/admin/settings/appearance` | Verified logo/favicon settings exist and are populated | Yes | Also supported by `AppearanceController`. |
| Theme/colors | `/admin/settings/appearance` | Verified theme color settings exist | Yes | Keep using dashboard for visual tuning. |
| Login/auth pages | `/admin/settings/auth-pages` | Available for further polish | Yes | Avoid hardcoded Blade edits for now. |
| Frontend editor | `/admin/frontend` | Available, not changed in this pass | Yes | Useful later for public marketing surfaces. |
| Official provider allow-list | `/admin/settings/general` providers section | Verified `allowed_send_methods=["waba"]` | Yes | Current local state already narrows to official WABA only. |
| Default provider | `/admin/settings/general` providers section | Verified `default_send_method=waba` | Yes | Keep as production default. |
| Twilio availability | `/admin/settings/general` providers section | Available but not enabled in current local settings | Yes | Keep for later official path. |
| Baileys/unofficial provider visibility | `/admin/settings/general` providers section | Must remain disabled for pilots | Yes, mostly | Platform allow-list already prevents normal selection. |
| Security guardrails | `/admin/security` | Settings exist; not broadly tightened yet | Partial | Enable only the minimum launch controls first. |
| Template review | `/admin/security` | Setting exists | Yes | Recommended to turn on for launch. |
| API/webhook policy | `/admin/security` | Verified webhook signature policy defaults exist | Yes | Good admin surface, but WABA/Twilio webhook auth is controller-level too. |
| Packages/plans | `/admin/packages` | Available for scope restriction | Yes | Use to hide risky features from pilot plans. |
| AI key management | `/admin/api-keys` | Available | Yes | Keep pilot AI usage tightly controlled. |
| Roles/permissions | `/admin/roles`, `/admin/permissions` | Available | Yes | Use for managed SaaS operator workflows. |
| Workspace admin control | `/admin/workspaces` | Available | Yes | Good for managed onboarding. |

## What Can Be Solved by Dashboard Only

- Branding, footer, logos, favicon, theme
- Default official WhatsApp provider selection
- Provider allow-list at platform level
- Package and visibility restrictions
- Role and permission setup
- Basic security policy toggles

## What Needs Config

- Local/staging `.env` and `node/.env`
- `storage/installed` marker for non-installer local boot
- Node bridge URL/token alignment

## What Needs Code

- Payment gateway secret exposure fix before paying clients
- Any stricter server-side official-only fallback guard if dashboard restrictions are later bypassed
- Kenya-specific M-Pesa workflows
- Delivery zones and weekly merchant reporting

## What Should Not Be Changed Yet

- Billing and checkout architecture beyond local safety fixes
- Broad frontend redesign
- Vendor module deletion
- Bulk feature removal that would complicate future upstream updates
