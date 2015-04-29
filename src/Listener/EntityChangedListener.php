<?php
namespace Hostnet\Component\EntityTracker\Listener;

use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Proxy\Proxy;
use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;
use Hostnet\Component\EntityTracker\Events;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Listener for entities that use the Tracked Annotation.
 *
 * This listener will fire an "Events::entityChanged" event
 * per entity that is changed.
 * @author Yannick de Lange <ydelange@hostnet.nl>
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
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
     * @var string[]
     */
    private $annotations = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityAnnotationMetadataProvider $meta_annotation_provider
     * @param EntityMutationMetadataProvider $meta_mutation_provider
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityAnnotationMetadataProvider $meta_annotation_provider,
        EntityMutationMetadataProvider   $meta_mutation_provider,
        LoggerInterface                  $logger = null
    ) {
        $this->meta_annotation_provider = $meta_annotation_provider;
        $this->meta_mutation_provider   = $meta_mutation_provider;
        $this->logger = $logger ? : new NullLogger();
    }

    /**
     * Pre Flush event callback
     *
     * Checks if the entity contains an @Tracked (or derived)
     * annotation. If so, it will attempt to calculate changes
     * made and dispatch 'Events::entityChanged' with the current
     * and original entity states. Note that the original entity
     * is not managed.
     *
     * @param PreFlushEventArgs $event
     */
    public function preFlush(PreFlushEventArgs $event)
    {
        $em      = $event->getEntityManager();
        $changes = $this->meta_mutation_provider->getFullChangeSet($em);

        foreach ($changes as $updates) {
            if (empty($updates)) {
                continue;
            }

            if (false === $this->meta_annotation_provider->isTracked($em, current($updates))) {
                continue;
            }

            foreach ($updates as $entity) {
                if (!$this->meta_mutation_provider->isEntityManaged($em, $entity) || $entity instanceof Proxy) {
                    continue;
                }

                $original       = $this->meta_mutation_provider->createOriginalEntity($em, $entity);
                $mutated_fields = $this->meta_mutation_provider->getMutatedFields($em, $entity, $original);

                if (!empty($mutated_fields)) {
                    $this->logger->info(
                        'Going to notify a change to {entity_class}, which has {mutated_fields}',
                        ['entity_class' => get_class($entity), 'mutated_fields' => $mutated_fields]
                    );
                    $em->getEventManager()->dispatchEvent(
                        Events::entityChanged,
                        new EntityChangedEvent($em, $entity, $original, $mutated_fields)
                    );
                }
            }
        }
    }
}
