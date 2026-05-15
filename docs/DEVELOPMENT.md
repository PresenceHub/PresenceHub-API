# Local development setup

This guide covers both supported local API workflows:

- **Host PHP**: Laravel runs with `php artisan serve` on `http://localhost:8000` and connects to local PostgreSQL.
- **Docker Compose**: Nginx serves `https://presencehub.test` / `https://presencehub.local`, proxies to the Laravel `api` service, and uses the Compose PostgreSQL service.

## Prerequisites

- PHP `8.3` and Composer `2` for host development.
- PostgreSQL `16` for host development, or Docker Desktop / Docker Engine + Compose for the containerized stack.
- Node.js `22` / npm if you run `composer run dev` and want Vite assets locally.
- **mkcert** (recommended for Docker HTTPS) so browsers trust your local certificates without constant warnings.

Install mkcert (macOS with Homebrew):

```bash
brew install mkcert nss
mkcert -install
```

On Linux, follow [mkcert’s install instructions](https://github.com/FiloSottile/mkcert#installation) for your distribution.

## Host setup: PHP + local PostgreSQL

Use this path when you want Laravel on `http://localhost:8000` without Docker.

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Create and configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Set the database values for local PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=presencehub-dev
DB_USERNAME=presencehub-dev
DB_PASSWORD=secret
```

### 3. Prepare the database

Create the development database if it does not already exist, then run migrations and seed required lookup data:

```bash
php artisan migrate
php artisan db:seed --force --no-interaction
```

Registration depends on seeded roles/platforms; without seeding you may see errors such as "Customer role not found".

### 4. Start the API

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

For the full local dev loop (server, queue listener, logs, and Vite), run:

```bash
composer run dev
```

The companion web app should use `NEXT_PUBLIC_API_URL=http://localhost:8000` for this workflow.

## Docker Compose setup: Nginx + API + PostgreSQL

Use this path when you want the API behind local Nginx HTTPS.

### 1. Map local hostnames

The stack expects `presencehub.test` and `presencehub.local` (see [`docker/nginx.conf`](../docker/nginx.conf)). Add them to your hosts file:

- **macOS / Linux:** edit `/etc/hosts` and add:
  - `127.0.0.1 presencehub.test presencehub.local`
- **Windows:** edit `C:\Windows\System32\drivers\etc\hosts` the same way (may need an elevated editor).

### 2. Create TLS files (do not commit keys)

Nginx is configured to load fixed paths under `docker/ssl/`. **Generate a certificate and key on your machine** and use these exact output names so you do not have to edit Nginx:

```bash
cd /path/to/Presence-Hub-API
mkdir -p docker/ssl

mkcert -cert-file docker/ssl/presencehub.local+3.pem -key-file docker/ssl/presencehub.local+3-key.pem presencehub.test presencehub.local
```

- The `+3` in the filenames is only a label; what matters is that the paths match [`docker/nginx.conf`](../docker/nginx.conf).
- These files are **gitignored**; each developer creates their own pair.
- If you used mkcert, your system trusts the mkcert root CA, so `https://presencehub.test` should work without a browser warning.

**If the `nginx` container fails to start** with an error about missing certificate files, confirm both files exist under `docker/ssl/` and match the `ssl_certificate` and `ssl_certificate_key` lines in `docker/nginx.conf`.

### 3. Environment file

```bash
cp .env.example .env
```

Edit `.env` as needed, at minimum for HTTPS behind the proxy:

- `APP_URL=https://presencehub.test` (or `https://presencehub.local` if you prefer; keep it consistent with the URL you use in the browser)
- `DB_CONNECTION=pgsql`
- `DB_HOST=postgres`
- `DB_PORT=5432`
- `DB_DATABASE=presencehub-dev`
- `DB_USERNAME=presencehub-dev`
- `DB_PASSWORD=secret`

The `api` service reads `.env` from the bind mount. Compose does **not** inject `DB_*` into the API service, so set `DB_HOST=postgres` in `.env` before running Artisan inside the container. The Postgres service uses the same `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values, defaulting to `presencehub-dev` / `presencehub-dev` / `secret` when they are unset.

`POSTGRES_PORT` controls the host port exposed by the Postgres container and defaults to `5432`.

The [`docker/entrypoint.sh`](../docker/entrypoint.sh) ensures `composer install`, a present `.env`, and `APP_KEY` are handled when the `api` container starts.

### 4. Start the stack

From the project root:

```bash
docker compose up -d --build
```

- **Nginx** listens on **80** and **443** on the host.
- The **api** service does **not** publish port `8000` to the host; traffic is meant to go through Nginx only.

If port **80** or **443** is already in use (another web server, etc.), stop that service or change the port mapping for `nginx` in `docker-compose.yml` and use matching URLs/hosts.

### 5. Database migrations

Run migrations and seed required lookup data inside the `api` container:

```bash
docker compose exec api php artisan migrate
docker compose exec api php artisan db:seed --force --no-interaction
```

## Git hooks (optional)

This repo ships Git hook scripts under [`.githooks/`](../.githooks/) (version-controlled). They are **not** enabled until you point Git at that directory.

### One-time setup

From the **repository root**:

```bash
composer run setup-hooks
```

That runs `git config core.hooksPath .githooks`, so Git uses `.githooks/pre-commit` instead of `.git/hooks/`.

You can confirm:

```bash
git config --get core.hooksPath
# should print: .githooks
```

On macOS / Linux, ensure the hook script is executable (once per clone, if needed):

```bash
chmod +x .githooks/pre-commit
```

### What `pre-commit` does

The hook runs **inside the running `api` container**:

1. Formats staged PHP files with `composer format` (Pint — may modify files).
2. Re-stages only the PHP files that were already staged.
3. Runs `composer analyse` (PHPStan).

**Requirements:** bring the stack up first (`docker compose up -d` or equivalent) so `docker-compose exec -T api …` succeeds. The script uses `docker-compose` with a hyphen, consistent with other project docs.

**If Pint changes files**, Git will still be mid-commit with a dirty tree: stage the updates (`git add …`) and run `git commit` again (or amend) so the formatted code is included.

**To skip hooks for a single commit** (use sparingly):

```bash
git commit --no-verify
```

## Quick checks

```bash
docker compose ps
docker compose exec nginx nginx -t
```

- Open `http://presencehub.test` in a browser: you should be redirected to HTTPS.
- Open `https://presencehub.test` (or `https://presencehub.local`): the Laravel app should respond.
- `curl` example (ignores cert validation for a quick test):

  ```bash
  curl -skI https://presencehub.test/
  ```

## Optional: if private keys were ever committed

If keys were added to git before they were ignored, remove them from the index (keeps the files on disk if needed; adjust paths to match your repo):

```bash
git rm -r --cached docker/ssl/
```

Regenerate local certs (step 2) and commit only the updated `.gitignore` and this documentation.

## Troubleshooting

- **`nginx` container exits** — Often missing or misnamed `docker/ssl` files, or `nginx -t` errors. Check logs: `docker compose logs nginx`.
- **Database connection errors in Docker** — Confirm `postgres` is healthy: `docker compose ps`. Ensure `.env` uses `DB_HOST=postgres` before running Artisan inside the `api` container.
- **Database connection errors on the host** — Ensure PostgreSQL is running locally and `.env` uses `DB_HOST=127.0.0.1`.
- **“Wrong host” or 404 from Laravel** — Set `APP_URL` to the hostname you use (`https://presencehub.test`).

## Tests (host PHP)

With dependencies installed on the host (`composer install`) and `.env` pointing at the test database:

```bash
php artisan test --compact
```

Tests expect PostgreSQL; create a local `presencehub_testing` database or point your test environment at an equivalent database before running the suite.
