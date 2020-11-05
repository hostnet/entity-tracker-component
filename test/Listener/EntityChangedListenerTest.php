<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Listener;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Proxy\Proxy;
use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;
use Hostnet\Component\EntityTracker\Events;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

/**
 * Listener for the Entities that use the Mutation Annotation.
 *
 * @covers \Hostnet\Component\EntityTracker\Listener\EntityChangedListener
 */
class EntityChangedListenerTest extends TestCase
{
    private $meta_annotation_provider;
    private $meta_mutation_provider;
    private $event_manager;
    private $em;
    private $logger;
    private $event;

    /**
     * @var EntityChangedListener
     */
    private $listener;

    protected function setUp()
    {
        $this->meta_annotation_provider = $this->prophesize(EntityAnnotationMetadataProvider::class);
        $this->meta_mutation_provider   = $this->prophesize(EntityMutationMetadataProvider::class);
        $this->em                       = $this->prophesize(EntityManagerInterface::class);
        $this->event                    = $this->prophesize(PreFlushEventArgs::class);
        $this->event_manager            = $this->prophesize(EventManager::class);
        $this->logger                   = $this->prophesize(LoggerInterface::class);

        $this->em->getEventManager()->willReturn($this->event_manager->reveal());

        $this->listener = new EntityChangedListener(
            $this->meta_annotation_provider->reveal(),
            $this->meta_mutation_provider->reveal(),
            $this->logger->reveal()
        );
    }

    public function testPreFlushNoAnnotation()
    {
        $entity = new \stdClass();
        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity));
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldNotBeCalled();
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity)->willReturn(false);
        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushEmptyChanges()
    {
        $this->meta_mutation_provider->getFullChangeSet($this->em->reveal())->willReturn([[]]);
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldNotBeCalled();
        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushUnmanaged()
    {
        $entity = new \stdClass();

        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity));
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity)->willReturn(true);
        $this->logger->debug(Argument::cetera())->shouldBeCalled();
        $this->meta_mutation_provider->isEntityManaged($this->em->reveal(), $entity)->willReturn(true);
        $this->meta_mutation_provider->createOriginalEntity($this->em->reveal(), $entity)->willReturn(null);
        $this->meta_mutation_provider->getMutatedFields($this->em->reveal(), $entity, null)->willReturn(['id']);
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldBeCalledTimes(1);

        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushWithoutMutatedFields()
    {
        $entity   = new \stdClass();
        $original = new \stdClass();

        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity));
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldNotBeCalled();
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity)->willReturn(true);
        $this->meta_mutation_provider->isEntityManaged($this->em->reveal(), $entity)->willReturn(true);
        $this->meta_mutation_provider->createOriginalEntity($this->em->reveal(), $entity)->willReturn($original);
        $this->meta_mutation_provider->getMutatedFields($this->em->reveal(), $entity, $entity)->willReturn([]);

        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushWithMutatedFields()
    {
        $entity       = new \stdClass();
        $entity->id   = 1;
        $original     = new \stdClass();
        $original->id = 0;

        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity));
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity)->willReturn(true);
        $this->logger->debug(Argument::cetera())->shouldBeCalled();
        $this->meta_mutation_provider->isEntityManaged($this->em->reveal(), $entity)->willReturn(true);
        $this->meta_mutation_provider->createOriginalEntity($this->em->reveal(), $entity)->willReturn($original);
        $this->meta_mutation_provider->getMutatedFields($this->em->reveal(), $entity, $original)->willReturn(['id']);
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldBeCalledTimes(1);

        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushWithNewEntity()
    {
        $entity = new \stdClass();

        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity));
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity)->willReturn(true);
        $this->logger->debug(Argument::cetera())->shouldBeCalled();
        $this->meta_mutation_provider->isEntityManaged($this->em->reveal(), $entity)->willReturn(true);
        $this->meta_mutation_provider->createOriginalEntity($this->em->reveal(), $entity)->willReturn(null);
        $this->meta_mutation_provider->getMutatedFields($this->em->reveal(), $entity, null)->willReturn(['id']);
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldBeCalledTimes(1);

        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushWithProxy()
    {
        $entity = $this->prophesize(Proxy::class)->reveal();
        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity));
        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldNotBeCalled();
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity)->willReturn(true);
        $this->meta_mutation_provider->isEntityManaged($this->em->reveal(), $entity)->willReturn(true);
        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPreFlushWithInitializedProxy()
    {
        $original     = new \stdClass();
        $original->id = 0;

        $entity = $this->prophesize(Proxy::class);
        $this->meta_mutation_provider
            ->getFullChangeSet($this->em->reveal())
            ->willReturn($this->genericEntityDataProvider($entity->reveal()));
        $this->meta_annotation_provider->isTracked($this->em->reveal(), $entity->reveal())->willReturn(true);
        $this->meta_mutation_provider->isEntityManaged($this->em->reveal(), $entity->reveal())->willReturn(true);
        $entity->__isInitialized()->willReturn(true);
        $this->logger->debug(Argument::cetera())->shouldBeCalled();
        $this->meta_mutation_provider->createOriginalEntity($this->em->reveal(), $entity)->willReturn($original);
        $this->meta_mutation_provider
            ->getMutatedFields($this->em->reveal(), $entity->reveal(), $original)
            ->willReturn(['id']);

        $this->event_manager
            ->dispatchEvent(Events::ENTITY_CHANGED, Argument::type(EntityChangedEvent::class))
            ->shouldBeCalledTimes(1);

        $this->listener->preFlush(new PreFlushEventArgs($this->em->reveal()));
    }

    public function testPrePersist()
    {
        $this->listener->prePersist(new LifecycleEventArgs(new \stdClass(), $this->em->reveal()));
    }

    /**
     * @param  mixed $entity
     * @return array[]
     */
    private function genericEntityDataProvider($entity)
    {
        return [
            get_class($entity) => [$entity],
        ];
    }
}
