# Zana 24-Hour Payment Fallback

## Summary
Zana now keeps the existing native payment-instructions send path, but adds a compliant Meta-template fallback when a freeform payment message is rejected because the 24-hour customer service window has closed.

## What changed
- storefronts can optionally store one approved Meta template for payment instructions
- storefronts can optionally store one approved Meta template for payment reminders
- order payment sends still try the normal WhatsApp dispatch path first
- when that send fails with a 24-hour-window error, Zana attempts an approved WABA template send
- only if the compliant template send also fails does the UI fall back to copy/manual text

## Files changed
- [app/Support/ZanaPaymentTemplateFallback.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaPaymentTemplateFallback.php)
- [app/Http/Controllers/WaOrderController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaOrderController.php)
- [app/Http/Controllers/WaStorefrontController.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Http/Controllers/WaStorefrontController.php)
- [resources/views/user/store/storefront/edit.blade.php](/Users/karinachanmane/Projects/zana/zana-commerce/resources/views/user/store/storefront/edit.blade.php)
- [app/Support/ZanaManualPayment.php](/Users/karinachanmane/Projects/zana/zana-commerce/app/Support/ZanaManualPayment.php)

## Update-safety notes
- no billing logic changed
- no checkout logic changed
- no payment routes changed
- no schema changes were required
- fallback template ids live inside existing `wa_storefronts.payment_config_json`

## Storefront settings added
| Setting | Stored in | Used for | Notes |
|---|---|---|---|
| `payment_instruction_template_id` | `wa_storefronts.payment_config_json` | payment instructions and resend-link fallback | Optional |
| `payment_reminder_template_id` | `wa_storefronts.payment_config_json` | payment reminder fallback | Optional |

## Fallback logic
| Step | Behavior | Notes |
|---|---|---|
| 1 | Try normal native WhatsApp send | Preserves current operator workflow |
| 2 | Detect 24-hour-window rejection | Looks for Meta-style `131047` / re-engagement wording |
| 3 | Try approved WABA template | Uses existing `TemplateSender` |
| 4 | Record timeline + last-send metadata | Visible on order history |
| 5 | If template send fails, keep copy fallback | No crash, no silent drop |

## Operator guidance
- Choose approved utility templates that can accept the full payment message in the first body placeholder.
- This keeps the fallback generic enough for M-Pesa instructions, bank-transfer instructions, and payment-link reminders.
- If no approved template is configured, operators still get the copy/manual fallback.

## How to test
1. Open `/store/storefront/edit`
2. Choose approved fallback templates in the merchant payment setup section
3. Open `/store/orders/{id}`
4. Trigger `Send payment instructions` or `Send payment reminder`
5. Confirm normal send still works when the conversation is open
6. Simulate or reproduce a 24-hour-window rejection and confirm the approved template path is used instead of only copy fallback
