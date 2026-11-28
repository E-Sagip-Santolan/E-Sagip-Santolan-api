# E-Sagip Backend — Phase Update Log

Living changelog tracking every backend update across Phases 1–8 for `esagip-api`.

> **Entry format for each update:**
> - *Timestamp (italic)*
> - **Brief description** of what changed
> - **Files & schema/API impact**
> - **Security / verification notes** (tests, Postman, next step)

---

## Phase 2 — Accounts, Roles and Approval

### Update 2.9: Volunteer Approve & Reject (`POST /api/volunteers/{id}/approve`, `POST /api/volunteers/{id}/reject`)
*November 28, 2026 — 11:31 PM PHT*

**Brief description:**
Implemented volunteer approval and rejection endpoints (optional `reason`, pending-only guard, and `activity_logs` recording with IP address).

**What changed:**
- Added `RejectVolunteerRequest`, `VolunteerController::approve()` and `VolunteerController::reject()`, routes under `permission:volunteer_approvals` in `routes/api.php`, and `tests/Feature/VolunteerApprovalTest.php`.

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — all tests passing.
- **Next step:** Implement Superadmin-only admin management (`GET /api/admins`, `POST /api/admins`, `PUT /api/admins/{id}`).

---

### Update 2.8: Volunteer List, Filters & Summary (`GET /api/volunteers`, `GET /api/volunteers/summary`)
*November 28, 2026 — 11:18 PM PHT*

**Brief description:**
Implemented the admin-only volunteer listing endpoint with filtering (`status`, `skill`, `availability`, `address_area`, `search`) and pagination, plus the status summary endpoint. Contact details remain restricted to authorized admins (`volunteer_approvals` or `superadmin`).

**What changed:**
- Added `ListVolunteersRequest`, `VolunteerResource`, `VolunteerController` (`index`, `summary`), routes under `permission:volunteer_approvals` in `routes/api.php`, and `tests/Feature/VolunteerListTest.php`.

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — all tests passing.
- **Next step:** Implement `POST /api/volunteers/{id}/approve` and `POST /api/volunteers/{id}/reject`.

---

### Update 2.7: Own Profile (`GET /api/me`, `PUT /api/me`)
*November 28, 2026 — 9:58 PM PHT*

**Brief description:**
Implemented `GET /api/me` and `PUT /api/me` for volunteers and admins to view and partially update their own profiles while keeping `email`, `role`, `status`, and `is_active` locked.

**What changed:**
- Added `UpdateProfileRequest`, `MeResource`, `ProfileController` (`show`, `update`), routes `GET /api/me` and `PUT /api/me` in `routes/api.php` (removed unused starter `/api/user` route), and `tests/Feature/ProfileTest.php`.

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — all tests passing.
- **Next step:** Implement `GET /api/volunteers` (with filters + admin-only contact details) and `GET /api/volunteers/summary`.

---

### Update 2.6: Public Skills List (`GET /api/skills`)
*November 28, 2026 — 9:15 PM PHT*

**Brief description:**
Implemented `GET /api/skills` as a public endpoint so the Flutter mobile registration screen can load available skills before login.

**What changed:**
- Added `app/Http/Resources/SkillResource.php`, `app/Http/Controllers/SkillController.php`, route `GET /api/skills` (`throttle:api`) in `routes/api.php`, and `tests/Feature/SkillTest.php`.

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — all tests passing.
- **Next step:** Implement `GET /api/me` and `PUT /api/me`.

---

### Update 2.5: Logout (`POST /api/logout`)
*November 28, 2026 — 8:59 PM PHT*

**Brief description:**
Implemented `POST /api/logout` to revoke only the current Sanctum Bearer token used in the request.

**What changed:**
- Added `AuthController::logout()` in `app/Http/Controllers/AuthController.php`, protected route `POST /api/logout` (`auth:sanctum`, `throttle:api`) in `routes/api.php`, and `tests/Feature/LogoutTest.php`.
- Verified `200` (revokes only the active token while keeping other device tokens intact) and `401` (missing or invalid token).

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — all tests passing.
- **Next step:** Implement `GET /api/skills`, `GET /api/me`, and `PUT /api/me`.

---

### Update 2.4: Admin Portal Login (`POST /api/admin/login`)
*November 28, 2026 — 8:42 PM PHT*

**Brief description:**
Implemented admin and superadmin login (`POST /api/admin/login`) with Sanctum token issuance, role and active-account guards, rate limiting (`throttle:login`), and `activity_logs` recording with IP address.

**What changed:**
- Added `AuthController::adminLogin()` (with shared credential/token helpers) in `app/Http/Controllers/AuthController.php`, route `POST /api/admin/login` in `routes/api.php`, and `tests/Feature/AdminLoginTest.php`.
- Verified `200` (active `admin` with assigned permissions + `superadmin` with all 6 permissions + `activity_logs` IP entry), `401` (wrong credentials), `403` (volunteers & disabled admins blocked), `422` (validation), and `429` (rate limit).

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — all tests passing.
- **Next step:** Implement `POST /api/logout`.

---

### Update 2.3: Volunteer Login (`POST /api/login`)
*November 28, 2026 — 8:30 PM PHT*

**Brief description:**
Implemented mobile volunteer login (`POST /api/login`) with Sanctum token issuance, role and active-account guards, rate limiting (`throttle:login`), and `activity_logs` recording with IP address.

**What changed:**
- Added `app/Http/Requests/LoginRequest.php`, `AuthController::login()`, route `POST /api/login` in `routes/api.php`, and `tests/Feature/LoginTest.php`.
- Verified `200` (approved/pending/rejected volunteers + `activity_logs` IP entry), `401` (wrong credentials), `403` (admins blocked & disabled accounts blocked), `422` (validation), and `429` (rate limit).

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — **15 / 15 tests passed (93 assertions)**.
- **Next step:** Implement `POST /api/admin/login`.

---

### Update 2.2: Phase 2 Foundation, Superadmin CLI & Volunteer Registration (`POST /api/register`)
*November 28, 2026 — 8:22 PM PHT*

**Brief description:**
Built the Phase 2 Laravel foundation on top of the merged Phase 2 database migrations, added the Artisan command to create the first Superadmin, and implemented the first Phase 2 API endpoint (`POST /api/register`) with full validation, Sanctum token issuance, and PHPUnit tests.

**What changed:**
- **Database & Indexes:** Added `database/migrations/2026_11_28_190000_add_phase2_filter_indexes.php` (without modifying merged migrations) to index `users` (`role`, `status`, `is_active`), `volunteer_profiles` (`availability`, `address_area`), and `activity_logs` (`module`, `action`, `created_at`).
- **Models:** Updated `app/Models/User.php` (`HasApiTokens`, relationships, role/permission helpers) and created `VolunteerProfile`, `Skill`, `AdminPermission`, `ConsentRecord`, and `ActivityLog` (with `ActivityLog::record()` capturing `ip_address`).
- **Security & Middleware:** Created `EnsureRole` (`role`) and `EnsurePermission` (`permission`) middleware, registered them in `bootstrap/app.php` with standardized `429` JSON responses, and configured `Password::defaults()` (min 8 chars, letters + numbers) and `login` / `register` / `api` rate limiters in `AppServiceProvider`.
- **CLI Command:** Added `php artisan esagip:create-superadmin` (`CreateSuperadminCommand`) and verified Superadmin creation.
- **Endpoint (`POST /api/register`):** Added `RegisterRequest`, `UserResource`, `AuthController::register()`, and route `POST /api/register` (`throttle:register`), creating `users`, `volunteer_profiles`, `volunteer_skills`, and `consent_records` inside a DB transaction.

**Verification & Documentation Notes:**
- PHPUnit: `php artisan test` — **8 / 8 tests passed (52 assertions)** (`CreateSuperadminCommandTest` + `RegisterTest` + defaults).
- **Next step:** Implement `POST /api/login`, `POST /api/admin/login`, and `POST /api/logout` (with `activity_logs` IP recording).

---

### Update 2.1: Phase 2 API Contract & Postman Collection
*November 28, 2026 — 6:30 PM PHT*

**Brief description:**
Aligned `docs/api.md` 1:1 with `docs/erd.dbml` and added a ready-to-import Postman collection divided into **Mobile App** and **Web Admin Portal** folders (with overlapping endpoints labeled `(mobile and web)`).

**What changed:**
- Updated `docs/api.md` with the live Render URL, password rules, rate limits, activity logging rules, ERD permission names (`volunteer_approvals`, `operation_management`, `attendance_confirmation`, `community_publishing`, `reports_exports`, `activity_logs`), Superadmin-only `/api/admins*`, public `GET /api/skills`, and sample JSON for all 15 Phase 2 endpoints.
- Added `docs/postman/esagip-api.postman_collection.json` and `docs/postman/local.postman_environment.json` using `{{base_url}}` and empty `{{token}}` with dummy payloads only.

**Verification & Documentation Notes:**
- Validated both Postman JSON files and confirmed 100% column alignment with merged Phase 2 migrations (`#11`).
- No secrets, `.env` values, or real credentials included.

---

## Phase 1 — Project Setup and Foundation *(Completed)*

### Update 1.0: Repository, Laravel Setup, Health Endpoint, CORS & Render Deployment
*November 27, 2026*

**Brief description:**
Set up the `esagip-api` Laravel 13 repository, Sanctum, standardized JSON exception formatting (`bootstrap/app.php`), CORS configuration, Dockerfile, live deployment on Render connected to Neon PostgreSQL, and `GET /api/health`.

**What changed:**
- Initialized Laravel project, `routes/api.php` (`GET /api/health`), `bootstrap/app.php` JSON error handlers (`401`, `404`, `422`, `403`, `500`), `config/cors.php`, and `Dockerfile`.
- Added project rules and context (`AGENTS.md`, `docs/context.md`, `docs/erd.dbml`, `docs/api.md`).
- Merged Phase 2 base migrations and `SkillSeeder` (`#11`).
