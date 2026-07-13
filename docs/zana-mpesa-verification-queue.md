## Summary
The order list now includes a verification-state filter, a lightweight queue block, payer-note/reference search, and derived merchant-facing review states.

| Queue/verification feature | Added? | Where shown | Source of data | Notes |
|---|---|---|---|---|
| Awaiting Verification filter | Yes | `/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/orders/index.blade.php` | `customer_says_paid` payment status | Derived label only; backend status unchanged. |
| Missing Reference filter | Yes | Orders page verification dropdown + queue card | Empty `transaction_reference` while awaiting verification | Helps merchants find unmatchable claims quickly. |
| Reference Recorded filter | Yes | Orders page verification dropdown + queue card | Non-empty `transaction_reference` while awaiting verification | Supports faster review. |
| Search by payer clue | Yes | Orders search box | `payer_note` | Covers “paid from spouse phone” / branch note scenarios. |
| Verification badge on each row | Yes | Orders table | `ZanaPaymentVerification::derivedState()` | Separate from payment-state badge. |
| Detail-page verification summary | Yes | `/store/orders/{id}` | Existing payment metadata | Keeps reference, payer clue, and verification state together. |
