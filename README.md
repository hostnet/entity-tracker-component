README
======

 - [What is the Entity Tracker?](#what-is-the-entity-tracker)
 - [Requirements](#requirements)
 - [Installation](#installation)

### Documentation
   - [How does it work?](#how-does-it-work)
   - [Setup](#setup)
     - [Registering the Events](#registering-the-events)
     - [Creating the Listener](#creating-the-listener)
     - [Creating an Interface for the Entity](#creating-an-interface-for-the-entity)
     - [Registering the Annotation on the Entity](#registering-the-annotation-on-the-entity)
     - [What's Next?](#whats-next)
     - [Extending the Tracker Annotation](#extending-the-tracker-annotation)
   - [Extending the Tracker Annotation](#extending-the-tracker-annotation)
     - [Example Annotation](#example-annotation)
     - [Custom Annotation Resolvers](#custom-annotation-resolvers)
     - [Custom entityChanged Listener](#custom-entitychanged-listener)

What is the Entity Tracker?
---------------------------
The Entity Tracker Component is a library used to track changes within an Entity during a flush of the EntityManager. This makes it possible to do all sorts of things you want to automated during the `preFlush` event.

Entities become tracked when you implement the `@Tracked` annotation or a sub-class of `@Tracked`. You have total control over what happens next and which events you will use to listen to the `entityChanged` event.

Let's say that every time you flush your User, you want to set when it was updated. By default, you would have to call `$user->setUpdatedAt()` manually or create a custom listener on preFlush that sets the updated at timestamp. Both are a lot of extra work and you have to write extra code to determine changes. Listening to preFlush will always trigger your listener and you don't want to make a huge if statement nor create a listener for each Entity.

Requirements
------------
The tracker component requires at least php 5.4 and runs on Doctrine2. For specific requirements, please check [composer.json](../master/composer.json)

Installation
------------

Installing is pretty easy, this package is available on [packagist](https://packagist.org/packages/hostnet/entity-tracker-component). You can register the package locked to a major as we follow [Semantic Versioning 2.0.0](http://semver.org/).

#### Example

```javascript
    "require" : {
        "hostnet/entity-tracker-component" : "0.*"
    }

```
> Note: You can use dev-master if you want the latest changes, but this is not recommended for production code!


Documentation
=============

How does it work?
-----------------

It works by putting an annotation on your Entity and registering your listener on our event, assuming you have already registered our event to doctrine. That's all you need to do to start tracking the Entity so it will be available in the entityChanged event.

Setup
-----

#### Registering The Events

Here's an example of a very basic setup. Setting this up will be a lot easier if you use a framework that has a Dependency Injection Container.

> Note: If you use Symfony2, you can take a look at the [hostnet/entity-tracker-bundle](https://github.com/hostnet/entity-tracker-bundle). This bundle is designed to configure the services for you.

```php

use Acme\Component\Listener\UpdatedAtListener;
use Hostnet\Component\EntityTracker\Listener\EntityChangedListener;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;

/* @var $em \Doctrine\ORM\EntityManager */
$event_manager = $em->getEventManager();

// default doctrine annotation reader
$annotation_reader = new AnnotationReader();

// setup required providers
$mutation_metadata_provider   = new EntityMutationMetadataProvider($annotation_reader);
$annotation_metadata_provider = new EntityAnnotationMetadataProvider($annotation_reader);

// pre flush event listener that uses the @Tracked annotation
$entity_changed_listener = new EntityChangedListener(
    $mutation_metadata_provider,
    $annotation_metadata_provider
);

// our example listener
$event = new UpdatedAtListener(new DateTime());

// register the events
$event_manager->addEventListener('preFlush', $entity_changed_listener);
$event_manager->addEventListener('entityChanged', $event);

```

#### Creating the Listener
The listener needs to have 1 method that has the same name as the event name. This method will have 1 argument which is the `EntityChangedEvent $event`. The event contains the used EntityManager, Current Entity, Original (old) Entity and an array of the fields which have been altered -or mutated.

> Note: The Doctrine2 Event Manager uses the event name as method name, therefore you should implement the entityChanged method as listed below.

```php

namespace Acme\Component\Listener;

use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;

class ChangedAtListener
{
    private $now;

    public function __construct(\DateTime $now)
    {
        $this->now = $now;
    }

    public function entityChanged(EntityChangedEvent $event)
    {
        if (!($entity = $event->getCurrentEntity()) instanceof UpdatableInterface) {
            // uses the tracked but might not have our method
            return;
        }

        $entity->setUpdatedAt($this->now);
    }
}


```

#### Creating an Interface for the Entity
Additionally to the `@Tracked` annotation, we want to determine if we can set and updated_at field within our Entity. This can be done by creating the following interface for our Entity.

```php

namespace Acme\Component\Listener;

interface UpdatableInterface
{
   public function setUpdatedAt(\DateTime $now);
}


```

#### Registering the Annotation on the Entity
All we have to do now is put the `@Tracked` annotation and Interface on our Entity and implement the required method

```php

use Acme\Component\Listener\UpdatableInterface;
use Doctrine\ORM\Mapping as ORM;
use Hostnet\Component\EntityTracker\Annotation\Tracked;

/**
 * @ORM\Entity
 * @Tracked
 */
class MyEntity implements UpdatableInterface
{
    /**
     * @ORM\...
     */
    private $changed_at;

    public function setUpdatedAt(\DateTime $now)
    {
        $this->changed_at = $now;
    }
}

```

#### What's Next?
Change the value of a field and flush the Entity. This will trigger the preFlush, which in turn will trigger our listener, which then fires up the entityChanged event.

```php

$entity->setName('henk');
$em->flush();
// Voila, your changed_at is filled in

```

### Extending the Tracker Annotation
You might want to extend the `@Tracker` annotation. This allows you to add options and additional checks within your listener.

#### Example Annotation
In the following example, you will see how using a creating a custom annotation works.
 - You have to add `@Annotation`
 - You have to add `@Target({"CLASS"})`
 - It has to extend `Hostnet\Component\EntityTracker\Annotation\Tracked`

Using this annotation will give us specific access to options within our listener. We can now attempt to get this annotation in the listener and we get can call `getIgnoredFields()`. This example will ignore certain fields for entities using the annotation.

```php

use Hostnet\Component\EntityTracker\Annotation\Tracked;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Changed extends Tracked
{
    public $ignore_fields = [];

    public function getIgnoredFields()
    {
        if (empty($this->ignore_fields)) {
            return ['id'];
        }

        return $ignore_fields;
    }
}

```

#### Custom Annotation Resolvers
To obtain the Annotation, we have implemented resolvers. The example below shows how you could implement it yourself.

```php

use Doctrine\ORM\EntityManagerInterface;
use Hostnet\Component\EntityTracker\Provider\EntityAnnotationMetadataProvider;

class ChangedResolver
{
    private $annotation = 'Changed';

    private $provider;

    public function __construct(EntityAnnotationMetadataProvider $provider)
    {
        $this->provider = $provider;
    }

    public function getChangedAnnotation(EntityManagerInterface $em, $entity)
    {
        return $this->provider->getAnnotationFromEntity($em, $entity, $this->annotation);
    }
}

```


#### Custom entityChanged Listener
The listener can now use the resolver to obtain the annotation so possible do something extra when a specific set of fields is changed.

```php

use Hostnet\Component\EntityTracker\Event\EntityChangedEvent;

class ChangedListener
{
    private $resolver;

    public function __construct(ChangedResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function entityChanged(EntityChangedEvent $event)
    {
        $em     = $event->getEntityManager();
        $entity = $event->getCurrentEntity();

        if (null === ($annotation = $this->resolver->getChangedAnnotation($em, $entity))) {
            return;
        }

        $preferred_changes = array_diff($annotation->getIgnoredFields(), $event->getMutatedFields());

        // do something with them
    }
}
```