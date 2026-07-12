# Zana M-Pesa Rollout Plan

| Payment capability | Phase | Native / Custom | Effort | Risk | Notes |
|---|---|---|---|---|---|
| Manual M-Pesa instructions | Phase A | Custom workflow on native inbox/order base | Low | Low | Fastest safe Kenya launch path. |
| Manual payment confirmation | Phase A | Custom workflow on native order base | Low | Low | Good for pilots. |
| Payment reminders | Phase A | Native send rails + custom process | Low | Medium | Use templates/quick replies first. |
| Optional payment links | Phase A | Partial | Medium | Medium | Existing gateway flows are not Kenya-first. |
| Better reference matching | Phase B | Custom | Medium | Medium | Add after pilot learning. |
| Payment event history | Phase B | Custom | Medium | Low | Good for auditability. |
| Weekly reports / reconciliation basics | Phase B | Custom | Medium | Low | Thin reporting layer. |
| Daraja / STK Push | Phase C | Custom | Hard | Medium | Should be isolated in Zana payment seam. |
| Callback handling | Phase C | Custom | Hard | Medium | Must be signed, logged, and workspace-scoped. |
| Automated reconciliation | Phase C | Custom | Hard | Medium | Phase after manual confidence. |

## Recommended Launch Sequence

1. manual M-Pesa instructions
2. manual confirmation
3. payment reminders
4. optional payment links only if a compliant gateway path is useful
5. Daraja/STK Push after pilot validation
