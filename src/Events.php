<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker;

final class Events
{
    /**
     * Thrown when the Tracked attribute (or a derived attribute) is found on the entity
     *
     * @var string
     */
    public const ENTITY_CHANGED = 'entityChanged';
}
