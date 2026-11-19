# Laravel Boundaries

[![tests](https://github.com/toshkq93/laravel-boundaries/actions/workflows/tests.yml/badge.svg)](https://github.com/toshkq93/laravel-boundaries/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/toshkq93/laravel-boundaries.svg)](https://packagist.org/packages/toshkq93/laravel-boundaries)
[![License](https://img.shields.io/packagist/l/toshkq93/laravel-boundaries.svg)](LICENSE)

A modular monolith for Laravel whose module boundaries are declared, and (soon) checked.

Splitting an application into `modules/Billing`, `modules/Catalog` and so on is easy. Keeping `Billing` from reaching into the internals of `Catalog` a year later is not: nothing in Laravel stops it. This package gives every module its own service provider, a list of the modules it may use (`$dependsOn`) and a public API (`Contracts/`, `Events/`, `Data/`). The next releases add `modules:check`, which reads your code and fails CI when a module uses another module's internals, a module it did not declare, or a dependency cycle.

> **Status:** early development. This release covers the module skeleton: `make:module`, module service providers, automatic registration and `modules:list`. Boundary checks come next (see [Roadmap](#roadmap)).

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13

## Installation

```bash
composer require toshkq93/laravel-boundaries
```

The service provider is auto-discovered. Optionally publish the config and the stubs:

```bash
php artisan vendor:publish --tag=boundaries-config
php artisan vendor:publish --tag=boundaries-stubs
```

## Quick start

```bash
php artisan make:module Billing
```

```
modules/Billing/
├── Providers/BillingServiceProvider.php   Modules\Billing\Providers\BillingServiceProvider
├── Contracts/                             public API: interfaces other modules depend on
├── Events/                                public API: events other modules listen to
├── Data/                                  public API: DTOs passed between modules
├── config/billing.php                     config('billing.*')
├── database/migrations/
├── lang/                                  __('billing::messages.paid')
└── routes/
    ├── api.php                            "api" middleware group, /api prefix
    └── web.php                            "web" middleware group
```

The first `make:module` adds the PSR-4 entry to `composer.json` and runs `composer dump-autoload`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\": "modules/"
    }
}
```

Only that line is added; the rest of the file keeps its formatting. Later modules need neither the entry nor a dump. If `composer.json` already maps `Modules\` to another directory, the command leaves it alone and tells you what to change.

The module's provider is registered automatically, so the module's routes, config, migrations and translations work right away:

```bash
php artisan modules:list
```

```
+----------+------------------+------------------+-------------------------+------------------------------+
| Module   | Namespace        | Path             | Provider                | Depends on                   |
+----------+------------------+------------------+-------------------------+------------------------------+
| Billing  | Modules\Billing  | modules/Billing  | BillingServiceProvider  | Catalog                      |
| Catalog  | Modules\Catalog  | modules/Catalog  | CatalogServiceProvider  | —                            |
| Shipping | Modules\Shipping | modules/Shipping | ShippingServiceProvider | Billing, Warehouse (missing) |
+----------+------------------+------------------+-------------------------+------------------------------+
```

`modules:list --json` prints the same as JSON (with `missing_dependencies` per module) for scripts and CI.

`make:module` refuses to touch an existing module. `--force` overwrites the generated files (provider, routes, config) with fresh stubs; the module's other files stay.

## The module service provider

```php
namespace Modules\Billing\Providers;

use Toshkq93\Boundaries\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    /**
     * Modules Billing uses, by name.
     *
     * @var list<string>
     */
    protected array $dependsOn = ['Catalog'];

    public function register(): void
    {
        parent::register();

        $this->app->bind(PaymentGateway::class, StripeGateway::class);
    }

    public function boot(): void
    {
        parent::boot();

        //
    }
}
```

Keep the `parent::register()` and `parent::boot()` calls: they load what the module ships, by convention, from the module root:

| What | Where | How it is loaded |
|------|-------|------------------|
| Config | `config/billing.php` | merged as `billing`: `config('billing.currency')` |
| | `config/gateways.php` (any other file) | merged as `billing.gateways`: `config('billing.gateways.default')` |
| Web routes | `routes/web.php` | `web` middleware group |
| API routes | `routes/api.php` | `api` middleware group, `/api` prefix |
| Migrations | `database/migrations` | `php artisan migrate` runs them |
| Translations | `lang/en/messages.php`, `lang/en.json` | `__('billing::messages.paid')`, JSON strings |

Each part is loaded only when the file or directory exists. Config values the application already set (for example in `config/billing.php` of the app) win over the module's, as with `mergeConfigFrom()`. Routes are skipped when the routes are cached (`route:cache` caches them with the rest).

The alias (`billing`) is the module name in snake case: `OrderHistory` gets `config('order_history.*')` and `__('order_history::…')`.

To change a convention, override the method or property:

```php
protected string $apiPrefix = 'api/v1';            // prefix of routes/api.php

public function moduleAlias(): string              // config key and translation namespace
{
    return 'payments';
}

protected function bootRoutes(): void              // registerConfig(), bootMigrations(), bootTranslations() too
{
    Route::middleware(['api', 'auth:sanctum'])->prefix('api/billing')->group($this->modulePath('routes/api.php'));
}
```

The module root is the parent of the directory the provider lives in (`modules/Billing` for `modules/Billing/Providers/BillingServiceProvider.php`). A provider kept elsewhere overrides `modulePath()`.

### Dependencies

`$dependsOn` lists the modules this module uses. Today it decides the registration order and shows in `modules:list`. With `modules:check` (next releases) it becomes the rule: using a module that is not declared, or anything outside its public folders, is a violation, and so is a cycle.

## Automatic registration

Every directory in `modules/` is a module. Its provider, `Modules\{Name}\Providers\{Name}ServiceProvider`, is registered when the application starts. Modules come after the modules they depend on, otherwise by name. Unknown dependencies are ignored at runtime, and a cycle does not stop the application: `modules:list` and `modules:check` are where you see them.

A directory without that provider is still listed by `modules:list` ("not found"), but nothing is registered for it.

To register providers yourself (for example in `bootstrap/providers.php`), turn it off:

```php
// config/boundaries.php
'auto_register' => false,
```

## Configuration

```php
return [
    'path' => 'modules',                            // relative to the application root, or absolute
    'namespace' => 'Modules',                       // modules/Billing → Modules\Billing
    'auto_register' => true,
    'public' => ['Contracts', 'Events', 'Data'],    // public API folders, created by make:module
];
```

To keep modules inside `app/`, set `'path' => 'app/Modules'` and `'namespace' => 'App\\Modules'`: the existing `"App\\": "app/"` entry already loads them, so `make:module` does not touch `composer.json`.

## Stubs

`php artisan vendor:publish --tag=boundaries-stubs` copies the stubs to `stubs/boundaries/module`: `provider.stub`, `routes-web.stub`, `routes-api.stub`, `config.stub`. `make:module` uses a published stub over its own. Placeholders: `{{ name }}` (`OrderHistory`), `{{ namespace }}` (`Modules\OrderHistory`), `{{ alias }}` (`order_history`), `{{ slug }}` (`order-history`).

## Roadmap

1. Module skeleton: `make:module`, module service provider, automatic registration, `modules:list`. **This release.**
2. Module manifest: public folders and dependencies per module.
3. `modules:check`: static analysis of references between modules (internals, undeclared dependencies, cycles) with CI-friendly output.
4. A test assertion (`assertModuleBoundaries()`) and a baseline for existing violations.
5. Integration with [toshkq93/laravel-layers](https://github.com/toshkq93/laravel-layers): `make:layers Invoice --module=Billing`.
6. Communication between modules through events and contracts; dependency graph.

## Testing

```bash
composer test       # PHPUnit
composer analyse    # PHPStan + Larastan, level max
composer format     # Pint
```

## License

MIT. See [LICENSE](LICENSE).
