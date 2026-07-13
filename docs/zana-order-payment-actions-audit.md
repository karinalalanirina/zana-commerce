## Summary
Before refactor, the payment buttons on `/store/orders/{id}` were handwritten directly in the order Blade while relying on readiness helpers and a hidden `payment_action` input for execution.

| Action ID | Current label | Visible when | Disabled when | Current group/location | Notes |
|---|---|---|---|---|---|
| `send_mpesa_instructions` | Send M-Pesa instructions | `$hasMpesaShortcut` | Never | Kenya payment shortcuts | Kenya-first shortcut button. |
| `customer_says_paid` | Customer says paid | Always | Never | Kenya payment shortcuts | Moves order into verification flow. |
| `paid_confirmed` | Paid confirmed | Always | Never | Kenya payment shortcuts | Confirms payment state. |
| `generate_paystack_link` | Generate Paystack link | Always | `empty($paystackReadiness['can_generate'])` | Bottom action row | Provider-specific action. |
| `generate_paystack_link_send` | Generate Paystack link + send | Always | `empty($paystackReadiness['can_generate'])` | Bottom action row | Provider-specific action with send path. |
| `send_daraja_stk` | Send M-Pesa STK Push | `$darajaReadiness['enabled']` | `empty($darajaReadiness['can_initiate'])` | Bottom action row | Hidden entirely when Daraja flag/readiness says off. |
| `send_instructions` | Send general payment instructions / Send payment instructions | Always | Never | Bottom action row | Label depends on `$hasMpesaShortcut`. |
| `send_reminder` | Send payment reminder | Always | Never | Bottom action row | Messaging action. |
| `sendPaymentLink()` | Send payment link | `$storedExternalPaymentLink || $order->payment_link` | Never | Bottom action row | JS helper, not `payment_action`. |
| `resend_link` | Resend payment link | `$storedExternalPaymentLink || $order->payment_link` | Never | Bottom action row | Uses standard action flow. |
| `payment_failed` | Mark payment failed | Always | Never | Bottom action row | State update action. |
| `refunded` | Mark refunded | Always | Never | Bottom action row | State update action. |
| `generatePaymentLink()` | Generate Razorpay link + send | `!$hideIndiaMerchantPayments` | Never | Bottom action row | JS helper, India-specific path. |
| form submit | Save | Always | Never | Bottom action row | Normal form submit, not `payment_action`. |

| Readiness/source | Used for which actions? | Source file/helper | Notes |
|---|---|---|---|
| Paystack readiness | `generate_paystack_link`, `generate_paystack_link_send` | `App\Support\ZanaPaystackMerchantLink::readiness()` | Determines disabled state and notes. |
| Daraja readiness | `send_daraja_stk` and Daraja info block | `App\Support\ZanaDarajaSandbox::readiness()` | Determines visibility plus disabled state. |
| M-Pesa shortcut availability | `send_mpesa_instructions`, general instructions label | `App\Support\ZanaKenyaPaymentShortcut` | Kenya-specific operator flow. |
| Stored payment link presence | `sendPaymentLink()`, `resend_link` | `ZanaAfricaPayments::externalPaymentLink()` and `$order->payment_link` | Simple visibility gate. |
| India payments visibility | Razorpay action and WhatsApp Pay area | `ZanaAfricaPayments::hidesIndiaMerchantPayments()` | Market/UI gate. |
| Hidden input submit wiring | All `payment_action` actions | `resources/views/user/store/orders/show.blade.php` + `WaOrderController::updateStatus()` | Shared JS submit path. |
