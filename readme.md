# Marketplace REST API

Laravel 12 API for users, buyers, sellers, products, categories, and purchases. PHP 8.2+ and Composer are required; local development uses MySQL. The application retains its original kernel/provider layout.

## Local setup

```sh
composer install
test -f .env || cp .env.example .env
php artisan key:generate
```

Set the database credentials in `.env`, create the database, then run:

```sh
php artisan migrate
php artisan serve
```

Apply migrations when upgrading too: the new `api_tokens` table is required for authentication. `php artisan db:seed` is optional and **truncates application tables**. Factories expect users before products, and products before transactions.

## Authentication and access

Register using `POST /api/users` with `name`, `email`, `password`, and `password_confirmation`. Registration always creates an unverified, non-admin account. Obtain a token:

```sh
curl -X POST http://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"you@example.com","password":"your-password"}'
```

Use the returned `data.token` as `Authorization: Bearer <token>` over HTTPS outside local development. Tokens are stored as SHA-256 hashes in `api_tokens`, one per login, so several clients can stay signed in at once. Login allows five attempts per minute per IP. `POST /api/logout` revokes only the token it is called with; password and email changes revoke every token for the account, so log in again afterward.

| Operation | Access |
| --- | --- |
| Registration, login, catalog reads | Public |
| View/update/delete an account | Account owner or admin |
| List accounts or change admin roles | Admin; role changes require a verified target |
| Create/update/delete products; attach/detach categories | Seller/product owner or admin |
| Create a purchase | Buyer or admin; buyer and seller must be verified |
| Create/update/delete categories | Admin |

An owner can update their name without sending a password. Changing the email or password also requires `current_password` (the caller's own password, so an admin editing another account confirms with their own). To change a password, send `password` and matching `password_confirmation`. Email changes reset verification. Product image-only updates are supported. Public buyer and seller listings show only profile fields (no email, role, or verification status).

There is no email verification delivery flow yet. For a local development account, use `php artisan tinker` to provision a trusted admin:

```php
App\User::where('email', 'you@example.com')->firstOrFail()->forceFill([
    'verified' => App\User::VERIFIED_USER,
    'verification_token' => null,
    'admin' => App\User::ADMIN_USER,
])->save();
```

## Tests and Docker

```sh
vendor/bin/phpunit --do-not-cache-result
vendor/bin/phpunit tests/Feature/UserUpdateTest.php
docker build -t restfulapi .
docker run --rm restfulapi vendor/bin/phpunit --do-not-cache-result
```

Run `php artisan config:clear` first if configuration is cached; tests refuse cached configuration. Tests force an in-memory SQLite database and use fake image storage; they do not use your `.env` database. They cover authentication, ownership, updates, failed image writes, and purchase stock changes. SQLite does not exercise MySQL's concurrent row locks.

The PHP 8.4 Docker image includes Composer dependencies, MySQL/SQLite drivers, and GD. It runs PHP-FPM on port 9000; an HTTP reverse proxy is needed to serve requests. To run the full stack (PHP-FPM, nginx on port 80, MySQL 5.7 on port 3306):

```sh
test -f .env || cp .env.example .env
docker compose up -d --build
docker compose exec restfulAPI-app php artisan key:generate
docker compose exec restfulAPI-app php artisan migrate
```

The API is then served at `http://localhost/api`. The container keeps the image's `vendor/` in an anonymous volume, which Compose reuses across rebuilds; after changing `composer.json` or `composer.lock`, run `docker compose up -d --build -V` (`--renew-anon-volumes`) so it is refreshed. Compose sets the database connection itself, overriding `.env`. The nginx, PHP, and MySQL settings live in `nginx/conf.d/`, `php/local.ini`, and `mysql/my.cnf`. Its sample credentials are for local use only. `.dockerignore` excludes environment secrets, local dependencies, and runtime caches from the image.

## Legacy frontend scaffold

`npm install`, then `npm run dev`, `npm run watch`, or `npm run prod` builds the optional Vue/Sass assets through Laravel Mix. These assets are separate from the API test suite.

## License

The Laravel framework is licensed under the [MIT license](https://opensource.org/licenses/MIT).
