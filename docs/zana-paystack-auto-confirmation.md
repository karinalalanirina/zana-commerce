## Summary
Auto-confirmation is conservative. Zana only auto-confirms when the callback is clearly valid, signed, matched, and the paid amount exactly matches the order total.

| Callback outcome | System action | Merchant still needs review? | Notes |
|---|---|---|---|
| Signed success callback with exact amount match | Mark `paid_confirmed` and order `paid` | No | Safest low-friction auto-confirm case. |
| Signed success callback with amount mismatch | Mark `customer_says_paid` and order `confirmed` | Yes | Merchant should verify amount discrepancy. |
| Signed failed callback | Mark `payment_failed` and order `cancelled` | Yes, if merchant wants to override manually | Honest failure state. |
| Duplicate callback | Ignore duplicate state transition | No | Duplicate counter is still stored. |
| Unmatched or bad-signature callback | No order change | Yes | Manual flow remains intact. |
