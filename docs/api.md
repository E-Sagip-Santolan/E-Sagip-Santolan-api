# E-Sagip API (Phase 2)

Base URL (local): `http://127.0.0.1:8000/api`
Base URL (live): `https://e-sagip-santolan-api-1.onrender.com/api`

This file is the contract between the backend (Palagam) and the two frontends (Soleybar's mobile app, Libres' admin portal). Use these shapes in your mock data. If a shape needs to change, tell Palagam first and update this file in the same pull request.

---

## 1. Conventions

### Headers

| Header | Value | When |
|---|---|---|
| `Accept` | `application/json` | Every request |
| `Content-Type` | `application/json` | Requests with a body |
| `Authorization` | `Bearer <token>` | Every endpoint except health, skills, register, and the two login routes |

### Response shapes

Success:

```json
{ "data": {}, "message": "OK" }
```

Paginated list success:

```json
{
  "data": [],
  "message": "OK",
  "meta": { "page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

Error:

```json
{ "message": "Human-readable message", "errors": { "field": ["Reason"] } }
```

`errors` appears only on `422`. Other errors have only `message`. Stack traces are never returned. Dates are ISO 8601 strings (example: `2026-12-01T08:30:00+08:00`). Field names are `snake_case`.

### Password rules

- Minimum **8 characters**, containing at least **one letter** and **one number**.
- Passwords are always stored hashed and are never returned in responses or written to logs.

### Rate limiting

- `POST /api/login` and `POST /api/admin/login`: **5 attempts per minute** per email + IP address.
- `POST /api/register`: **5 attempts per minute** per IP address.
- Authenticated API endpoints: **60 requests per minute** per user (or IP).
- Exceeding a limit returns `429 Too Many Requests`.

### Activity logging

Every sign-in (`POST /api/login`, `POST /api/admin/login`) and every admin action (`approve`, `reject`, `create admin`, `update admin`) writes a row to `activity_logs` with `user_id`, `action`, `module`, `details`, `ip_address`, and `created_at`. Passwords and tokens are never logged.

### Roles, statuses, and permissions

All names match `docs/erd.dbml`.

| Item | Values |
|---|---|
| `role` | `volunteer`, `admin`, `superadmin` |
| `status` | `pending`, `approved`, `rejected` (volunteers start as `pending`; admins are `approved`) |
| `is_active` | `true`, `false` (disabled accounts cannot log in) |
| `availability` (volunteer) | `available`, `unavailable`, `on_operation` |
| `permissions` (admin) | `volunteer_approvals`, `operation_management`, `attendance_confirmation`, `community_publishing`, `reports_exports`, `activity_logs` |

A `superadmin` has every permission automatically and is the only role that can manage admin accounts (`/api/admins*`). A volunteer's `permissions` array is always `[]`.

#### RBAC presets (Admin Portal)

| Preset | `role` | `permissions` |
|---|---|---|
| Super Administrator | `superadmin` | All permissions + admin management |
| Operations Manager | `admin` | `volunteer_approvals`, `operation_management`, `attendance_confirmation` |
| Content Officer | `admin` | `community_publishing` |
| Auditor | `admin` | `reports_exports`, `activity_logs` |

### User object

Used by register, login, admin login, and `/me`.

```json
{
  "id": 12,
  "name": "Juan Dela Cruz",
  "email": "juan@example.com",
  "role": "volunteer",
  "status": "pending",
  "is_active": true,
  "rejection_reason": null,
  "permissions": []
}
```

### Standard errors

| Code | Meaning | Body |
|---|---|---|
| 401 | Missing, wrong, or expired token | `{ "message": "Unauthenticated." }` |
| 403 | Logged in, but not allowed | `{ "message": "You do not have access to this action." }` |
| 404 | Route or record not found | `{ "message": "Not found." }` |
| 422 | Validation failed | `{ "message": "The given data was invalid.", "errors": { "email": ["The email field is required."] } }` |
| 429 | Too many requests | `{ "message": "Too many attempts. Try again later." }` |
| 500 | Server error | `{ "message": "Server error." }` |

Every endpoint below can return `429`. Every protected endpoint can return `401`. They are not repeated in each section unless they have a special message.

---

## 2. Endpoint summary

| Method | Path | Who can call it | Purpose |
|---|---|---|---|
| GET | `/api/health` | Public | API health check |
| GET | `/api/skills` | Public | List available skills (used on registration and profile) |
| POST | `/api/register` | Public | Volunteer sign-up (starts as `pending`) |
| POST | `/api/login` | Public | Volunteer login (mobile) — logs sign-in with IP |
| POST | `/api/admin/login` | Public | Admin and superadmin login (web) — logs sign-in with IP |
| POST | `/api/logout` | Any logged-in user | Revoke the current Sanctum token |
| GET | `/api/me` | Any logged-in user | Get own profile |
| PUT | `/api/me` | Any logged-in user | Update own profile |
| GET | `/api/volunteers` | Admin with `volunteer_approvals`, or `superadmin` | List and filter volunteers (includes contact details) |
| GET | `/api/volunteers/summary` | Admin with `volunteer_approvals`, or `superadmin` | Volunteer counts by status |
| POST | `/api/volunteers/{id}/approve` | Admin with `volunteer_approvals`, or `superadmin` | Approve a pending volunteer — logs action with IP |
| POST | `/api/volunteers/{id}/reject` | Admin with `volunteer_approvals`, or `superadmin` | Reject a pending volunteer (reason optional) — logs action with IP |
| GET | `/api/admins` | `superadmin` only | List admin accounts |
| POST | `/api/admins` | `superadmin` only | Create an admin account — logs action with IP |
| PUT | `/api/admins/{id}` | `superadmin` only | Edit an admin (`name`, `role`, `permissions`, `is_active`) — logs action with IP |

---

## 3. Endpoints

### GET /api/health

Who: public. Smoke-test endpoint to verify the API is up.

Success (200):

```json
{
  "status": "ok",
  "service": "esagip-api",
  "time": "2026-12-01T08:30:00+08:00"
}
```

---

### GET /api/skills

Who: public (so the mobile registration form can load skills before sign-in). Not paginated.

Success (200):

```json
{
  "data": [
    { "id": 1, "name": "First Aid", "category": "Medical" },
    { "id": 2, "name": "Cooking", "category": "Relief Operations" }
  ],
  "message": "OK"
}
```

Errors: `429`.

---

### POST /api/register

Who: public (rate-limited). Creates a volunteer user (`role: "volunteer"`, `status: "pending"`, `is_active: true`), their `volunteer_profiles` record, `volunteer_skills` pivot rows, and a `consent_records` entry. Returns a Sanctum token so the app can display the pending screen.

Request:

```json
{
  "name": "Juan Dela Cruz",
  "email": "juan@example.com",
  "password": "Password123",
  "password_confirmation": "Password123",
  "contact_number": "09171234567",
  "address_area": "Zone 1, Sgt. de Leon St., Santolan",
  "birthdate": "2000-05-14",
  "is_resident": true,
  "availability": "available",
  "training": "Red Cross Basic First Aid (2025)",
  "skills": [1, 4, 7],
  "consent": true,
  "policy_version": "1.0"
}
```

Notes:
- `birthdate` must be at least 18 years old.
- `is_resident` must be `true`.
- `consent` must be `true`.
- `availability` is optional on register (defaults to `"available"`). Allowed values: `available`, `unavailable`, `on_operation`.
- `training` and `policy_version` are optional (`policy_version` defaults to `"1.0"`).

Success (201):

```json
{
  "data": {
    "token": "1|placeholder-sanctum-token",
    "user": {
      "id": 12,
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "role": "volunteer",
      "status": "pending",
      "is_active": true,
      "rejection_reason": null,
      "permissions": []
    }
  },
  "message": "Registration received. Waiting for admin approval."
}
```

Errors:

| Code | Example |
|---|---|
| 422 | `{ "message": "The given data was invalid.", "errors": { "email": ["The email has already been taken."], "birthdate": ["You must be 18 or older."], "is_resident": ["You must be a resident of Barangay Santolan."], "consent": ["You must accept the privacy notice."] } }` |
| 429 | `{ "message": "Too many attempts. Try again later." }` |

---

### POST /api/login

Who: public (rate-limited). Volunteers only. A `pending` or `rejected` volunteer can still log in so the mobile app can show the pending or rejected screen using `user.status` and `user.rejection_reason`. Writes an `activity_logs` entry (`action: "login"`, `module: "auth"`) including the request IP address.

Request:

```json
{
  "email": "juan@example.com",
  "password": "Password123"
}
```

Success (200):

```json
{
  "data": {
    "token": "2|placeholder-sanctum-token",
    "user": {
      "id": 12,
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "role": "volunteer",
      "status": "approved",
      "is_active": true,
      "rejection_reason": null,
      "permissions": []
    }
  },
  "message": "Logged in."
}
```

Errors:

| Code | Example |
|---|---|
| 401 | `{ "message": "Invalid email or password." }` |
| 403 | `{ "message": "Please use the admin portal to log in." }` (admin/superadmin on mobile login) or `{ "message": "This account is disabled." }` (`is_active: false`) |
| 422 | `{ "message": "The given data was invalid.", "errors": { "email": ["The email field is required."] } }` |
| 429 | `{ "message": "Too many attempts. Try again later." }` |

---

### POST /api/admin/login

Who: public (rate-limited). `admin` and `superadmin` only. Writes an `activity_logs` entry (`action: "admin_login"`, `module: "auth"`) including the request IP address.

Request:

```json
{
  "email": "admin@example.com",
  "password": "Password123"
}
```

Success (200):

```json
{
  "data": {
    "token": "3|placeholder-sanctum-token",
    "user": {
      "id": 2,
      "name": "Maria Santos",
      "email": "admin@example.com",
      "role": "admin",
      "status": "approved",
      "is_active": true,
      "rejection_reason": null,
      "permissions": ["volunteer_approvals", "operation_management", "attendance_confirmation"]
    }
  },
  "message": "Logged in."
}
```

Errors:

| Code | Example |
|---|---|
| 401 | `{ "message": "Invalid email or password." }` |
| 403 | `{ "message": "This account has no access to the admin portal." }` (a volunteer, or an admin with `is_active: false`) |
| 422 | `{ "message": "The given data was invalid.", "errors": { "password": ["The password field is required."] } }` |
| 429 | `{ "message": "Too many attempts. Try again later." }` |

---

### POST /api/logout

Who: any logged-in user. No body. Revokes only the Sanctum token used in the request.

Success (200):

```json
{
  "data": null,
  "message": "Logged out."
}
```

Errors: `401`, `429`.

---

### GET /api/me

Who: any logged-in user.

Success (200):

```json
{
  "data": {
    "id": 12,
    "name": "Juan Dela Cruz",
    "email": "juan@example.com",
    "role": "volunteer",
    "status": "approved",
    "is_active": true,
    "rejection_reason": null,
    "permissions": [],
    "profile": {
      "contact_number": "09171234567",
      "address_area": "Zone 1, Sgt. de Leon St., Santolan",
      "birthdate": "2000-05-14",
      "is_resident": true,
      "availability": "available",
      "training": "Red Cross Basic First Aid (2025)",
      "skills": [
        { "id": 1, "name": "First Aid", "category": "Medical" }
      ]
    }
  },
  "message": "OK"
}
```

For `admin` and `superadmin` accounts, `profile` is `null`.

Errors: `401`, `429`.

---

### PUT /api/me

Who: any logged-in user. Updates only the fields sent. `email`, `role`, `status`, and `is_active` cannot be changed here.

Request:

```json
{
  "name": "Juan D. Cruz",
  "contact_number": "09179876543",
  "address_area": "Zone 2, Tawiran St., Santolan",
  "availability": "unavailable",
  "training": "Red Cross Basic First Aid (2025), BDRRMC Orientation",
  "skills": [1, 2]
}
```

Success (200): same shape as `GET /api/me`, with `"message": "Profile updated."`

```json
{
  "data": {
    "id": 12,
    "name": "Juan D. Cruz",
    "email": "juan@example.com",
    "role": "volunteer",
    "status": "approved",
    "is_active": true,
    "rejection_reason": null,
    "permissions": [],
    "profile": {
      "contact_number": "09179876543",
      "address_area": "Zone 2, Tawiran St., Santolan",
      "birthdate": "2000-05-14",
      "is_resident": true,
      "availability": "unavailable",
      "training": "Red Cross Basic First Aid (2025), BDRRMC Orientation",
      "skills": [
        { "id": 1, "name": "First Aid", "category": "Medical" },
        { "id": 2, "name": "Cooking", "category": "Relief Operations" }
      ]
    }
  },
  "message": "Profile updated."
}
```

Errors:

| Code | Example |
|---|---|
| 422 | `{ "message": "The given data was invalid.", "errors": { "skills.0": ["The selected skill is invalid."], "availability": ["The selected availability is invalid."] } }` |
| 401, 429 | See standard errors |

---

### GET /api/volunteers

Who: `admin` with `volunteer_approvals`, or `superadmin`. Contact details (`email`, `contact_number`, `address_area`) are visible only to authorized admins; volunteers receive `403`.

Query parameters (all optional):

| Param | Example | Meaning |
|---|---|---|
| `status` | `pending` | `pending`, `approved`, or `rejected` |
| `skill` | `3` | Skill ID |
| `availability` | `available` | `available`, `unavailable`, or `on_operation` |
| `address_area` | `Zone 1` | Matches `address_area` (case-insensitive) |
| `search` | `juan` | Matches `name`, `email`, or `contact_number` |
| `page` | `1` | Page number (default `1`) |
| `per_page` | `15` | Page size (default `15`, max `50`) |

Example: `GET /api/volunteers?status=pending&skill=1&availability=available&page=1`

Success (200):

```json
{
  "data": [
    {
      "id": 12,
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "contact_number": "09171234567",
      "address_area": "Zone 1, Sgt. de Leon St., Santolan",
      "birthdate": "2000-05-14",
      "is_resident": true,
      "status": "pending",
      "is_active": true,
      "rejection_reason": null,
      "approved_by": null,
      "approved_at": null,
      "availability": "available",
      "training": "Red Cross Basic First Aid (2025)",
      "skills": [
        { "id": 1, "name": "First Aid", "category": "Medical" }
      ],
      "created_at": "2026-11-20T09:15:00+08:00"
    }
  ],
  "message": "OK",
  "meta": { "page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 422 | `{ "message": "The given data was invalid.", "errors": { "status": ["The selected status is invalid."] } }` |
| 401, 429 | See standard errors |

---

### GET /api/volunteers/summary

Who: `admin` with `volunteer_approvals`, or `superadmin`.

Success (200):

```json
{
  "data": {
    "total": 42,
    "pending": 5,
    "approved": 34,
    "rejected": 3
  },
  "message": "OK"
}
```

Errors: `401`, `403`, `429`.

---

### POST /api/volunteers/{id}/approve

Who: `admin` with `volunteer_approvals`, or `superadmin`. No body. Works only on a `pending` volunteer. Sets `status = "approved"`, `approved_by`, and `approved_at`, and writes an `activity_logs` entry (`action: "volunteer_approved"`, `module: "volunteers"`) including the IP address.

Success (200):

```json
{
  "data": {
    "id": 12,
    "name": "Juan Dela Cruz",
    "email": "juan@example.com",
    "contact_number": "09171234567",
    "address_area": "Zone 1, Sgt. de Leon St., Santolan",
    "birthdate": "2000-05-14",
    "is_resident": true,
    "status": "approved",
    "is_active": true,
    "rejection_reason": null,
    "approved_by": 2,
    "approved_at": "2026-11-21T14:00:00+08:00",
    "availability": "available",
    "training": "Red Cross Basic First Aid (2025)",
    "skills": [
      { "id": 1, "name": "First Aid", "category": "Medical" }
    ],
    "created_at": "2026-11-20T09:15:00+08:00"
  },
  "message": "Volunteer approved."
}
```

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 404 | `{ "message": "Not found." }` |
| 422 | `{ "message": "Only pending volunteers can be approved.", "errors": { "status": ["Volunteer is already approved."] } }` |
| 401, 429 | See standard errors |

---

### POST /api/volunteers/{id}/reject

Who: `admin` with `volunteer_approvals`, or `superadmin`. Works only on a `pending` volunteer. Sets `status = "rejected"` and optional `rejection_reason`, and writes an `activity_logs` entry (`action: "volunteer_rejected"`, `module: "volunteers"`) including the IP address.

Request (optional body):

```json
{
  "reason": "Address is outside Barangay Santolan."
}
```

Success (200):

```json
{
  "data": {
    "id": 12,
    "name": "Juan Dela Cruz",
    "email": "juan@example.com",
    "contact_number": "09171234567",
    "address_area": "Zone 1, Sgt. de Leon St., Santolan",
    "birthdate": "2000-05-14",
    "is_resident": true,
    "status": "rejected",
    "is_active": true,
    "rejection_reason": "Address is outside Barangay Santolan.",
    "approved_by": null,
    "approved_at": null,
    "availability": "available",
    "training": "Red Cross Basic First Aid (2025)",
    "skills": [
      { "id": 1, "name": "First Aid", "category": "Medical" }
    ],
    "created_at": "2026-11-20T09:15:00+08:00"
  },
  "message": "Volunteer rejected."
}
```

Errors: same as approve. A `reason` over 255 characters returns `422`.

---

### GET /api/admins

Who: `superadmin` only. Query params: `page`, `per_page` (same rules as volunteers).

Success (200):

```json
{
  "data": [
    {
      "id": 2,
      "name": "Maria Santos",
      "email": "admin@example.com",
      "role": "admin",
      "is_active": true,
      "permissions": ["volunteer_approvals", "operation_management", "attendance_confirmation"],
      "created_at": "2026-11-01T10:00:00+08:00"
    }
  ],
  "message": "OK",
  "meta": { "page": 1, "per_page": 15, "total": 3, "last_page": 1 }
}
```

Errors: `401`, `403`, `429`.

---

### POST /api/admins

Who: `superadmin` only. This is the only API endpoint that creates admin accounts (the first Superadmin is created via Artisan command). Writes an `activity_logs` entry (`action: "admin_created"`, `module: "admins"`) including the IP address.

Request:

```json
{
  "name": "Pedro Reyes",
  "email": "pedro@example.com",
  "password": "TempPassword123",
  "role": "admin",
  "permissions": ["volunteer_approvals", "operation_management"]
}
```

Success (201):

```json
{
  "data": {
    "id": 5,
    "name": "Pedro Reyes",
    "email": "pedro@example.com",
    "role": "admin",
    "is_active": true,
    "permissions": ["volunteer_approvals", "operation_management"],
    "created_at": "2026-11-28T10:00:00+08:00"
  },
  "message": "Admin created."
}
```

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 422 | `{ "message": "The given data was invalid.", "errors": { "email": ["The email has already been taken."], "permissions.0": ["The selected permission is invalid."] } }` |
| 401, 429 | See standard errors |

---

### PUT /api/admins/{id}

Who: `superadmin` only. Updates only the fields sent (`name`, `role`, `permissions`, `is_active`). Writes an `activity_logs` entry (`action: "admin_updated"`, `module: "admins"`) including the IP address.

Request:

```json
{
  "name": "Pedro R. Reyes",
  "permissions": ["volunteer_approvals", "operation_management", "attendance_confirmation"],
  "is_active": false
}
```

Success (200):

```json
{
  "data": {
    "id": 5,
    "name": "Pedro R. Reyes",
    "email": "pedro@example.com",
    "role": "admin",
    "is_active": false,
    "permissions": ["volunteer_approvals", "operation_management", "attendance_confirmation"],
    "created_at": "2026-11-28T10:00:00+08:00"
  },
  "message": "Admin updated."
}
```

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 404 | `{ "message": "Not found." }` |
| 422 | `{ "message": "The given data was invalid.", "errors": { "is_active": ["The is active field must be true or false."] } }` |
| 401, 429 | See standard errors |

---

## 4. Open questions to confirm with the team

1. Should rejected volunteers be allowed to re-register with the same email, or stay blocked unless an admin intervenes?
2. Should `GET /api/volunteers` also be readable by an admin with `reports_exports` (read-only), or strictly `volunteer_approvals` + `superadmin`?
3. Should admins be forced to change a temporary password on first login, or is the standard password rule (min 8 chars, letters + numbers) enough for Phase 2?
4. Should Sanctum tokens expire after a set time (e.g. 7 days), or stay non-expiring until logout for Phase 2?
