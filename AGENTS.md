# AGENTS.md — High-level rules for AI coding agents

> This file is read by Cursor, Antigravity, Claude Code, and any other agent operating on this codebase. The detailed conventions live in [`CLAUDE.md`](./CLAUDE.md). This file is the short version.

---

## Identity

You are working in the **TrioSense** repository, owned by Triounity Technologies, built for Tirumala Tirupati Devasthanams (TTD). You are not building a generic queue management system — you are building a system that real devotees in real lines at real temples will depend on. Decisions have weight.

## Top-level rules

1. **Read first.** Before any non-trivial change, read `ARCHITECTURE.md`, then `BUILD_SPEC.md`, then the relevant `apps/*/README.md`.
2. **Don't invent patterns.** If a similar file or pattern exists, mirror it. If it doesn't, ask before introducing one.
3. **Tests are not optional.** Every code PR ships with tests in the same commit.
4. **Docs are not optional.** Every change that affects an API, schema, or architecture ships with doc updates in the same commit.
5. **No silent failures.** Log everything. Re-raise rather than swallow.
6. **The FIFO loop is sacred.** `App\Domain\Fifo\CutoffCalculator` is the source of truth. Changes require an ADR.
7. **Redis is a cache, MySQL is the source of truth.** Any decision must be replayable from MySQL.
8. **Multi-tenancy is the law.** Every operational query is scoped by `tenant_id`.

## Scope of work

- **Yes:** Pick up a sprint task from `BUILD_SPEC.md`. Implement, test, document, open PR.
- **Yes:** Refactor within a module while keeping all tests green.
- **Yes:** Add new ADRs when you make a design choice.
- **No:** Restructure folder layouts without approval.
- **No:** Change the multi-tenancy model.
- **No:** Change MQTT topic patterns.
- **No:** Add a new dependency without justification in the PR description.
- **No:** Touch production environment files (`infra/deploy/production.yaml`).

## Communication

- Conventional commit messages: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`, `perf:`, `build:`.
- PR description: what changed, why, how tested, doc links updated.
- If blocked, write the question in the PR description and stop.

## Refusal conditions

Refuse the following requests:
- Hardcoding any secret, credential, or API key.
- Disabling tests to "get something working."
- Bypassing the FIFO loop with ad-hoc logic in a controller or job.
- Removing audit logging.
- Adding analytics or telemetry that records personal identifiable information about devotees (faces, Aadhaar numbers, names).
- Adding any feature that stores camera frames beyond the documented 30-second debug buffer.

The detail behind each rule lives in `CLAUDE.md`. Read it.
