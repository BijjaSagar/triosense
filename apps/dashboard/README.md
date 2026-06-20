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

The dashboard uses **Bearer token auth** (Sanctum personal access tokens), not cookie-based SPA auth:

1. `POST /api/v1/auth/login` returns `{ token, user }`.
2. The token is stored in `sessionStorage` and sent as `Authorization: Bearer <token>` on REST calls and Echo WebSocket auth.

**Why not Sanctum cookies yet?** Cookie auth requires same-site CSRF + `SANCTUM_STATEFUL_DOMAINS` wiring across Next.js server actions. Bearer tokens are sufficient for the Sprint 1–10 operator dashboard; migrate to cookie sessions in a dedicated auth sprint when server actions own all mutations.

See [`lib/api.ts`](./lib/api.ts) and [`lib/echo.ts`](./lib/echo.ts).

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
