# Deployment preparation

The repository is a working application under active development, not a certified production-ready business suite. Review `IMPLEMENTATION_STATUS.md` and configure only implemented capabilities.

## Environment

1. Install PHP/Composer extensions required by `composer check-platform-reqs`.
2. Use a tested database engine. PostgreSQL and SQLite are verified here; SQLite is convenient for a local demo.
3. Run `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build` in the build process.
4. Configure a unique `APP_KEY`, actual `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, and `DEMO_MODE=false` for a real deployment.
5. Configure database credentials, cache/queue infrastructure, email delivery, and private log access. Never commit `.env` or keys.
6. Point the web server document root at `public/`. Serve over HTTPS; configure trusted proxies/secure session cookies to match the actual host.
7. Make `storage/` and `bootstrap/cache/` writable by the application user, then run `php artisan storage:link` for approved public product photos.
8. Deploy migrations with an appropriate backup and maintenance strategy: `php artisan migrate --force`.
9. Publish/update Filament assets with `php artisan filament:upgrade` as required by the locked dependency version.
10. Cache configuration/routes/views after verifying configuration: `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.

## Workers and scheduler

Current core business operations execute synchronously; no application business outbox is implemented. If using database/Redis queues for package or future jobs, supervise `php artisan queue:work --tries=3` and configure a scheduler invoking `php artisan schedule:run` every minute for actual scheduled tasks. Restart workers on deployment. Do not assume the presence of a worker makes unimplemented notification jobs exist.

## Demo separation

The demo seeder refuses production and requires explicit demo mode. Do not copy a demo database into a real deployment: the demo accounts share a published password. Use an empty database and `/register` for onboarding, then create staff and opening stock.

## Backups and restoration

Back up the database and private/public application files consistently. Keep `APP_KEY` securely recoverable because it is needed for encrypted data. Store backups separately from the application and limit access.

For PostgreSQL, use the database tools appropriate to your host, for example `pg_dump` and `pg_restore` with your own connection settings. For a stopped local SQLite demo, a consistent copy of `database/database.sqlite` can be used; do not make an arbitrary live file copy during writes.

A restore must be exercised in a separate nonproduction environment before relying on it. **A full backup/restore drill has not yet been completed for this repository.** Define retention, recovery objectives, and an upgrade rollback plan with the deployment operator.
