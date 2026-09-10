# Phase 18 verification report — Support tickets, reviews, complaints, contact

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered (ST-01…ST-14)

- `includes/support.php` — ticket engine: `sup_create` (customer or guest
  with order-link validation), `sup_reply_customer` (blocked on closed,
  reopens resolved), `sup_close_customer`, staff `sup_list` (status /
  category / priority / free-text filters), `sup_get`, `sup_reply_staff`
  (public reply → pending + notify; internal note hidden from customers),
  `sup_set_status` / `sup_set_priority` (resolve/close notify the customer),
  review engine `rev_submit` (verified-purchase gate: product needs a
  delivered/completed order containing it; delivery needs own delivered
  delivery; one review per target), `rev_for_customer`,
  `rev_eligible_products` / `rev_eligible_deliveries`, `rev_moderate`
  (pending → approved/rejected only), `rev_product_summary` /
  `rev_product_list` (approved only), `sup_reports` (tickets by
  status/category/priority, review counts + average rating).
- `database/schema.sql` — `support_tickets.customer_id` nullable (guests),
  new `guest_email` + `order_id` (FK → orders, SET NULL), category enum
  extended with `complaint` and `refund`.
- `admin/tickets.php` — `tickets.manage` desk: filterable queue, full
  conversation with internal notes flagged, public reply + internal note
  forms, status/priority controls, order link, refund-ticket handoff to
  `admin/finance.php?order_id=`.
- `admin/reviews.php` — `reviews.moderate` desk: pending/approved/rejected
  filter, rating stats, approve/reject.
- `customer/tickets.php` (rewritten on the lib) — complaint/refund
  categories, optional own-order link, reply, close-own-ticket.
- `customer/reviews.php` — eligible products/deliveries only, 1–5 star
  submit, own review history with moderation status.
- `contact.php` — every message (guest or logged-in) now creates a real
  ticket (topic select, honeypot kept, staff notification email kept) and
  shows the `TKT-…` reference; customers get a Support tracking link.
- `product.php` — approved-only rating summary + review list + prefilled
  "Write a review" link (toggle-gated).
- Wiring: `admin/finance.php` refund form prefills `?order_id=`; Support +
  Reviews desks on the admin console (perm-gated); Reviews card on the
  customer dashboard (toggle-gated).

## Rules enforced (server-side)

- Ownership on every customer read/write (cross-customer ticket views 404;
  order links must belong to the customer; reviews need verified purchase).
- Closed tickets immutable for customers; staff must reopen before replying.
- Reviews start `pending`, need moderation, never editable after decision;
  only `approved` reviews render publicly.
- `tickets.manage` / `reviews.moderate` checked on every staff POST; CSRF on
  all forms; `support_tickets` + `reviews` feature toggles return 403.
- All ticket/review mutations audit-logged (`support.*`); customer-facing
  notify rows emitted for staff replies, resolve and close.

## Evidence (E2E `oyejo_p18`, 34/34 assertions green)

- T1 guest contact → complaint ticket with `guest_email`, reference shown.
- T2 customer refund ticket linked to order; reply stored (2 rows).
- T3 cross-customer isolation → HTTP 404.
- T4 staff reply + internal note (customer sees reply, never the note),
  open → pending → resolved, 2 `ticket_update` notifies, finance prefill.
- T5 product review pending → approved → 5 stars + reviewer on product
  page; duplicate, unverified-product and ineligible-customer blocked;
  delivery review accepted.
- T6 short-subject validation, customer close, reviews-toggle 403,
  dashboard + console links; T7 zero PHP fatals, ≥5 support audits.
- `php tests/foundation-check.php`: 296/296 (includes 6 new file checks +
  16 Phase 18 content checks; also registers the Phase 17 report, which
  Phase 17 forgot to add).

## Issues found and fixed (before approval)

1. Parallel same-file edits raced (last-write-wins + stray corruption):
   lost the schema column edit, the checker additions, three `require`
   lines and the contact reference line; also deleted two `notifications`
   columns and appended junk at EOF. All repaired, diff-audited hunk by
   hunk; schema re-imports clean. Lesson: one writer per file per turn.
2. First admin-desk draft used non-existent helpers (`admin_require`,
   `layout`, `csrf_check`, …). Rewritten in the real desk convention
   (`require_permission`, `csrf_verify`, header/footer) — verified live.
3. `order_items.name` required in E2E seed (test-script fix, not app code).

No known issues outstanding. Approved for commit under autopilot authority.
