# pinch-backend

Symfony 7.4 (PHP 8.4) API backend for [Pinch](https://github.com/maciej-jedral/pinch), served via [FrankenPHP](https://frankenphp.dev/).

This repo isn't meant to be run standalone — see the [`pinch`](https://github.com/maciej-jedral/pinch) meta-repo for the one-command local setup (`install.sh`), which wires this up together with the frontend and a Postgres database via Docker Compose.

## Tooling

- `composer cs-check` / `composer cs-fix` — php-cs-fixer (`@Symfony` + `@PER-CS2.0`)
- `composer stan` — phpstan (level 8)
- `composer test` — PHPUnit

## Docker image

`Dockerfile` is multi-stage: `base` (FrankenPHP + extensions) → `dev` (Composer, dev deps; what the meta-repo's compose builds and bind-mounts source over) and `prod` (`--no-dev`, `APP_ENV=prod` baked, cache warmed, no Composer). `compose.prod.yml` is the runtime definition the deploy workflow ships to the VM.

In production the container terminates TLS itself: `SERVER_NAME=api.pinchapp.fyi` makes the built-in Caddy serve the hostname on 80/443 with an automatic Let's Encrypt certificate (kept in the `caddy_data` volume). Locally the default `SERVER_NAME=:8000` applies. There is deliberately no container `HEALTHCHECK`: it could only probe `/api/hello`, and a `SELECT 1` every 30 s would keep the Neon free-tier compute awake around the clock (100 CU-hours/month); the deploy smoke test is the gate.

## CI / deploy

`.github/workflows/deploy.yml`:

- **Pull request** → `check`: `composer cs-check`, `composer stan`, `composer test` (against a Postgres 18 service; `composer test` creates the `pinch_test` database itself).
- **Push to `main`** → `check` → `build` (arm64 `prod` image → `ghcr.io/maciej-jedral/pinch-backend:sha-<commit>` + `latest`) → `deploy` (SSH to the EC2 box: copy `compose.prod.yml` + a generated `.env`, `pull`, run migrations, `up -d`, smoke-test `https://api.pinchapp.fyi/api/hello`).
- **Rollback**: Actions → pick the run of the commit you want → re-run the **`deploy` job only** (*Re-run all jobs* would rebuild the image from an unpinned base and overwrite that `sha-…` tag). The VM runs the pinned `sha-…` tag, never `latest`.

Only `main` may deploy to the `production` environment (deployment-branch policy). Rationale for all of this: the decision log in the meta-repo's `AGENTS.md`.

## Operations

- **Where things are**: on the VM, `/opt/pinch/compose.yml` + `/opt/pinch/.env` — both rewritten on every deploy, so edits there don't survive. Image: `ghcr.io/maciej-jedral/pinch-backend:sha-<full commit sha>`.
- **What's live**: `curl https://api.pinchapp.fyi/api/hello`, or `ssh -i ~/.ssh/pinch-aws ubuntu@63.182.98.240 'grep BACKEND_IMAGE_TAG /opt/pinch/.env; docker compose -f /opt/pinch/compose.yml ps'`.
- **Logs**: `ssh … 'docker compose -f /opt/pinch/compose.yml logs --tail 100 -f'`.
- **Redeploy / rollback**: `gh run list --repo maciej-jedral/pinch-backend`, then `gh run rerun <id> --job <deploy-job-id> --repo maciej-jedral/pinch-backend` (`gh run view <id>` lists the job ids; or Actions UI → the `deploy` job → *Re-run this job*). That redeploys the image already in GHCR for that commit. *Re-run all jobs* also works but rebuilds from the unpinned `dunglas/frankenphp:php8.4` base and overwrites the `sha-…` tag, so it may not restore the exact bits that ran before. Migrations are *not* rolled back: write expand/contract migrations.
- **Secrets / variables** (GitHub `production` environment, `gh secret set <NAME> --env production`): secrets `APP_SECRET`, `DATABASE_URL` (Neon *direct* URI, `postgres://…/neondb?sslmode=require`), `DEPLOY_SSH_KEY` (private half of `~/.ssh/pinch-deploy`); variables `BACKEND_HOST` (the box's IP, SSH target), `KNOWN_HOSTS` (`ssh-keyscan -t ed25519 <ip>`). The public hostname, `CORS_ALLOW_ORIGIN`, `DEFAULT_URI` and `SERVER_NAME` are hardcoded in the workflow. GitHub never shows a secret again — keep `APP_SECRET` in a password manager.
- **After a Terraform instance replacement** (new host key, empty disk): update `KNOWN_HOSTS`, re-run the latest workflow. Caddy re-issues the certificate on the first deploy.
- **Rotate the deploy key**: `ssh-keygen -t ed25519 -f ~/.ssh/pinch-deploy` → public half into `terraform.tfvars` (`deploy_ssh_public_key`) → `./tf plan`/`apply` (replaces the instance) → `gh secret set DEPLOY_SSH_KEY --env production < ~/.ssh/pinch-deploy` → update `KNOWN_HOSTS` → re-run the workflow.
- **Certificate**: `echo | openssl s_client -connect api.pinchapp.fyi:443 -servername api.pinchapp.fyi 2>/dev/null | openssl x509 -noout -dates`. Renewal is automatic (Caddy, ~30 days before expiry); certs live in the `caddy_data` volume on the VM.
- **GHCR**: the deploy job logs in with the job token before `pull` and logs out after, so a private package works. Flip it public (GitHub → Packages → pinch-backend → settings) only if anonymous pulls are wanted.
- **Migrations** run on every deploy (`--allow-no-migration` while `migrations/` is empty). The image carries a compiled `.env.local.php` (`composer dump-env prod`); real env vars still win.
- **Test DB**: `composer test` = `doctrine:database:create --env=test --if-not-exists` + PHPUnit. The root compose passes no `APP_ENV` to the container (Symfony reads `/app/.env*` itself), which is what lets PHPUnit force `test`.

## Status

Live at `https://api.pinchapp.fyi`. Phase 1 scaffold: a single `GET /api/hello` endpoint proving the stack is wired end-to-end (Symfony → Postgres, and reachable from the Next.js frontend). No auth or real API design yet — that's Phase 2; see `AGENTS.md` in the meta-repo.
