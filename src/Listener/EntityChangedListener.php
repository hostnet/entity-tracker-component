<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\Persistence\ObjectManager;
use Hostnet\Component\EntityTracker\Attributes\Tracked;
use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;
use Hostnet\Component\EntityTracker\Events;
use Hostnet\Component\EntityTracker\Provider\EntityMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Listener for entities that use the Tracked Annotation or attribute.
 *
 * This listener will fire an "Events::ENTITY_CHANGED" event
 * per entity that is changed.
 */
class EntityChangedListener
{
    public function __construct(
        private EntityMetadataProvider $meta_annotation_provider,
        private EntityMutationMetadataProvider $meta_mutation_provider,
        private ?LoggerInterface $logger = null,
        private CacheItemPoolInterface $is_tracked_cache = new ArrayAdapter()
    ) {
        $this->logger = $logger ? : new NullLogger();
    }

    private function isTracked(ObjectManager $em, mixed $entity): bool
    {
        $cache_key   = base64_encode('TRACKED-' . get_class($entity));
        $cached_item = $this->is_tracked_cache->getItem($cache_key);

        if ($cached_item->isHit()) {
            return $cached_item->get();
        }

        if (null !== $this->meta_annotation_provider->getAttributeFromEntity(Tracked::class, $em, $entity)) {
            return $this->save($cached_item, true);
        }

        if ($this->meta_annotation_provider->isTracked($em, $entity)) {
            return $this->save($cached_item, true);
        }

        return $this->save($cached_item, false);
    }

    private function save(CacheItemInterface $item, bool $value): bool
    {
        $item->set($value);
        $this->is_tracked_cache->save($item);

        return $value;
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
        $em      = $event->getObjectManager();
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
