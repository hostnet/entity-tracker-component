<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Hostnet\Component\DatabaseTest\MysqlPersistentConnection;
use Hostnet\Component\EntityTracker\Provider\Entity\A;
use Hostnet\Component\EntityTracker\Provider\Entity\B;
use Hostnet\Component\EntityTracker\Provider\Entity\C;
use Hostnet\Component\EntityTracker\Provider\Entity\Gallery;
use Hostnet\Component\EntityTracker\Provider\Entity\Node;
use Hostnet\Component\EntityTracker\Provider\Entity\Painting;
use Hostnet\Component\EntityTracker\Provider\Entity\Visit;
use Hostnet\Component\EntityTracker\Provider\Entity\Visitor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider
 */
class EntityMutationMetadataProviderTest extends TestCase
{
    /**
     * @var MysqlPersistentConnection
     */
    private $connection;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var EntityMutationMetadataProvider
     */
    private $provider;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->connection = new MysqlPersistentConnection();
        $params           = $this->connection->getConnectionParams();

        $config   = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/Entity'], true, null, null, false);
        $this->em = EntityManager::create($params, $config);

        // create tables in the database
        $metadata    = $this->em->getMetadataFactory()->getAllMetadata();
        $schema_tool = new SchemaTool($this->em);
        $schema_tool->createSchema($metadata);

        $this->provider = new EntityMutationMetadataProvider(new AnnotationReader());
    }

    public function testChanges()
    {
        $sunflowers = new Painting('Sunflowers');
        $this->em->persist($sunflowers);
        $this->em->flush();
        $sunflowers->name = 'The Sunflowers';
        self::assertCount(1, $this->provider->getFullChangeSet($this->em));
    }

    public function testChangesNewEntity()
    {
        $gallery = new Gallery('foobar street 10');

        $v1 = $gallery->addVisitor('henk');
        $this->em->persist($gallery);

        $v2 = $gallery->addVisitor('hans');

        self::assertEquals([
            Gallery::class => [$gallery],
            Visitor::class => [$v2, $v1],
        ], $this->provider->getFullChangeSet($this->em));
    }

    public function testChangesNewEntityFlushed()
    {
        $gallery = new Gallery('foobar street 10');

        $v1 = $gallery->addVisitor('henk');
        $this->em->persist($gallery);
        $this->em->flush();

        $v2 = $gallery->addVisitor('hans');

        self::assertEquals([
            Gallery::class => [$gallery],
            Visitor::class => [$v1, $v2],
        ], $this->provider->getFullChangeSet($this->em));
    }

    public function testChangesNewEntityFlushedBadOrder()
    {
        $a  = new A();
        $b1 = new B();
        $b2 = new B();
        $c  = new C();

        $a->bees->add($b1);
        $b1->a = $a;

        $this->em->persist($a);
        $this->em->persist($b1);
        $this->em->flush();

        $a->bees->add($b2);
        $b2->a = $a;

        $b2->cees->add($c);
        $c->b = $b2;

        $change_set = $this->provider->getFullChangeSet($this->em);

        self::assertCount(1, $change_set[A::class]);
        self::assertCount(2, $change_set[B::class]);
        self::assertCount(1, $change_set[C::class]);
    }

    public function testChangesNewEntityOneToOne()
    {
        $root         = new Node('root');
        $root->mirror = $mirror = new Node('mirror');

        $this->em->persist($root);

        self::assertEquals([Node::class => [$root]], $this->provider->getFullChangeSet($this->em));
    }

    public function testCreateOriginalEntity()
    {
        $tall_ship = new Painting('Tall Ship');
        self::assertNull($this->provider->createOriginalEntity($this->em, $tall_ship));

        $this->em->persist($tall_ship);
        $this->em->flush();

        $tall_ship->name = 'Seven Provinces';
        $original        = $this->provider->createOriginalEntity($this->em, $tall_ship);
        self::assertSame('Tall Ship', $original->name);
    }

    public function testCreateOriginalEntityIdentity()
    {
        $gallery = new Gallery('Riverstreet 12');
        $gallery->addVisitor('Foo de Bar');

        $this->em->persist($gallery);
        $this->em->flush();

        $gallery->addVisitor('Bar Baz');

        $this->em->getUnitOfWork()->computeChangeSets();
        $this->em->flush();

        $original = $this->provider->createOriginalEntity($this->em, $gallery);
        self::assertSame($gallery->getId(), $original->getId());
    }

    public function testCreateOriginalEntityNonOwning()
    {
        $visitor = new Visitor('Henk de Vries');
        $visitor->addVisit(new Visit(new \DateTime('2016-10-10 10:10:10'), $visitor));

        $this->em->persist($visitor);
        $this->em->flush();

        $visitor->setName('foobar');

        $this->em->flush();

        $original = $this->provider->createOriginalEntity($this->em, $visitor);

        self::assertSame($visitor->getName(), $original->getName());
        self::assertSame($visitor->getVisits(), $original->getVisits());
    }

    /**
     * @depends testCreateOriginalEntity
     */
    public function testGetMutatedFields()
    {
        // Simple new entity
        $venus = new Painting('The birth of Aphrodite');
        self::assertSame(['id', 'name'], $this->provider->getMutatedFields($this->em, $venus, null));
        self::assertSame([], $this->provider->getMutatedFields($this->em, $venus, $venus));

        // Complex entity
        $start       = new Node('start');
        $start->id   = 1;
        $end         = new Node('end');
        $end->id     = 10;
        $end->parent = $start;

        self::assertSame(['id', 'name', 'parent', 'mirror'], $this->provider->getMutatedFields($this->em, $end, null));
        self::assertSame([], $this->provider->getMutatedFields($this->em, $end, $end));

        $original       = clone $end;
        $middle         = new Node('middle');
        $middle->id     = 5;
        $end->parent    = $middle;
        $middle->parent = $start;
        self::assertSame(['parent'], $this->provider->getMutatedFields($this->em, $end, $original));
    }

    public function testIsEntityManaged()
    {
        $apples = new Painting('Apples of Cezanne');
        self::assertFalse($this->provider->isEntityManaged($this->em, $apples));

        $this->em->persist($apples);
        self::assertTrue($this->provider->isEntityManaged($this->em, $apples));
    }
}
