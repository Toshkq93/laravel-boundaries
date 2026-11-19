<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Tests\Fixtures\Modules\Shipping\Providers;

use Toshkq93\Boundaries\ModuleServiceProvider;

class ShippingServiceProvider extends ModuleServiceProvider
{
    protected array $dependsOn = ['Billing', 'Warehouse'];
}
