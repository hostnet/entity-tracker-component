<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Proxy\Proxy;
use Hostnet\Component\EntityTracker\Attributes\Tracked;
use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;
use Hostnet\Component\EntityTracker\Events;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Listener for entities that use the Tracked Annotation or attribute.
 *
 * This listener will fire an "Events::ENTITY_CHANGED" event
 * per entity that is changed.
 */
class EntityChangedListener
{
    /**
     * @var EntityAnnotationMetadataProvider
     */
    private $meta_annotation_provider;

    /**
     * @var EntityMutationMetadataProvider
     */
    private $meta_mutation_provider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Caches the class names to prevent iterating over attribute and annotations again on the next flush.
     */
    private array $is_tracked_cache = [];

    /**
     * @param EntityAnnotationMetadataProvider $meta_annotation_provider
     * @param EntityMutationMetadataProvider $meta_mutation_provider
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityAnnotationMetadataProvider $meta_annotation_provider,
        EntityMutationMetadataProvider $meta_mutation_provider,
        LoggerInterface $logger = null
    ) {
        $this->meta_annotation_provider = $meta_annotation_provider;
        $this->meta_mutation_provider   = $meta_mutation_provider;
        $this->logger                   = $logger ? : new NullLogger();
    }

    private function hasTrackedAttribute($entity): bool
    {
        $reflection = new \ReflectionClass($entity);
        $attributes = $reflection->getAttributes(Tracked::class, \ReflectionAttribute::IS_INSTANCEOF);

        return !empty($attributes);
    }

    private function isTracked($em, $entity): bool
    {
        $class = get_class($entity);
        if (array_key_exists($class, $this->is_tracked_cache)) {
            return $this->is_tracked_cache[$class];
        }

        $has_tracked_attribute = $this->hasTrackedAttribute($entity);
        if ($has_tracked_attribute) {
            $this->is_tracked_cache[$class] = true;

            return true;
        }

        $has_tracked_annotation = $this->meta_annotation_provider->isTracked($em, $entity);
        if ($has_tracked_annotation) {
            $this->is_tracked_cache[$class] = true;

            return true;
        }

        $this->is_tracked_cache[$class] = false;

        return false;
    }

    /**
     * Pre Flush event callback
     *
     * Checks if the entity contains an @Tracked (or derived)
     * annotation or attribute. If so, it will attempt to calculate changes
     * made and dispatch 'Events::ENTITY_CHANGED' with the current
     * and original entity states. Note that the original entity
     * is not managed.
     */
    public function preFlush(PreFlushEventArgs $event): void
    {
        $em      = $event->getEntityManager();
        $changes = $this->meta_mutation_provider->getFullChangeSet($em);

        foreach ($changes as $updates) {
            if (0 === count($updates)) {
                continue;
            }

            $entity = current($updates);
            if (!$this->isTracked($em, $entity)) {
                continue;
            }

            foreach ($updates as $entity) {
                if ($entity instanceof Proxy && !$entity->__isInitialized()) {
                    continue;
                }

                $original = $this->meta_mutation_provider->createOriginalEntity($em, $entity);

                $mutated_fields = $this->meta_mutation_provider->getMutatedFields($em, $entity, $original);

                if (null === $original || !empty($mutated_fields)) {
                    $this->logger->debug(
                        'Going to notify a change (preFlush) to {entity_class}, which has {mutated_fields}',
                        [
                            'entity_class'   => get_class($entity),
                            'mutated_fields' => $mutated_fields,
                        ]
                    );
                    $em->getEventManager()->dispatchEvent(
                        Events::ENTITY_CHANGED,
                        new EntityChangedEvent($em, $entity, $original, $mutated_fields)
                    );
                }
            }
        }
    }

    /**
     * @deprecated Will be removed when removing doctrine/annotations, will break entity-tracker-bundle otherwise.
     */
    public function prePersist(LifecycleEventArgs $event): void
    {
    }
}
