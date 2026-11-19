# Changelog

All notable changes to this package are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `make:module Billing`: creates `modules/Billing` with `Providers/BillingServiceProvider.php`, `routes/web.php`, `routes/api.php`, `config/billing.php`, `database/migrations`, `lang` and the public API folders (`Contracts`, `Events`, `Data`). The first run adds `"Modules\\": "modules/"` to the PSR-4 autoload of `composer.json`, leaving the rest of the file as it was, and runs `composer dump-autoload`. It skips `composer.json` when an existing entry already loads the namespace, and warns when the namespace is mapped to another directory. `--force` writes the files of an existing module again. Published stubs (`stubs/boundaries/module`) win over the package's.
- `ModuleServiceProvider`, the base provider of a module: merges `config/*.php` (`config/billing.php` as `billing`, other files as `billing.<file>`), loads `routes/web.php` (`web` group) and `routes/api.php` (`api` group, `/api` prefix, `$apiPrefix`), migrations from `database/migrations` and translations from `lang` (`billing::` namespace and JSON). `$dependsOn` lists the modules the module uses. `moduleName()`, `moduleAlias()` and `modulePath()` can be overridden.
- Automatic registration of module providers (`Modules\{Name}\Providers\{Name}ServiceProvider`), with every module after the modules it depends on. `boundaries.auto_register` turns it off.
- `modules:list`: modules with namespace, path, provider and dependencies, flagging missing providers and dependencies; `--json` for scripts.
- `Modules\ModuleRegistry` and `Modules\Module` to read the modules from code.
- Config `config/boundaries.php`: `path`, `namespace`, `auto_register`, `public`.
- CI: PHP 8.2–8.4 × Laravel 11–13 × lowest/stable dependencies; PHPStan + Larastan at level max and Pint.
