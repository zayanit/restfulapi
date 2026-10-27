# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A Laravel 12 (PHP ^8.2) RESTful API for a marketplace domain: `Users` specialize into `Buyer` and `Seller` (both extend `App\User`), who trade `Product`s (belonging to a `Seller`, tagged with many `Category`) via `Transaction`s (a `Buyer` buying a `Product`). There is no frontend of consequence — `resources/`, `webpack.mix.js`, and `package.json` retain the Laravel frontend scaffold. `readme.md` documents local setup and API authentication.

**Legacy skeleton on a modern framework.** The app was created on Laravel 5.x and the framework was bumped to 12 without migrating the app structure. It still uses `App\Http\Kernel`, `App\Exceptions\Handler`, `App\Providers\RouteServiceProvider`, and an old-style `bootstrap/app.php`; models live directly in `App\` (not `App\Models\`); seeders are in `database/seeds/` (classmap-autoloaded). Follow these existing conventions — don't introduce Laravel 11+ `bootstrap/app.php` `withRouting`/`withMiddleware` configuration or `App\Models` alongside them.

## Commands

- Install PHP deps: `composer install`
- Run the app locally: `php artisan serve`
- Run all tests: `vendor/bin/phpunit`
- Run a single test file: `vendor/bin/phpunit tests/Feature/UserUpdateTest.php`
- Run a single test method: `vendor/bin/phpunit --filter testMethodName`
- Regenerate autoloader after adding classes: `composer dump-autoload`
- Run migrations: `php artisan migrate` (add `--seed` to also seed; `DatabaseSeeder` truncates all tables first)
- Docker stack (app + nginx + mysql 5.7): `docker-compose up -d` (see `Dockerfile`, `docker-compose.yml`)

There is no configured linter/formatter (no Pint/PHPCS config present) — match the existing code style when editing.

Factories are class-based (`Database\Factories\{Model}Factory`, PSR-4 autoloaded; `User` and `ApiModel` use `HasFactory`), so use `Model::factory()`. `ProductFactory` and `TransactionFactory` pick random *existing* users/sellers/products rather than creating related models, so seed in order: users → categories → products → transactions. `DatabaseSeeder` itself stays un-namespaced in `database/seeds/`; `db:seed` falls back to it.

Tests cover API authentication/authorization, user updates, image replacement failures, and purchase stock handling. `phpunit.xml` forces SQLite `:memory:` and array cache/session stores. Endpoint tests extend `Tests\ApiTestCase` (`RefreshDatabase`); unit tests extend PHPUnit’s `TestCase`. Clear cached configuration before tests; the test bootstrap refuses cached config to avoid using a non-test database. SQLite does not verify MySQL row-lock concurrency. Docker uses PHP 8.4: `docker build -t restfulapi .`, then `docker run --rm restfulapi vendor/bin/phpunit --do-not-cache-result`.

## Architecture

### Model hierarchy and scoping

- `App\ApiModel` — base class for `Category`, `Product`, `Transaction`; adds `SoftDeletes`.
- `App\User` — base Eloquent auth model (also uses `SoftDeletes`). Has `verified`/`admin` string-flag constants (`VERIFIED_USER = '1'`, `ADMIN_USER = 'true'`, etc.) rather than booleans. Mutators lowercase `name`/`email` on write, and `name` is `ucwords`'d on read.
- `App\Buyer extends User` — global scope `BuyerScope` restricts buyers to users that `has('transactions')`.
- `App\Seller extends User` — global scope `SellerScope` restricts sellers to users that `has('products')`.
- Because `Buyer`/`Seller` are scoped subtypes of the same `users` table, a "buyer" or "seller" only shows up in those resources once they have at least one transaction/product respectively. That's why the *create* endpoints type-hint `User` instead of the subtype: `SellerProductController::store(Request, User $seller)` and `ProductBuyerTransactionController::store(Request, Product, User $buyer)`. A first-time seller/buyer would 404 under route-model binding otherwise. Keep this in mind for any endpoint that creates someone's first product or transaction.
- `Product` belongsToMany `Category` (pivot `category_product`, `pivot` hidden from JSON), belongsTo `Seller`, hasMany `Transaction`. An `updated` model event in `Product::boot()` flips `status` to `unavailable` when `quantity` reaches 0.
- Product images are stored on the `images` disk (`FILESYSTEM_DRIVER=images` → `public/img`), using explicit `images` disk calls for uploads and deletion.

### Controller/routing pattern

Routes live entirely in `routes/api.php` and are served under the `/api` prefix (set in `RouteServiceProvider`). `routes/web.php` is loaded but empty. Routes are declared as nested `Route::resource(...)` pairs mirroring the model relationships, e.g. `products.categories`, `sellers.products`, `buyers.transactions`, plus the three-level `products.buyers.transactions` (store only), and are restricted with `only`/`except`. Controllers are namespaced and directoried by their **primary** resource (`app/Http/Controllers/{Buyer,Category,Product,Seller,Transaction,User}/`), and a nested-resource controller name is `{Primary}{Nested}Controller`, e.g. `ProductCategoryController` handles `products/{product}/categories`. When adding a new nested endpoint, follow this same directory/naming convention and register it in `routes/api.php` next to its sibling resources.

Read-only nested "index" endpoints that cross more than one relationship (e.g. `BuyerCategoryController`) are built by eager-loading through the chain and then flattening in PHP: `->with('product.categories')->get()->pluck('product.categories')->collapse()->unique('id')->values()`. Reuse that idiom for new ones.

All API controllers extend `App\Http\Controllers\ApiController`, which pulls in the `App\Traits\ApiResponser` trait for consistent JSON responses:
- `showAll($collection, $code = 200)` — wraps a collection as `{"data": [...]}`
- `showOne($model, $code = 200)` — wraps a single model as `{"data": {...}}`
- `errorResponse($message, $code)` — `{"error": ..., "code": ...}`

Use these helpers rather than calling `response()->json()` directly in new controllers.

### Error handling

`App\Exceptions\Handler` also uses `ApiResponser` and converts exceptions into the same `{"error", "code"}` shape. Validation errors become 422 with the field→messages map as `error`, `ModelNotFoundException` becomes 404 ("Does not exist any {model}..."), any `HttpException` passes its status/message through, MySQL FK error 1451 becomes 409, and anything else becomes a generic 500 (unless `APP_DEBUG`). Controllers validate with `$this->validate($request, $rules)` and let the handler format the failure.

To abort from a helper/guard method, **throw** `Symfony\Component\HttpKernel\Exception\HttpException` (as `SellerProductController::checkSeller` and `ProductBuyerTransactionController::checkAddingPossibility` do). Returning `$this->errorResponse(...)` from a helper does nothing unless the caller returns that value too.

### Auth

`ApiTokenController` implements `POST /api/login` (five attempts per minute) and authenticated `POST /api/logout`. The existing `api` token guard hashes tokens with SHA-256; `users.api_token` is hidden and not mass assignable. Login replaces the previous token; logout and password/email changes revoke it. Send `Authorization: Bearer <token>` over HTTPS. Stock session auth controllers remain unrouted.

Registration and catalog reads remain public. Account reads and every other mutation require `auth:api`. `UserPolicy` permits owners/admins to view, update, and delete accounts; listing accounts and changing roles require an admin. Product updates/deletion/category associations require the product owner or admin; product creation and purchases authorize the seller/buyer account. Category writes use the admin-only `manage-categories` gate. Keep both route middleware and controller authorization when adding endpoints.

Product image writes explicitly use the `images` disk. Replacement stores the new file, saves the model transactionally, then deletes the old file; a save failure cleans up the replacement. An image-only update is valid.

### Middleware

The `api` route group applies `throttle:60,1` and `bindings` (route-model binding substitution); see `app/Http/Kernel.php`. There is no CSRF/session middleware on API routes.
