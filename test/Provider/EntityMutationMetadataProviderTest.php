<?php
namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\UnitOfWork;
use Hostnet\Component\EntityTracker\Mocked\MockEntity;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 * @covers Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider
 */
class EntityMutationMetadataProviderTest extends \PHPUnit_Framework_TestCase
{
    private $reader;
    private $em;
    private $uow;

    public function setUp()
    {
        $this->reader = new AnnotationReader();
        $this->em     = $this->getMock('Doctrine\ORM\EntityManagerInterface');
        $this->uow    = $this
            ->getMockBuilder('Doctrine\ORM\UnitOfWork')
            ->disableOriginalConstructor()
            ->getMock();

        $this->em
            ->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($this->uow);
    }

    public function testCreateOriginalEntity()
    {
        $entity   = new MockEntity();
        $metadata = $this->buildMetadata($entity, ['id'], ['parent']);

        $metadata
            ->expects($this->exactly(2))
            ->method('setFieldValue')
            ->withConsecutive([$entity, 'id', $this->anything()], [$entity, 'parent', $this->anything()]);

        $this->uow
            ->expects($this->once())
            ->method('getOriginalEntityData')
            ->willReturn(['id' => 1, 'parent' => 1]);

        $this->em
            ->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $provider = new EntityMutationMetadataProvider($this->reader);
        $provider->createOriginalEntity($this->em, $entity);
    }

    public function testCreateOriginalEntityEmpty()
    {
        $entity   = new MockEntity();
        $metadata = $this->buildMetadata($entity, ['id'], ['parent']);

        $this->uow
            ->expects($this->once())
            ->method('getOriginalEntityData')
            ->willReturn([]);

        $this->em
            ->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $provider = new EntityMutationMetadataProvider($this->reader);
        $this->assertEquals(null, $provider->createOriginalEntity($this->em, $entity));
    }

    /**
     * @dataProvider getMutatedFieldsProvider
     */
    public function testGetMutatedFieldsId($entity_id, $original_id, $expected_changes)
    {
        $entity       = new MockEntity();
        $entity->id   = $entity_id;
        $original     = new MockEntity();
        $original->id = $original_id;
        $metadata     = $this->buildMetadata($entity, ['id'], []);

        $this->em
            ->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $metadata
            ->expects($this->exactly(2))
            ->method('getFieldValue')
            ->withConsecutive([$entity, 'id'], [$original, 'id'])
            ->willReturnOnConsecutiveCalls($entity->id, $original->id);

        $provider = new EntityMutationMetadataProvider($this->reader);
        $this->assertCount($expected_changes, $provider->getMutatedFields($this->em, $entity, $original));
    }

    /**
     * @dataProvider getMutatedFieldsProvider
     */
    public function testGetMutatedFieldsParent($entity_id, $original_id, $expected_changes)
    {
        $entity               = new MockEntity();
        $entity->parent       = new \stdClass();
        $entity->parent->id   = $entity_id;
        $original             = new MockEntity();
        $original->parent     = new \stdClass();
        $original->parent->id = $original_id;
        $metadata             = $this->buildMetadata($entity, [], ['parent']);

        $this->em
            ->expects($this->exactly(2))
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $metadata
            ->expects($this->once())
            ->method('getAssociationTargetClass');

        $metadata
            ->expects($this->exactly(2))
            ->method('getFieldValue')
            ->willReturnOnConsecutiveCalls($entity->parent, $original->parent);

        $metadata
            ->expects($this->exactly(2))
            ->method('getIdentifierValues')
            ->willReturnOnConsecutiveCalls([$entity->parent->id], [$original->parent->id]);


        $provider = new EntityMutationMetadataProvider($this->reader);
        $this->assertCount($expected_changes, $provider->getMutatedFields($this->em, $entity, $original));
    }

    /**
     * @return array[]
     */
    public function getMutatedFieldsProvider()
    {
        // testing with objects as doctrine allows an @ORM\Id on Foreign Keys
        $std1 = new \stdClass();
        $std1->id = 1;
        $std2 = new \stdClass();
        $std2->id = 2;
        $std3 = new \stdClass();
        $std3->id = 2;

        return [
            [1, 1, 0],
            [1, 2, 1],
            [$std1, $std1, 0],
            [$std2, $std2, 0],
            [$std2, $std3, 1],
            [$std1, $std2, 1],
            [$std2, $std1, 1]
        ];
    }

    public function testGetMutatedFieldsEmpty()
    {
        $entity   = new MockEntity();
        $original = null;
        $metadata = $this->buildMetadata($entity, [], ['parent']);

        $this->em
            ->expects($this->exactly(1))
            ->method('getClassMetadata')
            ->willReturn($metadata);


        $provider = new EntityMutationMetadataProvider($this->reader);
        $this->assertEquals(["parent"], $provider->getMutatedFields($this->em, $entity, $original));
    }

    /**
     * @dataProvider getFullChangeSetProvider
     */
    public function testGetFullChangeSet($changes, $inserts, $expected_size)
    {
        $this->uow
            ->expects($this->once())
            ->method('getIdentityMap')
            ->willReturn($changes);

        $this->uow
            ->expects($this->once())
            ->method('getScheduledEntityInsertions')
            ->willReturn($inserts);

        $provider = new EntityMutationMetadataProvider($this->reader);
        $this->assertCount($expected_size, $provider->getFullChangeSet($this->em));
    }

    /**
     * return array[]
     */
    public function getFullChangeSetProvider()
    {
        $class = 'Hostnet\Component\EntityTracker\Mocked\MockEntity';
        return [
            [[], [], 0],
            [[$class => [new MockEntity()]], [], 1],
            [[$class => [new MockEntity()]], [new MockEntity()], 1],
            [[$class => []], [new MockEntity()], 1],
            [[$class . 'Henk' => [new MockEntity()]], [new MockEntity()], 2],
        ];
    }

    public function testIsEntityManaged()
    {
        $entity = new MockEntity();

        $this->uow
            ->expects($this->any())
            ->method('getEntityState')
            ->with($entity)
            ->willReturnOnConsecutiveCalls(
                UnitOfWork::STATE_DETACHED,
                UnitOfWork::STATE_MANAGED,
                UnitOfWork::STATE_NEW,
                UnitOfWork::STATE_REMOVED
            );

        $provider = new EntityMutationMetadataProvider($this->reader);
        $this->assertFalse($provider->isEntityManaged($this->em, $entity));
        $this->assertTrue($provider->isEntityManaged($this->em, $entity));
        $this->assertFalse($provider->isEntityManaged($this->em, $entity));
        $this->assertFalse($provider->isEntityManaged($this->em, $entity));
    }

    /**
     * @param mixed    $entity
     * @param string[] $field_names
     * @param string[] $assoc_names
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function buildMetadata($entity, array $field_names, array $assoc_names)
    {
        $meta = $this
            ->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->setMethods([
                'getFieldNames',
                'getAssociationNames',
                'setFieldValue',
                'getFieldValue',
                'getAssociationTargetClass',
                'getIdentifierValues',
                'getReflectionClass'
            ])
            ->setConstructorArgs([get_class($entity)])
            ->getMock();

        $meta
            ->expects($this->any())
            ->method('getFieldNames')
            ->willReturn($field_names);

        $meta
            ->expects($this->any())
            ->method('getAssociationNames')
            ->willReturn($assoc_names);

        return $meta;
    }
}
