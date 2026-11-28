# E-Sagip context (esagip-api)

E-Sagip is a post-disaster volunteer management system for Barangay Santolan, Pasig City. It has an Android app for volunteers (Flutter), a web portal for BDRRMC admins (Next.js), and this Laravel API with a PostgreSQL database. This repo is the API. The AI chatbot (E-Sagip Guide) comes in a later phase.

## Where things live

| Part | Where |
|---|---|
| API repo | https://github.com/E-Sagip-Santolan/E-Sagip-Santolan-api |
| Live API | https://e-sagip-santolan-api-1.onrender.com (Render, Docker, free tier) |
| Health check | https://e-sagip-santolan-api-1.onrender.com/api/health |
| Admin portal | https://e-sagip-santolan-admin.vercel.app |
| Database | PostgreSQL locally, Neon for staging and the live API |
| API contract | `docs/api.md` (source of truth for endpoints and JSON shapes) |

The free Render server sleeps when idle. The first request can take about a minute.

## Current status

- Done: repository, Laravel project, Dockerfile, deployment to Render, `GET /api/health`, JSON error format, CORS, and `docs/api.md`.
- Next: the Phase 2 endpoints in `docs/api.md` (register, login, admin login, profile, skills, volunteers, approve and reject, admins, roles).

## Environment settings (names only, never values)

The API reads these from its environment, set locally in `.env` and on Render:

- `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`
- `DB_CONNECTION=pgsql` and `DB_URL`
- `LOG_CHANNEL=stderr`
- `CORS_ALLOWED_ORIGINS`: comma-separated list of allowed browser origins (the local admin portal on port 3000 and the Vercel address), with no trailing slash and no spaces

The mobile app is not affected by CORS, because CORS only applies to browsers.

## Rules of the system

- Volunteers are residents aged 18 and above. They register, then a BDRRMC admin approves them. Only approved volunteers can use operation features.
- Admin accounts are created only by authorized barangay personnel, never by self-registration.
- Volunteers see only their own records and open operations. Volunteer contact details are visible only to admins.
- The mobile app has no admin functions.
- No GPS, no document uploads, and no automatic SMS or push notifications.
- Data Privacy Act (RA 10173): collect only what is needed, store passwords hashed, and record important admin actions in the activity log.
- Use dummy data only during development.

## Roles and access

- Account roles: `volunteer`, `admin`, `superadmin`.
- Admin permissions are checked on the server with middleware or policies, never only in the frontend.
- The RBAC screen has four fixed presets: Super Administrator, Operations Manager, Content Officer and Auditor. The permission lists are in `docs/api.md`.

## Out of scope for Phase 2

Operations, attendance, community information, reports, the dashboard, settings, and the chatbot.

## Team

- Project manager: John Felix Sungahid
- Mobile app (Flutter): John Marvin Soleybar
- Backend (Laravel): Spike Vincent Palagam and Euan Alfonso Suba
- Admin portal and UI/UX: Ryan Dexter Libres
- QA: Jhon Carl Gabarda
