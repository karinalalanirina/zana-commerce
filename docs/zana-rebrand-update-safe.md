# Zana Rebrand Update-Safe Notes

Branding target:

- Main name: Zana
- Tagline: Turn WhatsApp chats into paid orders and repeat customers.
- Fallback tagline: WhatsApp tools for African businesses.
- Company: Fluxxeo

| Branding item | Dashboard setting available? | Action taken | Code changed? | File changed |
|---|---|---|---|---|
| App name | Yes | Verified `app_name=Zana` already set | No | None |
| Footer title | Yes | Verified Zana footer title already set | No | None |
| Footer description | Yes | Verified fallback positioning already set | No | None |
| Company name | Yes | Verified `site.company_name=Fluxxeo` already set | No | None |
| Sent-via branding | Yes | Verified `platform_branding_footer=Sent via Zana` | No | None |
| Logo set | Yes | Verified logo keys populated | No | None |
| Favicon | Yes | Verified favicon key populated | No | None |
| Theme colors | Yes | Available for tuning later | No | None |
| Auth page branding | Yes | Available but not changed in this pass | No | None |
| Code fallback brand name | N/A | Already reads DB branding via `App\Support\Brand` | No | None |

## Update-Safe Position

Branding is mostly DB-backed already, which is the safest possible outcome for Zana:

- `App\Support\Brand::name()` reads from `system_settings.app_name`
- admin appearance/auth/footer/general settings cover most surfaces
- no deep Blade replacement was needed in this pass

## Files Touched for Branding

None in this execution step. Rebrand is currently handled through settings, which is preferred.
