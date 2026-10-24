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
php artisan serve
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

## Deployment

Read `AI_HANDOFF.md` before deploying to InfinityFree. Production credentials must be entered only in hosting configuration or a non-repository `.env`.
