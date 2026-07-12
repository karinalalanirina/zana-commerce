# Zana Commerce MVP Mapping

| Zana MVP Feature | Existing WADesk feature/table/controller | Native / Partial / Missing | Code needed later? | Notes |
|---|---|---|---|---|
| Shared inbox | `TeamInboxController`, `conversations`, `inbox_messages` | Native | No | Strong launch building block. |
| Contacts / tags / notes | `contacts`, `ContactsController`, `Tag`, contact tags pivot | Partial | No immediate code after local migration | Contact tags now work locally after targeted migration. |
| Catalog / storefront | `WaProduct`, `StorefrontPublicController`, `WaStorefrontController` | Native | No | Good MVP base. |
| Create order from conversation | `WaOrder`, storefront/order controllers | Partial | Yes | Likely needs smoother operator workflow. |
| Payment status | `wa_orders` status fields | Partial | Yes | Manual payment state is possible, but Zana flow needs cleaner UX. |
| Manual M-Pesa confirmation | No native Kenya flow | Missing | Yes | P0 customization later. |
| Delivery zones | No Kenya-first delivery rules verified | Missing | Yes | P1 after pilot proof. |
| Payment reminder | Inbox/campaign/template send paths | Partial | Yes | Can start manually with templates/quick replies. |
| Abandoned inquiry follow-up | Flows/campaigns/storefront abandon hooks exist | Partial | Yes | Good candidate for controlled flow setup later. |
| Simple sales dashboard | Admin/report/order history surfaces exist | Partial | Yes | Need Zana-specific merchant summary. |
| Native WhatsApp send for payment instructions/reminders | WABA/Twilio dispatchers | Native | No | Use official WABA path. |
| Weekly merchant/client reporting | Admin analytics and order history exist | Missing for Zana format | Yes | Thin reporting layer needed. |

## Smallest Update-Safe MVP Path

1. use existing inbox, contacts, tags, storefront, products, and orders as the base
2. keep payment confirmation manual first
3. deliver M-Pesa as a Zana-specific workflow seam later, not a broad core rewrite
4. add reporting as a thin read layer over existing commerce tables
