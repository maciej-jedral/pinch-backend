# pinch-backend

Symfony 7.4 (PHP 8.4) API backend for [Pinch](https://github.com/maciej-jedral/pinch), served via [FrankenPHP](https://frankenphp.dev/).

This repo isn't meant to be run standalone — see the [`pinch`](https://github.com/maciej-jedral/pinch) meta-repo for the one-command local setup (`install.sh`), which wires this up together with the frontend and a Postgres database via Docker Compose.

## Tooling

- `composer cs-check` / `composer cs-fix` — php-cs-fixer (`@Symfony` + `@PER-CS2.0`)
- `composer stan` — phpstan (level 8)
- `composer test` — PHPUnit

## Docker image

`Dockerfile` is multi-stage: `base` (FrankenPHP + extensions) → `dev` (Composer, dev deps; what the meta-repo's compose builds and bind-mounts source over) and `prod` (`--no-dev`, `APP_ENV=prod` baked, cache warmed, no Composer). `compose.prod.yml` is the runtime definition the deploy workflow ships to the VM.

In production the container terminates TLS itself: `SERVER_NAME="api.pinchapp.fyi, :8000"` makes the built-in Caddy serve the hostname on 80/443 with an automatic Let's Encrypt certificate (kept in the `caddy_data` volume), while `:8000` stays an unpublished plain-HTTP listener for the image's `HEALTHCHECK`. Locally the default `SERVER_NAME=:8000` applies.

## CI / deploy

`.github/workflows/deploy.yml`:

- **Pull request** → `check`: `composer cs-check`, `composer stan`, `composer test` (against a Postgres 18 service; `composer test` creates the `pinch_test` database itself).
- **Push to `main`** → `check` → `build` (arm64 `prod` image → `ghcr.io/maciej-jedral/pinch-backend:sha-<commit>` + `latest`) → `deploy` (SSH to the EC2 box: copy `compose.prod.yml` + a generated `.env`, `pull`, run migrations, `up -d`, smoke-test `https://api.pinchapp.fyi/api/hello`).
- **Rollback**: Actions → pick the run of the commit you want → *Re-run all jobs*. The VM runs the pinned `sha-…` tag, never `latest`.

Secrets/variables live in the repo's `production` environment. Rationale and ops notes: `ai_artifacts/ALIGNMENT.md` (*Backend deployment*) and `WORKING_NOTES.md` in the meta-repo.

## Status

Live at `https://api.pinchapp.fyi`. Phase 1 scaffold: a single `GET /api/hello` endpoint proving the stack is wired end-to-end (Symfony → Postgres, and reachable from the Next.js frontend). No auth or real API design yet — that's Phase 2. See `../ai_artifacts/ALIGNMENT.md` in the meta-repo for the full plan.
