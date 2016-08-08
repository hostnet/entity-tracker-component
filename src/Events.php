<?php
namespace Hostnet\Component\EntityTracker;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 */
final class Events
{
    //@codingStandardsIgnoreStart
    /**
     * @deprecated use Events::ENTITY_CHANGED instead.
     */
    const entityChanged = self::ENTITY_CHANGED;
    //@codingStandardsIgnoreEnd

    /**
     * Thrown when @Tracked (or derived) annotations are found on the entity
     *
     * @var string
     */
    const ENTITY_CHANGED = 'entityChanged';
}
