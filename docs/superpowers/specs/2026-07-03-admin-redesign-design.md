# Admin Panel v2 — Design

**Approved by user 2026-07-03.** Style: Auto (dark+light toggle). Scope: everything.

## Stack
shadcn-vue components already in repo (`resources/js/components/ui/*`), Tailwind + CSS variables, lucide-vue-next icons. No new dependencies (HeroUI rejected — React-only, app is Vue 3). Theme via existing `useAppearance` composable (`resources/js/composables/useAppearance.ts`, light/dark/system class toggle); add a topbar toggle button inside admin.

## Backend
- `AdminController::dashboard()` replaces the empty closure route (`routes/web.php` admin group). Returns Inertia `Dashboard` with:
  - `stats`: own_products (website=suprememotors), total_products, users (role=user), newsletter, queries, contacts, published_blogs
  - `recent_queries`: latest 5 (id, company, contact_name, email, created_at)
  - `recent_products`: latest 5 own products (id, stock_code, title, price, front_image, created_at)
  - `by_country`: product counts per country; `top_makes`: top 5 makes by product count
- Feature test `AdminDashboardTest`: admin sees 200 + props; regular user redirected.

## Frontend structure
`resources/js/components/admin/`:
- `LiveClock.vue` — props `label`, `timeZone`; Intl.DateTimeFormat HH:MM:SS ticking via 1s interval (cleanup on unmount); shows date + UTC offset.
- `StatCard.vue` — props `title`, `value`, `icon`, optional `accent`; oversized numeric, brand-red accent bar.
- `PageHeader.vue` — title, subtitle, actions slot.
- `AdminPagination.vue` — Laravel paginator links prop.
- `EmptyState.vue` — icon + message + optional action.

Pages (all use `AppLayout` sidebar variant):
- `Dashboard.vue` — clocks strip (China Asia/Shanghai, Japan Asia/Tokyo, Pakistan Asia/Karachi), stat grid, recent tables, country/make breakdowns.
- Rebuilt lists: `Admin/Products/Index.vue` (thumbnails, stock code, attribute chips, price, search), `Admin/Categories/Index.vue`, `Admin/Users/Index.vue`.
- Rebuilt forms: `Admin/Products/Create.vue`, `Edit.vue`, `Admin/Categories/Create.vue`, `Edit.vue` — card-sectioned, same submit/axios logic and payloads (do not change request contracts).
- New pages (routes exist, components missing): `Admin/Newsletter/Index.vue`, `Admin/QueryForm/Index.vue`, `Admin/QueryForm/View.vue`, `Admin/Blogs/Index.vue`, `Admin/Blogs/Create.vue`, `Admin/Blogs/Edit.vue` (blog forms follow AdminController@blogs_store/update contracts incl. Quill editor + tags array + publish_status).
- `AppSidebar.vue` — nav: Dashboard, Products, Categories, Users, Newsletter, Queries, Blogs; brand block; active states.

## Visual language
- Dark: zinc-950 surfaces, zinc-900 cards, `#8e2527` accents/CTAs, white text; Light: white surfaces, zinc-100 page bg, same red accents.
- All components use shadcn CSS-var tokens + `dark:` utilities so the toggle works everywhere.
- Clocks monospace (`font-mono`), tabular-nums.

## Verification
Feature test green; `npm run build`; browser walk of every admin page in both themes with screenshots; no console errors.

## Out of scope
Public site pages; changing any backend list/store/update logic beyond the new dashboard endpoint.
