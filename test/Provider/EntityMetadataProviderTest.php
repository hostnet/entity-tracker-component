<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\ORM\Mapping\ClassMetadata;
use Hostnet\Component\EntityTracker\Attributes\Tracked;
use Hostnet\Component\EntityTracker\Mocked\ProxiedTrackedAttributeEntity;
use Hostnet\Component\EntityTracker\Mocked\TrackedAttributeEntity;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Hostnet\Component\EntityTracker\Provider\EntityMetadataProvider
 */
class EntityMetadataProviderTest extends TestCase
{
    use ProphecyTrait;

    private $provider;
    private $em;

    public function setUp(): void
    {
        $this->provider = new EntityMetadataProvider();
        $this->em       = $this->createMock('Doctrine\ORM\EntityManagerInterface');
    }

    /**
     * @dataProvider getAttributeFromEntityProvider
     */
    public function testGetAttributeFromEntity(mixed $entity, bool $has, ?string $proxied_class): void
    {
        if ($proxied_class) {
            $metadata = $this->prophesize(ClassMetadata::class);
            $metadata->getName()->willReturn($proxied_class)->shouldBeCalled();

            $this->em
                ->expects($this->once())
                ->method('getClassMetadata')
                ->with(get_class($entity))
                ->willReturn($metadata->reveal());
        } else {
            $this->em
                ->expects($this->never())
                ->method('getClassMetadata');
        }

        $result = $this->provider->getAttributeFromEntity(Tracked::class, $this->em, $entity);

        if ($has) {
            $this->assertEquals(Tracked::class, get_class($result));
        } else {
            $this->assertNull($result);
        }
    }

    /**
     * @return array
     */
    public function getAttributeFromEntityProvider(): iterable
    {
        return [
            [new \stdClass(), false, null],
            [new TrackedAttributeEntity(),  true, null],
            [new ProxiedTrackedAttributeEntity(),  true, TrackedAttributeEntity::class],
        ];
    }
}
