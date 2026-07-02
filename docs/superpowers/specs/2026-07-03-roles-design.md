# Roles & Permissions — Design

**Approved 2026-07-03.** Editor scope: content only.

## Roles
- `admin`: full panel incl. Users (role management), Queries, Newsletter.
- `editor`: Dashboard, Products, Categories & makes, Blogs. No customer data.
- `user`: customer, no panel access.

## Middleware
`App\Http\Middleware\EnsureRole` with variadic roles: `role:admin,editor`. Replaces AdminMiddleware (file deleted, alias `admin` removed, alias `role` added in bootstrap/app.php). Unauthenticated → auth middleware handles; wrong role → redirect('/') with message.

## Routes (routes/web.php)
Admin group middleware becomes `['auth','verified','role:admin,editor']`. Inside it, a nested `Route::middleware('role:admin')` group wraps: users routes (+ new `PATCH users/{user}/role` → `users_update_role`, name `admin.users.role`), newsletter, query-form.

## Backend
- `users_listing()`: optional `?role=` filter (admin|editor|user); no forced role=user filter; selects include role; keywords search unchanged.
- `users_update_role(Request, User $user)`: validate `role in admin,editor,user`; reject `$user->id === auth()->id()` with 422 `{message}`; save; return JSON.
- Dashboard endpoint unchanged (counts only, no emails leaked to editors? recent_queries contains emails — restrict: only include `recent_queries` when admin; editors get empty array).

## Frontend
- Sidebar: groups filtered by `auth.user.role` — editor sees Dashboard/Catalog/Content(Blogs only? No: Blogs+... Users lives in Content group → move Users into admin-only rendering). Concretely each nav item gets `roles: ['admin','editor']` or `['admin']`; filter on render.
- Users/Index.vue: role tabs (All/Admins/Editors/Customers via ?role=), role badge (admin red, editor blue, user zinc), role `<select>` per row → axios PATCH, disabled on own row ("You" badge), success/error feedback inline.
- Dashboard.vue: hide Recent queries panel + Review queries button when `role !== 'admin'`.

## Tests (Feature/RoleManagementTest)
- editor GET /admin/products → 200; GET /admin/users → 302 to /
- user GET /admin/products → 302
- admin PATCH role user→editor → 200 + DB updated
- editor PATCH role → 302 (blocked by middleware)
- admin PATCH own role → 422, DB unchanged
- PATCH invalid role → 422

## Out of scope
Granular permissions, audit log, invitations.
