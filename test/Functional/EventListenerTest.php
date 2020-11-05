<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Functional;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\UnitOfWork;
use Hostnet\Component\DatabaseTest\MysqlPersistentConnection;
use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;
use Hostnet\Component\EntityTracker\Functional\Entity\Author;
use Hostnet\Component\EntityTracker\Functional\Entity\Book;
use Hostnet\Component\EntityTracker\Functional\Entity\Tool;
use Hostnet\Component\EntityTracker\Functional\Entity\Toolbox;
use Hostnet\Component\EntityTracker\Listener\EntityChangedListener;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class EventListenerTest extends TestCase
{
    /**
     * @var EntityChangedEvent[]
     */
    private $events;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var MysqlPersistentConnection;
     */
    private $connection;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->connection = new MysqlPersistentConnection();
        $params           = $this->connection->getConnectionParams();

        $config   = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/Entity'], true, null, null, false);
        $this->em = EntityManager::create($params, $config);

        $event_manager = $this->em->getEventManager();

        // create tables in the database
        $metadata    = $this->em->getMetadataFactory()->getAllMetadata();
        $schema_tool = new SchemaTool($this->em);
        $schema_tool->createSchema($metadata);

        // default doctrine annotation reader
        $annotation_reader = new AnnotationReader();

        // setup required providers
        $mutation_metadata_provider   = new EntityMutationMetadataProvider($annotation_reader);
        $annotation_metadata_provider = new EntityAnnotationMetadataProvider($annotation_reader);

        // pre flush event listener that uses the @Tracked annotation
        $entity_changed_listener = new EntityChangedListener(
            $annotation_metadata_provider,
            $mutation_metadata_provider
        );

        $event_manager->addEventListener('preFlush', $entity_changed_listener);
        $event_manager->addEventListener('prePersist', $entity_changed_listener);
        $event_manager->addEventListener('entityChanged', $this);

        $this->events = [];
    }

    public function entityChanged(EntityChangedEvent $event)
    {
        $this->events[] = [$event, (array) $event->getCurrentEntity()];
    }

    public function testNewAuthorNewBook()
    {
        $tolkien          = new Author('J. R. R. Tolkien');
        $tolkien->books[] = new Book('The Fellowship of the Ring');

        $this->em->persist($tolkien);
        $this->em->flush();

        self::assertCount(1, $this->events);
        self::assertSame($tolkien->books[0], $this->events[0][0]->getCurrentEntity());
    }

    public function testNewBookPersistAuthor()
    {
        $tolkien = new Author('J. R. R. Tolkien');
        $this->em->persist($tolkien);
        $this->em->flush();
        $this->events = [];

        $tolkien->books[] = new Book('The Two Towers');

        $this->em->persist($tolkien);
        $this->em->flush();

        self::assertCount(1, $this->events);
        self::assertSame($tolkien->books[0], $this->events[0][0]->getCurrentEntity());
    }

    public function testNewBook()
    {
        $tolkien = new Author('J. R. R. Tolkien');
        $this->em->persist($tolkien);
        $this->em->flush();
        $this->events = [];

        $tolkien->books[] = new Book('The Return of the King');
        $this->em->persist($tolkien);
        $this->em->flush();

        self::assertCount(1, $this->events);
        self::assertSame($tolkien->books[0], $this->events[0][0]->getCurrentEntity());
    }

    public function testNewBookPersistAuthorNewBook()
    {
        $tolkien = new Author('J. R. R. Tolkien');
        $this->em->persist($tolkien);
        $this->em->flush();
        $this->events = [];

        $tolkien->books[] = new Book('The Hobbit');
        $this->em->persist($tolkien);
        $tolkien->books[] = new Book('The Silmarillion');
        $this->em->persist($tolkien);
        $this->em->flush();

        self::assertCount(2, $this->events);
        self::assertTrue($tolkien->books->contains($this->events[0][0]->getCurrentEntity()));
        self::assertTrue($tolkien->books->contains($this->events[1][0]->getCurrentEntity()));
    }

    public function testNewBookPersistAuthorEditBook()
    {
        $tolkien          = new Author('J. R. R. Tolkien');
        $tolkien->books[] = new Book('Silmarillion');
        $this->em->persist($tolkien);
        $this->em->flush();
        $this->events = [];

        $tolkien->books[0]->title = 'The Silmarillion';
        $this->em->flush();

        self::assertCount(1, $this->events);
        self::assertSame('The Silmarillion', $this->events[0][0]->getCurrentEntity()->title);
        self::assertSame('Silmarillion', $this->events[0][0]->getOriginalEntity()->title);
    }

    public function testMutatedAssociations()
    {
        // Create new Toolbox with tools.
        $toolbox = new Toolbox(new Tool('pliers'), new Tool('hammer'));
        $this->em->persist($toolbox);
        $this->em->flush();

        self::assertSame(['id', 'tag'], $this->events[0][0]->getMutatedFields());

        // Add new Tool to the Toolbox.
        $this->events     = [];
        $toolbox->tools[] = new Tool('saw');

        $toolbox->tag = "Work don't play";
        $this->em->flush();
        self::assertSame(['tag'], $this->events[0][0]->getMutatedFields());

        // Remove a Tool from the Toolbox.
        $this->events = [];
        unset($toolbox->tools[2]->toolbox, $toolbox->tools[2]);

        $this->em->flush();
    }

    public function testPersistDetach()
    {
        $toolbox = new Toolbox();
        $this->em->persist($toolbox);
        $this->em->detach($toolbox);
        $this->em->flush();

        self::assertEmpty($this->events);
        self::assertEquals(UnitOfWork::STATE_NEW, $this->em->getUnitOfWork()->getEntityState($toolbox));
    }

    public function testCorrectValues()
    {
        $toolbox      = new Toolbox();
        $toolbox->tag = 'foobar';

        $this->em->persist($toolbox);

        $toolbox->tag = 'barbaz';

        $this->em->flush();

        self::assertSame(['id', 'tag'], $this->events[0][0]->getMutatedFields());
        self::assertEquals([
            'id'    => null,
            'tag'   => 'barbaz',
            'tools' => new ArrayCollection([]),
        ], $this->events[0][1]);
    }

    /**
     * Test "Persistence by reachability". Reachable entities in collections
     * are persisted by default if there is a cascade persist.
     *
     * The listener should also trigger on those.
     */
    public function testReachablePersist()
    {
        $tolkien = new Author('J. R. R. Tolkien');
        $this->em->persist($tolkien);
        $this->em->flush();
        $this->events = [];

        // Added book and do not call persist for any entities.
        $tolkien->books[] = new Book('The Return of the King');
        $this->em->flush();

        self::assertCount(1, $this->events);
    }
}
