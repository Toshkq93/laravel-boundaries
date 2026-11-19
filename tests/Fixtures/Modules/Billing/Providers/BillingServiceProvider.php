<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Fixtures\Modules\Billing\Providers;

use Toshkq93\Boundaries\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected array $dependsOn = ['Catalog'];
}
