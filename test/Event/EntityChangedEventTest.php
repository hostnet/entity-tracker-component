<?php
namespace Hostnet\Component\EntityTracker\Event;

use Hostnet\Component\EntityTracker\Mocked\MockEntity;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 */
class EntityChangedEventTest extends \PHPUnit_Framework_TestCase
{
    public function testAll()
    {
        $em              = $this->getMock('Doctrine\ORM\EntityManagerInterface');
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
