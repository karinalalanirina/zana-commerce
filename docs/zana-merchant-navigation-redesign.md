# Zana Merchant Navigation Redesign

## Summary

Merchant workspace navigation was refactored into a business-first Zana top navigation while keeping the existing merchant route URLs, controllers, and admin sidebar intact.

The new merchant nav adds:

- rounded pill tabs
- route-safe mapping to existing merchant pages
- active-state grouping by business area
- a horizontally scrollable nav row with left/right controls
- a curated More dropdown based on the supplied reference
- a business-first dashboard layer above the legacy WADesk dashboard

The legacy operational dashboard was not removed. It now sits inside a collapsed `Advanced operational widgets` section.

## Files changed

- `/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/UserPagesController.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/components/user/header.blade.php`
- `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/dashboard/index.blade.php`

## Routes inspected

- `/dashboard`
- `/team-inbox`
- `/team-inbox/analytics/team`
- `/team-inbox/members`
- `/message-history`
- `/store/orders`
- `/store/products`
- `/store/storefront`
- `/wa-campaigns`
- `/broadcasts`
- `/ai-assistants`
- `/ai-training`
- `/analytics`
- `/meta-ads`
- `/flows`
- `/templates`
- `/devices`
- `/integrations`
- `/settings`
- `/webhooks`
- `/guidebook`
- `/support`
- `/activity-log`
- `/contacts`
- `/chat`
- `/developers`
- `/more`

## New tab mapping

| New Zana Tab | Existing Route Used | Old Label/Location | Icon | Active Route Logic | Notes |
|---|---|---|---|---|---|
| Dashboard | `/dashboard` | Existing dashboard top nav | dashboard grid | `/dashboard` | Preserved route |
| Inbox | `/team-inbox` | Existing Team Inbox | inbox panel | `/team-inbox*`, `/message-history*` | Message history now groups under Inbox |
| Orders | `/store/orders` | Store sidebar Orders | order bag | `/store/orders*` | Uses existing store order pages |
| AI Assistant | `/ai-assistants` or `/ai-training` fallback | More / AI tools | AI bot | `/ai-assistants*`, `/ai-training*` | Fallback keeps existing AI route surface |
| Campaigns | `/wa-campaigns` | Existing Campaigns top nav | message bubble | `/wa-campaigns*`, `/broadcasts*` | Broadcasts grouped into Campaigns |
| Catalog | `/store/products` | Store sidebar Products | catalog sheet | `/catalog*`, `/store/products*` | Keeps product management route stable |
| Storefront | `/store/storefront` | Store sidebar Storefront | storefront | `/store/storefront*`, `/store` | Uses existing storefront settings page |
| Reports | `/analytics` | More / Analytics | chart | `/analytics*` | Keeps analytics route stable |
| More | `/more` + dropdown links | Existing More page | ellipsis | all secondary routes | Dropdown contains curated secondary tools |

## Old route preserved?

Yes.

## Feature flag used?

Yes.

- `ZANA_MERCHANT_NAV_V2=true`
- Config source: `/Users/karinachanmane/Projects/zana/zana-commerce/config/zana.php`

## Responsive behavior

- The new nav lives in its own row below the merchant header.
- Tabs are horizontally scrollable.
- Left/right arrow buttons are shown on `sm+`.
- On narrow screens, the row can still be finger-scrolled.
- Existing mobile hamburger navigation remains available as a fallback surface.

## More menu contents

- Growth tools: Meta Ads, Flows, Templates
- Setup: Devices / WABA Accounts, Integrations, Team, Settings, Webhooks
- Support: Guidebook, Support, Activity Log
- Also available: Contacts, Message History, Quick Send, Developers / API
- Footer link: Open full More page

## Known limitations

- Tab visibility still respects existing workspace-role restrictions, so lower-permission members may not see every business tab.
- The AI Assistant tab uses the existing AI route surface and falls back from `/ai-assistants` to `/ai-training` based on plan feature access.
- Dashboard business summary cards use lightweight workspace-level rollups; they do not replace the deeper analytics in the legacy dashboard.

## How to test

1. Set `ZANA_MERCHANT_NAV_V2=true` in `.env` if you want to force the new nav explicitly.
2. Load `/dashboard` as a merchant workspace user.
3. Confirm the new nav row shows:
   - Dashboard
   - Inbox
   - Orders
   - AI Assistant
   - Campaigns
   - Catalog
   - Storefront
   - Reports
   - More
4. Click each tab and confirm it opens the mapped existing page.
5. Open More and confirm it contains:
   - Meta Ads
   - Flows
   - Templates
   - Devices / WABA Accounts
   - Integrations
   - Team
   - Settings
   - Webhooks
   - Guidebook
   - Support
   - Activity Log
6. Confirm `/dashboard` shows the business-first cards first.
7. Expand `Advanced operational widgets` and confirm the legacy WADesk dashboard still appears below.
8. Confirm the admin console sidebar remains unchanged by visiting `/admin`.
