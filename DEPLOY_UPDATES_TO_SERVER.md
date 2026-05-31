# Wallabag: Update Code on Server

This guide is for normal day-to-day code updates after one-time setup is complete.

Server app path:

- `<root-wallabag-dir>`

---

## Quick update flow (most common)

### 1. Local machine: push new code to `production`

```bash
git checkout production
git pull --ff-only origin production
git merge <your-feature-branch>
git push origin production
```

### 2. Server: pull and deploy

```bash
<root-wallabag-dir>/scripts/deploy.sh
```

This script should:

- pull latest `origin production`
- run composer install for prod
- run DB migrations
- clear/warm cache

---

## Safe update flow (recommended)

### 1. Create backup on server first

```bash
cd <root-wallabag-dir>/scripts
set -a; . ./backup.env; set +a; bash ./backup.sh
```

### 2. Deploy latest production

```bash
<root-wallabag-dir>/scripts/deploy.sh
```

### 3. Verify app health

```bash
cd <root-wallabag-dir>
git status
php -v
composer --version
```

Then open the site and confirm login, entry list, and tagging/search still work.

---

## Update from upstream wallabag

Use this when you want to import new commits from the official wallabag project.

### 1. Local machine

```bash
git fetch upstream
git checkout production
git merge upstream/master
# resolve conflicts + run your tests
git push origin production
```

### 2. Server

```bash
<root-wallabag-dir>/scripts/deploy.sh
```

---

## If deploy fails

### Check environment and dependencies

```bash
cd <root-wallabag-dir>
echo "APP_ENV=${APP_ENV:-unset} APP_DEBUG=${APP_DEBUG:-unset} SENTRY_DSN=${SENTRY_DSN:-unset}"
php -v
composer --version
```

### Reinstall dependencies cleanly

```bash
cd <root-wallabag-dir>
rm -rf vendor
export APP_ENV=prod APP_DEBUG=0 SENTRY_DSN=''
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
```

---

## Rollback options

If a deployment breaks production:

1. Restore latest filesystem/database backup from `scripts/backup.sh`.
2. Re-deploy a previous known-good commit from `production`.
3. Use git history to identify and revert the bad change, then redeploy.

---

## Helpful reminders

- Always deploy from `production`.
- Keep feature work off the server; merge locally, then push.
- Run backup before risky updates (schema changes, upstream merges, dependency jumps).
- Ensure server PHP remains compatible with project requirement (PHP 8.2+).
