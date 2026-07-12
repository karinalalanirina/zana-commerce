# Zana Local Demo Workspace

Demo workspace:

- Zuri Beauty Store

Verified demo products already in workspace `2`:

- Hair Oil
- Face Serum
- Body Lotion
- Wig
- Perfume

Suggested demo tags:

- New Inquiry
- Interested
- Awaiting Payment
- Paid
- Repeat Customer

Suggested demo quick replies:

1. price reply
2. delivery fee reply
3. payment instruction reply
4. payment confirmation reply
5. follow-up reply

## Demo Flow

1. customer asks about a product
2. agent replies from shared inbox
3. agent records or creates the order
4. agent marks payment state manually
5. agent sends payment instruction over official WhatsApp path
6. agent applies `Awaiting Payment`
7. agent applies `Paid`
8. agent schedules or drafts follow-up

## What Works Natively

- shared inbox
- contacts
- tags
- products/storefront
- orders
- official WhatsApp send rails

## What Is Manual but Acceptable for MVP

- M-Pesa instruction sending
- payment confirmation
- delivery quote handling
- weekly merchant reporting

## What Needs Later Custom Code

- manual M-Pesa workflow UI
- payment event history and matching
- delivery zones/fees
- weekly Zana merchant report layer
