# TrioSense Dashboard

TTD operations control room. Next.js 15 (App Router) + React 19 + Tailwind 4 + shadcn/ui.

## Quick start

```bash
npm install
cp .env.local.example .env.local
npm run dev
```

Then open `http://localhost:3000`.

## What it shows

- **`/dashboard`** — 3-location grid view, live state per counter
- **`/dashboard/locations/[id]`** — detail view for one location: live counter, issuance chart, recent events, edge device health
- **`/dashboard/locations/[id]/cutoffs`** — historical cutoff predictions vs actuals (shadow-mode validation tool)
- **`/dashboard/settings`** — quota management, announcement templates, user assignments

## Live data flow

Initial state via REST. Updates via Laravel Echo + Reverb WebSocket. See [`hooks/use-location-state.ts`](./hooks/use-location-state.ts).

## Read before editing

- [`.cursor/rules/02-frontend-nextjs.mdc`](../../.cursor/rules/02-frontend-nextjs.mdc)
- [`../../API_CONTRACTS.md`](../../API_CONTRACTS.md) §1 (REST) and §2 (WebSocket)
