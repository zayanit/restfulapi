# Repository Guidelines

## Project Structure & Module Organization

This marketplace REST API uses Laravel 12 and PHP 8.2+, retaining a legacy Laravel application layout. Models live directly in `app/`; controllers are grouped by resource under `app/Http/Controllers/`. Register API endpoints in `routes/api.php` (served under `/api`). Keep the existing kernel/provider bootstrap structure.

`database/` contains migrations, legacy factories, and seeders. Tests live in `tests/Feature` and `tests/Unit`. Scaffolded Vue/JavaScript and Sass sources are in `resources/assets/`; Laravel Mix outputs to `public/js` and `public/css`.

## Build, Test, and Development Commands

- `composer install`: install locked PHP dependencies.
- Copy `.env.example` to `.env` if absent, configure MySQL, then run `php artisan key:generate`.
- `php artisan migrate`: apply database migrations.
- `php artisan serve`: start the local HTTP server.
- `vendor/bin/phpunit`: run both test suites.
- `vendor/bin/phpunit tests/Feature/ExampleTest.php`: run one test file; use `--filter testMethodName` for one method.
- `npm install`, then `npm run dev`, `npm run watch`, or `npm run prod`: install and build, watch, or optimize legacy frontend assets.

The Dockerfile still targets PHP 7.3 and needs updating before it can satisfy current Composer requirements. Legacy factories require modernization before seeding; `DatabaseSeeder` also truncates application tables.

## Coding Style & Naming Conventions

Match existing PHP style: four-space indentation, opening class/method braces on separate lines, PascalCase classes, and camelCase methods. Follow resource controller names such as `ProductCategoryController` in the `Product/` directory. Extend `ApiController` and reuse `showAll`, `showOne`, and `errorResponse` for consistent JSON envelopes. No formatter or linter is configured.

## Testing Guidelines

Use PHPUnit 11.5; name files `*Test.php` and methods `test...`. Add endpoint tests under `Feature` and isolated logic tests under `Unit`. No coverage threshold is configured. Configure a separate test database: `phpunit.xml` does not isolate the database connection. The stock feature test expects `/` to return 200, although `routes/web.php` defines no routes; account for this baseline mismatch when reporting results.

## Commit & Pull Request Guidelines

History uses short imperative subjects such as `Fix high dependency vulnerabilities` and `Add CLAUDE.md file`. Keep commits focused. PRs should describe behavior changes, link relevant issues, list validation commands/results, and identify existing failures. Include request/response examples for API changes.

## Security & Configuration

Keep credentials in untracked `.env` files. Never commit production secrets or reuse the sample Docker database password outside local development.
