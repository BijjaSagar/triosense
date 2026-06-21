# TrioSense Dashboard

TTD operations control room. Next.js 15 (App Router) + React 19 + Tailwind 4 + shadcn/ui.

## Quick start

```bash
npm install
cp .env.example .env.local
npm run dev
```

Then open `http://localhost:3001` (or the port shown by `npm run dev`).

## Mac webcam demo preview

When running `make edge-webcam` from the repo root, open:

- **Dashboard preview:** `/dashboard/locations/1/preview` — MJPEG feed + IN/OUT counters beside live queue state
- **Edge-only preview:** `http://127.0.0.1:8766` — standalone annotated stream

Set `NEXT_PUBLIC_EDGE_PREVIEW_URL` in `.env.local` if the edge preview binds elsewhere.

## Authentication

The dashboard uses **Sanctum SPA cookie auth** (HttpOnly session, no bearer token in browser storage):

1. `GET /sanctum/csrf-cookie` — obtain CSRF token
2. `POST /api/v1/auth/login` with header `X-TrioSense-Auth: cookie` — session established
3. Subsequent REST and Echo requests use `credentials: 'include'`

Mobile and automation clients omit the header and receive a Bearer token in the login response.

See [`lib/api.ts`](./lib/api.ts), [`lib/api-client.ts`](./lib/api-client.ts), and [`lib/echo.ts`](./lib/echo.ts).

## What it shows

- **`/dashboard`** — 3-location grid view, live state per counter
- **`/dashboard/locations/[id]`** — detail view for one location: live counter, recent events, edge device health
- **`/dashboard/locations/[id]/preview`** — Mac webcam demo: MJPEG feed + person/IN/OUT counters from edge preview server
- **`/dashboard/locations/[id]/settings`** — camera source and tripwire configuration

## Live data flow

Initial state via REST. Updates via Laravel Echo + Reverb WebSocket. See [`hooks/use-location-state.ts`](./hooks/use-location-state.ts).

## Read before editing

- [`.cursor/rules/02-frontend-nextjs.mdc`](../../.cursor/rules/02-frontend-nextjs.mdc)
- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) §1 (REST) and §2 (WebSocket)
