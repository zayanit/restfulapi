# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A Laravel 12 (PHP ^8.2) RESTful API for a marketplace domain: `Users` specialize into `Buyer` and `Seller` (both extend `App\User`), who trade `Product`s (belonging to a `Seller`, tagged with many `Category`) via `Transaction`s (a `Buyer` buying a `Product`). There is no frontend of consequence — `resources/`, `webpack.mix.js`, and `package.json` are the stock Laravel scaffold and not actively developed.

## Commands

- Install PHP deps: `composer install`
- Run the app locally: `php artisan serve`
- Run all tests: `php artisan test` or `vendor/bin/phpunit`
- Run a single test file: `vendor/bin/phpunit tests/Feature/ExampleTest.php`
- Run a single test method: `vendor/bin/phpunit --filter testMethodName`
- Regenerate autoloader after adding classes: `composer dump-autoload`
- Run migrations: `php artisan migrate` (add `--seed` to also seed)
- Docker stack (app + nginx + mysql 5.7): `docker-compose up -d` (see `Dockerfile`, `docker-compose.yml`)

There is no configured linter/formatter (no Pint/PHPCS config present) — match the existing code style when editing.

## Architecture

### Model hierarchy and scoping

- `App\ApiModel` — base class for `Category`, `Product`, `Transaction`; adds `SoftDeletes`.
- `App\User` — base Eloquent auth model (also uses `SoftDeletes`). Has `verified`/`admin` string-flag constants (`VERIFIED_USER`, `ADMIN_USER`, etc.) rather than booleans.
- `App\Buyer extends User` — global scope `BuyerScope` restricts buyers to users that `has('transactions')`.
- `App\Seller extends User` — global scope `SellerScope` restricts sellers to users that `has('products')`.
- Because `Buyer`/`Seller` are scoped subtypes of the same `users` table, a "buyer" or "seller" only shows up in those resources once they have at least one transaction/product respectively.
- `Product` belongsToMany `Category` (pivot `category_product`), belongsTo `Seller`, hasMany `Transaction`. A DB trigger-like `boot()` hook auto-flips `status` to `unavailable` when `quantity` hits 0 on update.

### Controller/routing pattern

Routes live entirely in `routes/api.php` (no `web.php` routes are used) and are declared as nested `Route::resource(...)` pairs mirroring the model relationships, e.g. `products.categories`, `sellers.products`, `buyers.transactions`. Controllers are namespaced and directoried by their **primary** resource (`app/Http/Controllers/{Buyer,Category,Product,Seller,Transaction,User}/`), and a nested-resource controller name is `{Primary}{Nested}Controller`, e.g. `ProductCategoryController` handles `products/{product}/categories`. When adding a new nested endpoint, follow this same directory/naming convention and register it in `routes/api.php` next to its sibling resources.

All API controllers extend `App\Http\Controllers\ApiController`, which pulls in the `App\Traits\ApiResponser` trait for consistent JSON responses:
- `showAll($collection, $code = 200)` — wraps a collection as `{"data": [...]}`
- `showOne($model, $code = 200)` — wraps a single model as `{"data": {...}}`
- `errorResponse($message, $code)` — `{"error": ..., "code": ...}`

Use these helpers rather than calling `response()->json()` directly in new controllers.

### Auth

`app/Http/Controllers/Auth/*` are the stock Laravel `AuthenticatesUsers`/`RegistersUsers`/password-reset traits wired to session (`web`) auth — they are largely unused/legacy scaffolding, since the actual API resources under `routes/api.php` are not currently guarded by an `auth` middleware. Be cautious assuming request-level authentication exists when touching resource controllers.

### Middleware

`api` route group applies `throttle:60,1` and `bindings` (route-model binding substitution) — see `app/Http/Kernel.php`. There is no CSRF/session middleware on API routes.
