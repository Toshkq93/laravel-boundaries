<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modules directory and namespace
    |--------------------------------------------------------------------------
    |
    | Every subdirectory of `path` is a module: modules/Billing holds the
    | classes of the `Modules\Billing` namespace. The path is relative to the
    | application root (an absolute path works too). make:module adds the
    | matching PSR-4 entry ("Modules\\": "modules/") to composer.json.
    |
    */

    'path' => 'modules',

    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Automatic registration
    |--------------------------------------------------------------------------
    |
    | Register the service provider of every module
    | ({namespace}\Billing\Providers\BillingServiceProvider) without listing
    | it in bootstrap/providers.php. Modules are registered after the modules
    | they depend on ($dependsOn).
    |
    */

    'auto_register' => true,

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    |
    | Folders of a module that other modules may use. Everything else is
    | internal to the module. make:module creates these folders.
    |
    */

    'public' => ['Contracts', 'Events', 'Data'],

];
