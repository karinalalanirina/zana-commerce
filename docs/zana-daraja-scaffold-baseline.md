## Summary
This pass builds on the existing Zana Africa payment MVP instead of replacing it. Daraja sandbox sits beside the current manual payment flow, verification queue, export, and reporting seams.

| Existing payment capability | Already present? | Reused in this pass? | Notes |
|---|---|---|---|
| Storefront payment setup | Yes | Yes | Existing `payment_config_json` now also carries Daraja sandbox config. |
| Manual M-Pesa instructions | Yes | Yes | Remains the safe fallback when STK initiation is unavailable or fails. |
| Manual confirmation workflow | Yes | Yes | Daraja callbacks move orders into the same review/confirmation flow instead of bypassing it. |
| Kenya payment shortcuts | Yes | Yes | `Send M-Pesa STK Push` was added as a narrow extension on the order page. |
| Payment timeline/history | Yes | Yes | Daraja initiation, callback success, callback failure, and duplicate handling extend the same order timeline. |
| Verification queue | Yes | Yes | Callback outcomes feed existing payment states used by the queue. |
| Payment export | Yes | Yes | Export now includes Daraja request and callback fields without changing the export route. |
| Weekly merchant report | Yes | Yes | Existing summaries remain intact because Daraja writes into the same payment metadata/state model. |
| Template fallback/compliant send path | Yes | Yes | Unchanged by this pass. Daraja scaffold does not replace WhatsApp payment instruction flows. |
