# Zana Tenant Isolation Smoke Test

Workspaces used:

- Zuri Beauty Store, workspace `2`
- Nairobi Fashion House, workspace `3`

Test login:

- `zuri.owner@example.com / password`

## Results

| Resource | Isolation tested? | Result | Fix needed? | File changed |
|---|---|---|---|---|
| Products | Yes | Pass | No | None |
| Orders | Yes | Pass | No | None |
| Campaigns | Yes | Pass | No | None |
| Contact tags route in same workspace | Yes | Pass after local migration fix | No further code fix | None |
| Cross-workspace contact tags route | Yes | Pass | No | None |
| Conversations | Yes | Pass | No | None |
| Exports | Yes | Pass | No | None |
| Reports | Yes | Pass | No | None |
| Provider config direct access | Yes | Pass | No | None |

## Verified Route Evidence

Authenticated as Zuri owner:

- own product edit: `200`
- Nairobi product edit: `404`
- own order: `200`
- Nairobi order: `404`
- own campaign: `200`
- Nairobi campaign: `404`
- own contact tags: failed first due missing local table, then restored by targeted migration
- Nairobi contact tags: `404`

## Focused Controller Evidence

Executed with deterministic feature tests instead of flaky scripted login:

- `Tests\Feature\TenantIsolationFocusedTest::test_team_inbox_show_only_loads_conversations_from_current_workspace`
  - same-workspace thread load succeeds
  - cross-workspace conversation id throws `ModelNotFoundException` before thread data is exposed
- `Tests\Feature\TenantIsolationFocusedTest::test_workspace_exports_only_include_rows_from_current_workspace`
  - contacts CSV contains only Zuri workspace contact rows
  - conversations CSV contains only Zuri workspace conversation rows
  - messages CSV contains only Zuri workspace inbox-message rows
- `Tests\Feature\TenantIsolationFocusedTest::test_message_history_export_uses_current_workspace_scope`
  - report export passes the active workspace id into `App\Services\UnifiedMessageStream`
- `Tests\Feature\TenantIsolationFocusedTest::test_waba_health_json_is_scoped_to_current_workspace_config`
  - same-workspace WABA config resolves
  - cross-workspace provider config id fails lookup before health data is returned

## Local Fix Applied

The only issue found was local schema drift:

- ran `database/migrations/2026_07_05_000000_create_contact_tag_table.php`

This was not a tenant leak; it was a missing local pivot table.

## Conclusion

- Safe enough for continued local/staging work: Yes
- Safe enough for pilot clients with managed onboarding: Yes
- Safe enough for paying clients right now: Partial

## Status After Security Hardening Pass

- no new cross-workspace leak was introduced by the official-only enforcement or payment-gateway changes
- focused regression tests now cover conversations, exports, reports, and provider-config access in addition to the earlier route checks
- broader API/UI exploration is still worthwhile later, but the previously open tenant-isolation matrix items in this pass are now covered

## Must Be Smoke-Tested Before Launch

- any new workspace-scoped endpoints added after this hardening pass
- especially AI, analytics, and future payment/order admin endpoints as they are introduced
