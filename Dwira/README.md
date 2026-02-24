# DWIRA - Vercel Deployment (PHP + MySQL)

## What was prepared
- `config/db.php` now supports environment variables for production and keeps your local defaults.
- Root `index.php` redirects to `/Dwira/index.php` so your existing routes and links continue to work.
- `vercel.json` was added to run all PHP files with `vercel-php`.
- `.vercelignore` excludes the local MySQL data folder from deployments.

## Required database setup
Vercel does not host MySQL itself. Use a managed MySQL provider and import `dwira_db.sql`.

## Environment variables (Vercel Project Settings -> Environment Variables)
You can use either `DATABASE_URL` or `DB_*` variables.

Option A (recommended):
- `DATABASE_URL=mysql://USER:PASSWORD@HOST:PORT/DB_NAME`

Option B:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

Optional:
- `APP_ENV=production`

## Deploy
1. Push this repository to GitHub/GitLab/Bitbucket.
2. Import the repo into Vercel.
3. Add the environment variables above.
4. Deploy.

After deploy, open your domain root (`/`) and you will be redirected to `/Dwira/index.php`.
