<?php
namespace Hostnet\Component\EntityTracker;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 */
final class Events
{
    /**
     * Thrown when @Tracked (or derived) annotations are found on the entity
     *
     * @var string
     */
    const entityChanged = 'entityChanged';
}
