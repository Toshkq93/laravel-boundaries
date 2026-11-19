<?php

declare(strict_types=1);

namespace Toshkq93\Boundaries\Modules;

use Toshkq93\Boundaries\ModuleServiceProvider;

/**
 * A module found in the modules directory.
 */
final class Module
{
    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly string $path,
        public readonly ?ModuleServiceProvider $provider,
    ) {}

    /**
     * Class name of the module's service provider by convention
     * (Modules\Billing\Providers\BillingServiceProvider).
     */
    public function providerClass(): string
    {
        return self::providerClassFor($this->namespace, $this->name);
    }

    public static function providerClassFor(string $namespace, string $name): string
    {
        return $namespace.'\\Providers\\'.$name.'ServiceProvider';
    }

    /**
     * Modules this module declares in `$dependsOn` of its provider.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return $this->provider?->dependsOn() ?? [];
    }
}
