# Phase 19 verification report — Marketing, CMS, banners, FAQs, announcements

Date: 2026-09-10 · Autopilot gate: build → file check → test → correct → approve/commit.

## Scope delivered (MK-01…MK-12)

- `includes/marketing.php` — CMS engine: banners (`mk_banner_save`, live
  window/position queries), campaigns, announcements (all/customers/drivers
  audiences), FAQs, posts (slug auto-generation + uniqueness,
  draft/published with DB-clock `published_at` stamping), homepage
  sections (paragraph rendering, escaped), newsletter (subscribe /
  resubscribe / unsubscribe, unique emails), `mk_reports` (content counts
  + coupon usage).
- `admin/marketing.php` — tabbed desk (campaigns, banners, announcements,
  homepage, reports) with per-tab `marketing.campaigns` /
  `marketing.banners` enforcement; banners-only staff see only Banners.
- `admin/faqs.php` (`marketing.faqs`), `admin/posts.php`
  (`marketing.posts`, publishing controls + public view links),
  `admin/newsletter.php` (`marketing.newsletter`, status filter + remove).
- Public: `blog.php` + `post.php` (published-only, drafts 404),
  `newsletter.php` (subscribe/unsubscribe + honeypot), homepage renders
  live `home_top`/`home_bottom` banners, live campaigns, editable
  sections; header Blog link; footer Blog + Newsletter links.
- `customer/index.php` — customer-audience announcements on the dashboard.
- MK-03/MK-04 coupons and MK-05 featured products were already live
  (desks + checkout + homepage); Phase 19 adds coupon usage to the
  marketing reports and re-verifies the surfaces.
- `database/seeds.sql` — demo FAQs (4), welcome announcement, 2 homepage
  sections; admin console gains 4 perm-gated desks.

## Rules enforced (server-side)

- Every staff POST checks its granular `marketing.*` permission; managers
  without them get 403; customers are blocked from all desks.
- Public content is window/audience/status-gated in SQL: expired, future,
  inactive, draft and cross-audience items never render.
- Link URLs restricted to site paths or http(s); slugs validated + unique;
  end dates must follow start dates; all mutations audit-logged
  (`marketing.*`); CSRF on all forms.

## Evidence (E2E `oyejo_p19`, 48/48 assertions green)

- T1 campaigns/banners live on homepage; future-window banner hidden.
- T2 customer announcement on dashboard, hidden from homepage; expired
  announcement hidden everywhere.
- T3 FAQ create → public → delete → gone.
- T4 draft 404s + hidden from blog; publish → listed + rendered;
  duplicate slug blocked.
- T5 homepage section renders; seed sections present.
- T6 newsletter subscribe/duplicate/invalid/unsubscribe/resubscribe/desk
  remove.
- T7 manager 403s, customer 403, marketing manager admitted.
- T8 bad window, `javascript:` link, missing-id delete handled.
- T9 reports (newsletter counts, WELCOME10 usage), console desks, nav.
- T10 zero PHP fatals, ≥10 marketing audits.
- `php tests/foundation-check.php`: 322/322 (9 new file checks + 17 new
  content checks).

## Issues found and fixed (before approval)

1. Real bug: `published_at` was stamped with PHP time (Africa/Lagos) while
   reads compare against MySQL `NOW()` (UTC) — freshly published posts
   stayed hidden for an hour. Fixed by stamping `NOW()` on the database
   clock for both insert and draft→publish transitions; verified green on
   a fresh DB.
2. Test-script bug: a CSRF token from one session was reused for another
   user; corrected to same-session tokens.

No known issues outstanding. Approved for commit under autopilot authority.
