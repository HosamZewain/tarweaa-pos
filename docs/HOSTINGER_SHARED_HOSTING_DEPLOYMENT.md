# Tarweaa POS Deployment Guide for Hostinger Shared Hosting

This guide is for deploying this existing Laravel + Filament + POS project to **Hostinger Shared Hosting** using the code already pushed to GitHub.

It is written specifically for this project, not as a generic Laravel guide.

## 1. Project Deployment Requirements

Before deploying, make sure the hosting plan can support this app:

- PHP **8.3 or newer**
- MySQL / MariaDB database
- SSH access strongly recommended
- Composer available on the server, or an alternate way to upload `vendor/`
- Ability to point the domain/subdomain document root to Laravel `public/`, or a fallback plan using `public_html`

This project also needs:

- built frontend assets from Vite
- writable `storage/` and `bootstrap/cache/`
- database migrations
- seed data for system permissions / roles / baseline master data

## 2. Important Project-Specific Notes

This repository is not a plain Laravel skeleton. A few details matter during deployment:

- Admin panel path is `/admin`
- POS login path is `/pos/login`
- Kitchen path is `/kitchen`
- The app uses Filament with a custom Vite theme:
  - `resources/css/filament/admin/theme.css`
- The app uses Sanctum tokens for POS / kitchen APIs
- Default env values use database-backed services:
  - `SESSION_DRIVER=database`
  - `CACHE_STORE=database`
  - `QUEUE_CONNECTION=database`
- This project has upload fields for menu/category images, so `php artisan storage:link` matters

For shared hosting, I recommend:

- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=sync`

Reason:

- sessions and cache work well on shared hosting with the database driver
- queue workers are usually inconvenient on shared hosting
- `sync` avoids needing a long-running worker and is safer for this app unless you later decide to add a cron-based queue worker

## 3. Recommended Deployment Strategy

For Hostinger Shared Hosting, the safest flow is:

1. Keep the source code in GitHub
2. Build frontend assets locally or in CI
3. Deploy to Hostinger via SSH/Git or upload
4. Run Laravel production commands on the server

### Recommended approach for this repo

Because this app uses Vite and Tailwind, do **not** rely on Node being available on shared hosting.

Instead:

1. On your local machine:
   - run `npm ci`
   - run `npm run build`
2. Confirm `public/build` exists
3. Deploy code that already includes the built assets

That keeps the server-side deployment simpler.

## 4. Prepare the Production Environment in Hostinger

In Hostinger hPanel:

1. Create the production domain or subdomain
2. Create the MySQL database
3. Create the database user and password
4. Set the PHP version to **8.3+**
5. Enable SSH access for the hosting account if your plan supports it

Collect these values before starting:

- domain name
- database name
- database username
- database password
- SSH host / port / username

## 5. Choose the Web Root Layout

### Option A. Best option: domain root points to Laravel `public/`

If Hostinger lets you set the domain or subdomain document root, point it to:

```text
<project-folder>/public
```

This is the clean Laravel approach.

### Option B. Fallback: use `public_html`

If Hostinger does not let you point directly to Laravel `public/`, use this structure:

- keep the Laravel app in a folder outside `public_html`, for example:
  - `~/domains/your-domain.com/tarweaa-app`
- place only the contents of Laravel `public/` inside:
  - `~/domains/your-domain.com/public_html`

Then update `public_html/index.php` so its paths point to the real app folder.

Example:

```php
require __DIR__.'/../tarweaa-app/vendor/autoload.php';

$app = require_once __DIR__.'/../tarweaa-app/bootstrap/app.php';
```

Adjust the relative path to match your real folder layout.

Do not expose the full Laravel app directly inside `public_html` if you can avoid it.

## 6. Deployment Option 1: Deploy from GitHub Using SSH

This is the best shared-hosting flow if SSH is available.

### 6.1 Connect with SSH

Example:

```bash
ssh u123456789@your-server-host
```

### 6.2 Go to your domain directory

Example Hostinger-style layout often looks like:

```bash
cd ~/domains/your-domain.com
```

### 6.3 Clone the repository

If using the clean Laravel structure:

```bash
git clone https://github.com/YOUR-USERNAME/YOUR-REPO.git tarweaa-app
cd tarweaa-app
```

If the repo is private, use Hostinger-supported Git auth or deploy with a zip/upload workflow.

### 6.4 Install PHP dependencies

Hostinger documents Composer on supported web/cloud plans, and for modern PHP they instruct using `composer2`.

Run:

```bash
composer2 install --no-dev --optimize-autoloader
```

### 6.5 Upload or include built frontend assets

If you already built locally:

- make sure `public/build` is present in the deployed code

If you do not want built files in the main branch, use a deploy branch or upload the built files manually during deployment.

## 7. Deployment Option 2: Upload the Project Manually

Use this if Git or SSH is unavailable or inconvenient.

### 7.1 Prepare locally

On your local machine:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Make sure these exist before uploading:

- `vendor/`
- `public/build/`

### 7.2 Upload files

Upload the app to Hostinger using File Manager, FTP, or SFTP.

If using `public_html` fallback mode:

- upload the Laravel app outside `public_html`
- copy only the contents of the repo `public/` folder into `public_html`
- update `public_html/index.php` paths

## 8. Create the Production `.env`

Create the production `.env` file on the server.

Recommended starting point:

```dotenv
APP_NAME="Tarweaa POS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://your-domain.com

APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_DB_NAME
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=

CACHE_STORE=database

QUEUE_CONNECTION=sync

FILESYSTEM_DISK=public

LOG_CHANNEL=stack
LOG_LEVEL=error
```

Notes:

- Leave `SESSION_DOMAIN` empty unless you specifically need a custom cookie domain
- `FILESYSTEM_DISK=public` is recommended for uploaded menu/category images
- `QUEUE_CONNECTION=sync` is recommended on Hostinger Shared Hosting

## 9. Run the Laravel Production Commands

From the app root on the server:

### 9.1 Generate the app key

```bash
php artisan key:generate
```

### 9.2 Run migrations

```bash
php artisan migrate --force
```

### 9.3 Seed required system data

This project relies on seeded baseline data such as roles, permissions, and related master records.

Run:

```bash
php artisan db:seed --force
```

### 9.4 Create the storage symlink

```bash
php artisan storage:link
```

### 9.5 Cache production config

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If any of these fail, stop and fix that issue before going live.

## 10. File Permissions

Make sure these are writable by PHP:

- `storage/`
- `bootstrap/cache/`

If needed:

```bash
chmod -R 775 storage bootstrap/cache
```

Use your account/group-specific ownership rules if Hostinger requires a different permission setup.

## 11. First Login / Smoke Test Checklist

After deployment, test these URLs:

- `https://your-domain.com/`
- `https://your-domain.com/pos/login`
- `https://your-domain.com/admin`
- `https://your-domain.com/kitchen`

Then verify:

1. admin login works
2. POS login works
3. kitchen page opens correctly
4. uploaded images display
5. admin theme loads correctly
6. payments, terminals, and reports pages open
7. permissions exist in the admin panel

## 12. Suggested First Production Validation

Before using it live, validate:

1. create or edit a menu item from `/admin/menu-items`
2. upload an image and confirm it appears
3. create a payment terminal in admin
4. create a test order in POS
5. pay one order with cash
6. pay one order with card terminal
7. confirm drawer totals only reflect cash correctly
8. confirm terminal report shows:
   - gross amount
   - fee amount
   - net settlement
9. confirm discount approval still works
10. confirm kitchen flow still works

## 13. Updating the App After the First Deploy

For future updates:

### If deploying with Git

```bash
cd ~/domains/your-domain.com/tarweaa-app
git pull origin main
composer2 install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If frontend files changed, redeploy the built `public/build` assets too.

### If deploying by upload

Upload changed files, then run:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 14. What NOT to Run on Production

Do **not** run these on production:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

These commands destroy or rebuild live tables and will remove data.

## 15. Logging and Troubleshooting

### If you get a 500 error

Check:

- `storage/logs/laravel.log`
- `.env` values
- file permissions
- whether `vendor/` exists
- whether `public/build/` exists

### If CSS/JS is broken

Usually one of these is the cause:

- `public/build` was not deployed
- Vite assets were built locally but not uploaded
- the domain root is not pointing to the right `public` folder

### If login/session behavior is broken

Check:

- `APP_URL`
- database connection
- migrations for `sessions`, `cache`, and `cache_locks`
- whether cookies are being set for the correct domain

### If uploads fail or images do not load

Check:

- `FILESYSTEM_DISK=public`
- `php artisan storage:link`
- write access for `storage/app/public`

### If admin permissions look incomplete

Run:

```bash
php artisan db:seed --force
```

This project uses seeded permission and master-data records.

## 16. Cron Jobs / Queue Workers

At the moment, this project does **not** need a scheduler for a normal deployment.

Also, for shared hosting, I recommend keeping:

```dotenv
QUEUE_CONNECTION=sync
```

That avoids needing a persistent worker.

If later you add scheduled tasks or queue workers, set those up intentionally through Hostinger cron jobs.

## 17. Recommended Deployment Checklist

Use this exact order:

1. build assets locally with `npm run build`
2. upload or pull the latest code from GitHub
3. install PHP dependencies with `composer2 install --no-dev --optimize-autoloader`
4. create/update `.env`
5. run `php artisan key:generate`
6. run `php artisan migrate --force`
7. run `php artisan db:seed --force`
8. run `php artisan storage:link`
9. run `php artisan config:cache`
10. run `php artisan route:cache`
11. run `php artisan view:cache`
12. test `/admin`, `/pos/login`, and `/kitchen`

## 18. Useful References

- Hostinger Composer on shared/cloud hosting:
  - <https://www.hostinger.com/support/5792078-how-to-use-composer-at-hostinger/>
- Laravel deployment and production practices:
  - <https://laravel.com/docs>

## 19. Final Recommendation

For this repo on Hostinger Shared Hosting, the safest real-world setup is:

- deploy from GitHub to a non-public app folder
- serve only Laravel `public/`
- build Vite assets locally
- use `composer2`
- use MySQL in production
- use database sessions + database cache
- use `QUEUE_CONNECTION=sync`
- always run `migrate --force` and `db:seed --force`
- never run destructive migration commands on production

If you want, the next step can be a **Hostinger-ready production checklist file with your real domain placeholders filled in**, or a **GitHub Actions deploy workflow** for this same server.
