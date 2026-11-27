# E-Sagip API (Phase 2)

Base URL (local): `http://127.0.0.1:8000/api`
Base URL (live): `https://[service].onrender.com/api`

This file is the contract between the backend (Palagam) and the two frontends (Soleybar's mobile app, Libres' admin portal). Use these shapes in your mock data. If a shape needs to change, tell Palagam first and update this file in the same pull request.

---

## 1. Conventions

### Headers

| Header | Value | When |
|---|---|---|
| `Accept` | `application/json` | Every request |
| `Content-Type` | `application/json` | Requests with a body |
| `Authorization` | `Bearer <token>` | Every endpoint except register and the two login routes |

### Response shapes

Success:

```json
{ "data": {}, "message": "OK" }
```

Error:

```json
{ "message": "Human-readable message", "errors": { "field": ["Reason"] } }
```

`errors` appears only on 422. Other errors have only `message`. Dates are ISO 8601 strings (example: `2026-12-01T08:30:00+08:00`). Field names are `snake_case`.

### Roles, statuses and permissions

| Item | Values |
|---|---|
| `role` | `volunteer`, `admin`, `superadmin` |
| `status` (volunteer) | `pending`, `approved`, `rejected` |
| `status` (admin) | `active`, `disabled` |
| `permissions` (admin) | `volunteers.view`, `volunteers.approve`, `admins.manage` |

A `superadmin` has every permission. A volunteer's `permissions` is always `[]`.

### User object

Used by login, register and `/me`.

```json
{
  "id": 12,
  "name": "Juan Dela Cruz",
  "email": "juan@example.com",
  "role": "volunteer",
  "status": "pending",
  "permissions": []
}
```

### Standard errors

| Code | Meaning | Body |
|---|---|---|
| 401 | Missing, wrong or expired token | `{ "message": "Unauthenticated." }` |
| 403 | Logged in but not allowed | `{ "message": "You do not have access to this action." }` |
| 404 | Route or record not found | `{ "message": "Not found." }` |
| 422 | Validation failed | `{ "message": "The given data was invalid.", "errors": { "email": ["The email field is required."] } }` |
| 429 | Too many requests | `{ "message": "Too many attempts. Try again later." }` |
| 500 | Server error | `{ "message": "Server error." }` |

Every endpoint below can return 429. Every endpoint except register and the two login routes can return 401. They are not repeated in each section unless they have a special message.

---

## 2. Endpoint summary

| Method | Path | Who can call it | Purpose |
|---|---|---|---|
| POST | `/api/register` | Public | Volunteer sign-up (starts as `pending`) |
| POST | `/api/login` | Public | Volunteer login (mobile) |
| POST | `/api/admin/login` | Public | Admin and superadmin login (web) |
| POST | `/api/logout` | Any logged-in user | Revoke the current token |
| GET | `/api/me` | Any logged-in user | Get own profile |
| PUT | `/api/me` | Any logged-in user | Update own profile |
| GET | `/api/skills` | Any logged-in user | List the skills |
| GET | `/api/volunteers` | Admin with `volunteers.view` | List and filter volunteers |
| GET | `/api/volunteers/summary` | Admin with `volunteers.view` | Counts by status |
| POST | `/api/volunteers/{id}/approve` | Admin with `volunteers.approve` | Approve a pending volunteer |
| POST | `/api/volunteers/{id}/reject` | Admin with `volunteers.approve` | Reject a pending volunteer |
| GET | `/api/admins` | Superadmin or `admins.manage` | List admin accounts |
| POST | `/api/admins` | Superadmin or `admins.manage` | Create an admin account |
| PUT | `/api/admins/{id}` | Superadmin or `admins.manage` | Edit an admin (role, permissions, status) |

---

## 3. Endpoints

### POST /api/register

Who: public. Creates a volunteer with `status: "pending"` and returns a token so the app can show the pending screen.

Request:

```json
{
  "name": "Juan Dela Cruz",
  "email": "juan@example.com",
  "password": "secret1234",
  "password_confirmation": "secret1234",
  "phone": "09171234567",
  "address": "123 Sgt. de Leon St., Santolan, Pasig",
  "birthdate": "2000-05-14",
  "skills": [1, 4, 7],
  "availability": ["weekends", "evenings"],
  "consent": true
}
```

Success (201):

```json
{
  "data": {
    "token": "1|abc123...",
    "user": { "id": 12, "name": "Juan Dela Cruz", "email": "juan@example.com", "role": "volunteer", "status": "pending", "permissions": [] }
  },
  "message": "Registration received. Waiting for admin approval."
}
```

Errors:

| Code | Example |
|---|---|
| 422 | `{ "message": "The given data was invalid.", "errors": { "email": ["The email has already been taken."], "birthdate": ["You must be 18 or older."], "consent": ["You must accept the privacy notice."] } }` |
| 429 | `{ "message": "Too many attempts. Try again later." }` |

---

### POST /api/login

Who: public. Volunteers only. A `pending` or `rejected` volunteer can still log in, and the app uses `user.status` to show the pending or rejected screen.

Request:

```json
{ "email": "juan@example.com", "password": "secret1234" }
```

Success (200):

```json
{
  "data": {
    "token": "2|def456...",
    "user": { "id": 12, "name": "Juan Dela Cruz", "email": "juan@example.com", "role": "volunteer", "status": "approved", "permissions": [] }
  },
  "message": "Logged in."
}
```

Errors:

| Code | Example |
|---|---|
| 401 | `{ "message": "Invalid email or password." }` |
| 403 | `{ "message": "Please use the admin portal to log in." }` (an admin account used on the mobile login) |
| 422 | `{ "message": "The given data was invalid.", "errors": { "email": ["The email field is required."] } }` |
| 429 | `{ "message": "Too many attempts. Try again later." }` |

---

### POST /api/admin/login

Who: public. Admin and superadmin only.

Request:

```json
{ "email": "admin@example.com", "password": "secret1234" }
```

Success (200):

```json
{
  "data": {
    "token": "3|ghi789...",
    "user": { "id": 2, "name": "Maria Santos", "email": "admin@example.com", "role": "admin", "status": "active", "permissions": ["volunteers.view", "volunteers.approve"] }
  },
  "message": "Logged in."
}
```

Errors:

| Code | Example |
|---|---|
| 401 | `{ "message": "Invalid email or password." }` |
| 403 | `{ "message": "This account has no access to the admin portal." }` (a volunteer, or a `disabled` admin) |
| 422 | `{ "message": "The given data was invalid.", "errors": { "password": ["The password field is required."] } }` |
| 429 | `{ "message": "Too many attempts. Try again later." }` |

---

### POST /api/logout

Who: any logged-in user. No body. Revokes only the token used in the request.

Success (200):

```json
{ "data": null, "message": "Logged out." }
```

Errors: 401, 429.

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
    "permissions": [],
    "profile": {
      "phone": "09171234567",
      "address": "123 Sgt. de Leon St., Santolan, Pasig",
      "birthdate": "2000-05-14",
      "skills": [ { "id": 1, "name": "First Aid", "category": "Medical" } ],
      "availability": ["weekends", "evenings"]
    }
  },
  "message": "OK"
}
```

For admins, `profile` is `null`.

Errors: 401, 429.

---

### PUT /api/me

Who: any logged-in user. Updates only the fields sent. Email, role and status cannot be changed here.

Request:

```json
{
  "name": "Juan D. Cruz",
  "phone": "09179876543",
  "address": "45 Tawiran St., Santolan, Pasig",
  "skills": [1, 2],
  "availability": ["weekdays"]
}
```

Success (200): same shape as `GET /api/me`, with `"message": "Profile updated."`

Errors:

| Code | Example |
|---|---|
| 422 | `{ "message": "The given data was invalid.", "errors": { "skills.0": ["The selected skill is invalid."] } }` |
| 401, 429 | See standard errors |

---

### GET /api/skills

Who: any logged-in user. Not paginated (15 rows).

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

Errors: 401, 429.

---

### GET /api/volunteers

Who: admin with `volunteers.view` (superadmin always).

Query parameters (all optional):

| Param | Example | Meaning |
|---|---|---|
| `status` | `pending` | `pending`, `approved` or `rejected` |
| `skill` | `3` | Skill id |
| `availability` | `weekends` | One availability value |
| `search` | `juan` | Matches name, email or phone |
| `page` | `1` | Page number (default 1) |
| `per_page` | `15` | Page size (default 15, max 50) |

Example: `GET /api/volunteers?status=pending&skill=3&page=2`

Success (200):

```json
{
  "data": [
    {
      "id": 12,
      "name": "Juan Dela Cruz",
      "email": "juan@example.com",
      "phone": "09171234567",
      "address": "123 Sgt. de Leon St., Santolan, Pasig",
      "status": "pending",
      "skills": [ { "id": 1, "name": "First Aid", "category": "Medical" } ],
      "availability": ["weekends", "evenings"],
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

Who: admin with `volunteers.view`.

Success (200):

```json
{
  "data": { "total": 42, "pending": 5, "approved": 34, "rejected": 3 },
  "message": "OK"
}
```

Errors: 401, 403, 429.

---

### POST /api/volunteers/{id}/approve

Who: admin with `volunteers.approve`. No body. Works only on a `pending` volunteer. Writes an activity log entry.

Success (200): the updated volunteer object (same shape as one item in `GET /api/volunteers`), with `"message": "Volunteer approved."`

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 404 | `{ "message": "Not found." }` |
| 422 | `{ "message": "Only pending volunteers can be approved.", "errors": { "status": ["Volunteer is already approved."] } }` |
| 401, 429 | See standard errors |

---

### POST /api/volunteers/{id}/reject

Who: admin with `volunteers.approve`. Works only on a `pending` volunteer. Writes an activity log entry.

Request (optional body):

```json
{ "reason": "Address is outside Barangay Santolan." }
```

Success (200): the updated volunteer object with `"status": "rejected"` and `"message": "Volunteer rejected."`

Errors: same as approve. A `reason` over 255 characters returns 422.

---

### GET /api/admins

Who: superadmin, or admin with `admins.manage`. Query params: `page`, `per_page` (same rules as volunteers).

Success (200):

```json
{
  "data": [
    {
      "id": 2,
      "name": "Maria Santos",
      "email": "admin@example.com",
      "role": "admin",
      "status": "active",
      "permissions": ["volunteers.view", "volunteers.approve"],
      "created_at": "2026-11-01T10:00:00+08:00"
    }
  ],
  "message": "OK",
  "meta": { "page": 1, "per_page": 15, "total": 3, "last_page": 1 }
}
```

Errors: 401, 403, 429.

---

### POST /api/admins

Who: superadmin, or admin with `admins.manage`. This is the only way to create admin accounts.

Request:

```json
{
  "name": "Pedro Reyes",
  "email": "pedro@example.com",
  "password": "temporary1234",
  "role": "admin",
  "permissions": ["volunteers.view"]
}
```

Success (201): the new admin object (same shape as one item in `GET /api/admins`), with `"message": "Admin created."`

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 422 | `{ "message": "The given data was invalid.", "errors": { "email": ["The email has already been taken."], "permissions.0": ["The selected permission is invalid."] } }` |
| 401, 429 | See standard errors |

---

### PUT /api/admins/{id}

Who: superadmin, or admin with `admins.manage`. Updates only the fields sent. A superadmin account cannot be edited by an admin.

Request:

```json
{
  "name": "Pedro R. Reyes",
  "permissions": ["volunteers.view", "volunteers.approve"],
  "status": "disabled"
}
```

Success (200): the updated admin object, with `"message": "Admin updated."`

Errors:

| Code | Example |
|---|---|
| 403 | `{ "message": "You do not have access to this action." }` |
| 404 | `{ "message": "Not found." }` |
| 422 | `{ "message": "The given data was invalid.", "errors": { "status": ["The selected status is invalid."] } }` |
| 401, 429 | See standard errors |

---

## 4. Assumptions (questions to confirm)

1. Are the roles `volunteer`, `admin` and `superadmin` correct? The UI notes list "Superadmin & Admin" and RBAC.
2. Are the permission names (`volunteers.view`, `volunteers.approve`, `admins.manage`) enough for Phase 2, or should we add more now (operations, attendance, community, logs)?
3. Is the availability list (`weekdays`, `weekends`, `mornings`, `afternoons`, `evenings`) the one Libres used in the Figma register screen?
4. Should registration collect the address as one text field, or as a fixed list of Santolan areas for filtering?
5. Should a `rejected` volunteer see the rejection reason in the app? If yes, we add `rejection_reason` to the user object.
6. Should rejected volunteers be allowed to register again with the same email?
7. Should `pending` volunteers be allowed to log in (current assumption: yes, so the app can show the pending screen), or be blocked with a 403?
8. Should an admin be able to approve or reject volunteers without the `volunteers.approve` permission if they have `volunteers.view`? (Current assumption: no.)
9. What are the login rate limits (suggested: 5 attempts per minute per email and IP) and the token expiry (suggested: none for now)?
10. Is the password rule (minimum 8 characters) fine, and should admins be forced to change a temporary password on first login?
11. Should `GET /api/volunteers` also return volunteers' participation hours later (Phase 4+), or stay as is?
12. Should `PUT /api/me` allow changing the email, or is it locked after registration?
