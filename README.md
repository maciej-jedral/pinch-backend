# pinch-backend

Symfony 7.4 (PHP 8.4) API backend for [Pinch](https://github.com/maciej-jedral/pinch), served via [FrankenPHP](https://frankenphp.dev/).

This repo isn't meant to be run standalone — see the [`pinch`](https://github.com/maciej-jedral/pinch) meta-repo for the one-command local setup (`install.sh`), which wires this up together with the frontend and a Postgres database via Docker Compose.

## Tooling

- `composer cs-check` / `composer cs-fix` — php-cs-fixer (`@Symfony` + `@PER-CS2.0`)
- `composer stan` — phpstan (level 8)
- `composer test` — PHPUnit

## Status

Phase 1 scaffold: a single `GET /api/hello` endpoint proving the stack is wired end-to-end (Symfony → Postgres, and reachable from the Next.js frontend). No domain, auth, or real API design yet — that's Phase 2. See `../ai_artifacts/ALIGNMENT.md` in the meta-repo for the full plan.
