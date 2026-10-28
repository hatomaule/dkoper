# DKoper Setup

Start with AI_HANDOFF.md for the complete architecture and current stop point.

## Local Runtime

```bash
cd ~/projects/DKoper
sudo service mariadb start
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=StaffAccountSeeder
npm install
npm run build
./start-local.sh
```

Use values from local MariaDB only in `.env`. Do not commit `.env`.

## Local Accounts

Seeded staff usernames are `alan` and `chalo`. Both default password values are intended for first login only and must be changed.

## Core Commands

```bash
php artisan migrate:status
php artisan test --compact
npm run build
php artisan optimize:clear
```

Start the local web server on DKoper's assigned port:

```bash
./start-local.sh
```

Open `http://localhost:8000`. The `composer run dev` workflow also serves
DKoper on port `8000`.

## Deployment

Read `AI_HANDOFF.md` before deploying to InfinityFree. Production credentials must be entered only in hosting configuration or a non-repository `.env`.

## FIDES Platform

The Superuser portal is available at `/platform` after FIDES has been installed and enabled. A new cooperative gets a dedicated database and its own Owner/Admin accounts. If MySQL/MariaDB denies `CREATE DATABASE` (error 1044), FIDES provisions that tenant as a separate SQLite file under `storage/fides-tenants`; keep this directory writable and persistent, and include it in backups.

The isolated sample cooperative `Cair Dana` is provisioned with `php artisan fides:seed-demo`. This one-time command refuses to overwrite an existing `cair-dana` tenant and creates 21 synthetic borrowers with varied active loan amounts. Its generated Owner/Admin passwords are printed once by the command and must be changed at first login.
