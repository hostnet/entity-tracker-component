<?php
/**
 * @copyright 2026-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Mocked;

use Doctrine\Persistence\Proxy;

class ProxiedTrackedAttributeEntity extends TrackedAttributeEntity implements Proxy
{
    public function __load(): void
    {
    }

    public function __isInitialized(): void
    {
    }
}
