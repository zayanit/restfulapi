# Repository Guidelines

## Project Structure & Module Organization

This marketplace API uses Laravel 12 and PHP 8.2+ with a legacy layout. Models live in `app/`; controllers are grouped by resource under `app/Http/Controllers/`. Register endpoints in `routes/api.php` (served under `/api`). Keep the kernel/provider bootstrap structure.

`database/` contains migrations, class-based factories, and seeders. Tests live in `tests/Feature` and `tests/Unit`. Scaffolded Vue/JavaScript and Sass sources are in `resources/assets/`; Laravel Mix outputs to `public/js` and `public/css`.

## Build, Test, and Development Commands

- `composer install`: install locked PHP dependencies.
- Copy `.env.example` to `.env` if absent, configure MySQL, then run `php artisan key:generate`.
- `php artisan migrate`: apply database migrations.
- `php artisan serve`: start the local HTTP server.
- `vendor/bin/phpunit`: run both test suites.
- `vendor/bin/phpunit tests/Feature/UserUpdateTest.php`: run one test file; use `--filter testMethodName` for one method.
- `npm install`, then `npm run dev`, `npm run watch`, or `npm run prod`: install and build, watch, or optimize legacy frontend assets.

The Dockerfile uses PHP 8.4 with Composer and test extensions. Build with `docker build -t restfulapi .`; run tests with `docker run --rm restfulapi vendor/bin/phpunit --do-not-cache-result`. Factories use `Model::factory()`; seed users before products and transactions. `DatabaseSeeder` truncates application tables.

## Coding Style & Naming Conventions

Match existing PHP style: four-space indentation, opening class/method braces on separate lines, PascalCase classes, and camelCase methods. Follow resource controller names such as `ProductCategoryController` in the `Product/` directory. Extend `ApiController` and reuse `showAll`, `showOne`, and `errorResponse` for consistent JSON envelopes. No formatter/linter is configured.

## Testing Guidelines

Use PHPUnit 11.5; name files `*Test.php` and methods `test...`. Endpoint tests extend `Tests\ApiTestCase` with `RefreshDatabase`; isolated logic tests extend PHPUnit’s `TestCase`. `phpunit.xml` forces in-memory SQLite and array cache/session stores. Cover authentication, ownership, validation, image failures, and stock changes. SQLite tests do not verify MySQL row-lock concurrency. Clear cached configuration before tests.

## Commit & Pull Request Guidelines

Use short imperative commit subjects, following history: `Fix high dependency vulnerabilities`. Keep commits focused. PRs should describe behavior changes, link issues, list validation commands/results, and identify existing failures. Include request/response examples for API changes.

## Security & Configuration

Keep credentials in untracked `.env` files. Run migrations before using API tokens. `POST /api/login` issues a bearer token per login; logout revokes that token, and password/email changes (which require `current_password`) revoke all of the account's tokens. Protect mutations with `auth:api` and policies; category writes and role changes require an admin. See `readme.md` for access rules. Never reuse sample database credentials outside local development.
