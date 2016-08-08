<?php
namespace Hostnet\Component\EntityTracker\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Hostnet\Component\EntityTracker\Mocked\MockEntity;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 * @covers Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider
 */
class EntityAnnotationMetadataProviderTest extends \PHPUnit_Framework_TestCase
{
    private $reader;
    private $provider;
    private $em;

    public function setUp()
    {
        $this->reader   = new AnnotationReader();
        $this->provider = new EntityAnnotationMetadataProvider($this->reader);
        $this->em       = $this->createMock('Doctrine\ORM\EntityManagerInterface');
    }

    /**
     * @dataProvider isTrackedProvider
     */
    public function testIsTracked($entity, $expected_output)
    {
        $class      = get_class($entity);
        $reflection = new \ReflectionClass($class);
        $metadata   = $this->buildMetadata($entity, [], []);
        $metadata
            ->expects($this->once())
            ->method('getReflectionClass')
            ->willReturn($reflection);

        $this->em
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->willReturn($metadata);

        $this->assertEquals(
            $expected_output,
            $this->provider->isTracked($this->em, $entity)
        );
    }

    /**
     * @return array[]
     */
    public function isTrackedProvider()
    {
        return [
            [new \stdClass(), false],
            [new MockEntity(), true]
        ];
    }

    /**
     * @dataProvider getAnnotationFromEntityProvider
     * @param mixed $entity
     * @param mixed $annotation
     * @param bool  $has
     */
    public function testGetAnnotationFromEntity($entity, $annotation, $has)
    {
        $class    = get_class($entity);
        $metadata = $this
            ->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadataInfo')
            ->disableOriginalConstructor()
            ->getMock();

        $metadata
            ->expects($this->once())
            ->method('getReflectionClass')
            ->willReturn(new \ReflectionClass($entity));

        $this->em
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->willReturn($metadata);

        $result = $this->provider->getAnnotationFromEntity($this->em, $entity, $annotation);

        if ($has) {
            $this->assertEquals($annotation, $result);
        } else {
            $this->assertNull($result);
        }
    }

    /**
     * @return array
     */
    public function getAnnotationFromEntityProvider()
    {
        return [
            [new \stdClass(), null, false],
            [new MockEntity(), new Tracked(), true]
        ];
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
