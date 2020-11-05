<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Event;

use Hostnet\Component\EntityTracker\Mocked\MockEntity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\EntityTracker\Event\EntityChangedEvent
 */
class EntityChangedEventTest extends TestCase
{
    public function testAll()
    {
        $em              = $this->createMock('Doctrine\ORM\EntityManagerInterface');
        $current_entity  = new MockEntity();
        $original_entity = new MockEntity();
        $mutated_fields  = ['test'];
        $event           = new EntityChangedEvent($em, $current_entity, $original_entity, $mutated_fields);
        $this->assertEquals($em, $event->getEntityManager());
        $this->assertEquals($current_entity, $event->getCurrentEntity());
        $this->assertEquals($original_entity, $event->getOriginalEntity());
        $this->assertEquals($mutated_fields, $event->getMutatedFields());
    }
}
