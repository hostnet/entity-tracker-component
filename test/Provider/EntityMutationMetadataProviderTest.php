<?php
namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Hostnet\Component\DatabaseTest\MysqlPersistentConnection;
use Hostnet\Component\EntityTracker\Provider\Entity\Node;
use Hostnet\Component\EntityTracker\Provider\Entity\Painting;

/**
 * @covers \Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider
 */
class EntityMutationMetadataProviderTest extends \PHPUnit_Framework_TestCase
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
