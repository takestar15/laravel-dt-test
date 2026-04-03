# Translation Service

This Laravel 13 project includes an authenticated translation API for multi-locale storage, search, and frontend JSON export.

## Setup

1. Run `composer install`
2. Run `npm install`
3. Copy `.env.example` to `.env`
4. Run `php artisan key:generate`
5. Run `php artisan migrate`
6. Run `php artisan serve`

For Docker-based local setup, use [compose.dev.yaml](./compose.dev.yaml).
For Docker-based development and testing:

1. Run `docker compose -f compose.dev.yaml up -d postgres workspace`
2. Run `docker compose -f compose.dev.yaml exec workspace php artisan migrate --force --no-interaction`
3. Run tests with `docker compose -f compose.dev.yaml exec workspace php vendor/bin/pest --compact`

## API Flow

1. Create a token with `POST /api/tokens`
2. Send `Authorization: Bearer <token>` on translation requests
3. Use `GET /api/translations/export?locale=en` for frontend-ready JSON
4. For public frontend consumption and CDN caching, use `GET /api/public/translations/version` and `GET /api/public/translations/export?locale=en&tags[]=web`

## Endpoints

- `POST /api/tokens`
- `GET /api/translations`
- `POST /api/translations`
- `GET /api/translations/{translation}`
- `PATCH /api/translations/{translation}`
- `GET /api/translations/export`
- `GET /api/public/translations/export`
- `GET /api/public/translations/version`
- `GET /docs/api`
- `GET /docs/api.json`

## Design Notes

- Translations are unique per `locale` and `key`.
- Tags are normalized into a dedicated table plus pivot table for scalable filtering.
- Export responses use versioned cache keys and are invalidated on every create or update, so responses stay fast without serving stale translation data.
- The export builder uses a cursor-backed JSON serialization path so large datasets can be exported without loading the full translation set into memory at once.
- A public translation export endpoint is available for CDN/frontend use. It returns cache-friendly JSON with `Cache-Control`, `ETag`, and `X-Translation-Version` headers.
- A lightweight public translation version endpoint is available so frontend applications can build version-aware translation requests for CDN caching.
- Sanctum provides token-based authentication through Laravel's native API mode.
- `php artisan translations:seed --count=100000` creates a large dataset for scalability testing.
- `docker compose -f compose.dev.yaml exec workspace php artisan scramble:export` writes a Swagger-compatible OpenAPI JSON document to [openapi.json](/home/dev/test/laravel-test/public/openapi.json).
- Scramble serves interactive documentation at `/docs/api` and the raw document at `/docs/api.json`.
- In testing mode, the Scramble JSON endpoint is accessible so the API documentation test suite can validate the generated OpenAPI document.

## Testing

- Pest is the active test runner for this project.
- The automated test suite is configured to run against the dedicated Postgres testing database `laravel-test_testing`, not the local development database `laravel-test`.
- Run targeted translation tests with `docker compose -f compose.dev.yaml exec workspace php vendor/bin/pest tests/Feature/Api/TranslationApiTest.php tests/Unit/Translations --compact`
- Run the full remaining suite with `docker compose -f compose.dev.yaml exec workspace php vendor/bin/pest --compact`
- Run coverage with `docker compose -f compose.dev.yaml exec workspace php vendor/bin/pest --coverage --compact`
- Performance tests are opt-in to avoid flaky failures across different machines.
- Enable and run performance tests locally with `RUN_PERFORMANCE_TESTS=true php artisan test --compact --group=performance`
- Tune performance budgets per machine with environment variables such as `PERF_ITERATIONS=5`, `PERF_API_BUDGET_MS=200`, and `PERF_EXPORT_BUDGET_MS=500`
