<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Hostnet\Component\EntityTracker\Mocked\MockEntity;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider
 */
class EntityAnnotationMetadataProviderTest extends TestCase
{
    private $reader;
    private $provider;
    private $em;

    public function setUp(): void
    {
        $this->reader   = new AnnotationReader();
        $this->provider = new EntityAnnotationMetadataProvider($this->reader);
        $this->em       = $this->createMock('Doctrine\ORM\EntityManagerInterface');
    }

    /**
     * @dataProvider isTrackedProvider
     */
    public function testIsTracked($entity, $expected_output): void
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

    public function isTrackedProvider(): iterable
    {
        return [
            [new \stdClass(), false],
            [new MockEntity(), true],
        ];
    }

    /**
     * @dataProvider getAnnotationFromEntityProvider
     * @param mixed $entity
     * @param mixed $annotation
     * @param bool  $has
     */
    public function testGetAnnotationFromEntity($entity, $annotation, $has): void
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
    public function getAnnotationFromEntityProvider(): iterable
    {
        return [
            [new \stdClass(), null, false],
            [new MockEntity(), new Tracked(), true],
        ];
    }

    /**
     * @param mixed    $entity
     * @param string[] $field_names
     * @param string[] $assoc_names
     * @return MockObject
     */
    private function buildMetadata($entity, array $field_names, array $assoc_names): MockObject
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
                'getReflectionClass',
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
