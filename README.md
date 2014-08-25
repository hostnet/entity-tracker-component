README
======


What is the Entity Tracker?
---------------------------
The Entity Tracker Component is a library used to track entity changes during an Entity Flush. This makes it possible to do all sorts of things you want to automated during the `preFlush` event.

Entities become tracked when you implement the `@Tracked` annotation or a sub-class of `@Tracked`. You have total control over what happens next and which events you will use to listen to the `entityChanged` event.

Let's say that every time you flush your User, you want to set when it was updated. By default standards, you would have to call `$user->setUpdatedAt()` manually or create a custom listener on preFlush that sets the updated at. Both are a lot of extra work and you have to write extra code to determine changes. Listening to preFlush will always trigger your updated at and you don't want to make a huge if statement nor create a listener for each entity.

Requirements
------------
The tracker component requires a minimal php version of 5.4 and runs on Doctrine2. For specific requirements, please check [composer.json](../blob/master/composer.json)

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

It works by putting an annotation on your entity and registering your listener on our event, assuming you have already registered our event to doctrine. That's all you need to do to start tracking the entity so it will be available in the entityChanged event.

Setup
-----

#### Registering the events

Here's an example of a very basic setup. Setting this up will be a lot easier if you use a framework that has a Dependency Injection Container.

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

#### ChangedAtListener.php
The listener needs to have 1 method that has the same name as the event name, it's how the doctrine event manager works. This method will have 1 argument which is the `EntityChangedEvent $event`. The event contains the used EntityManager, Current Entity, Original (old) Entity and an array of the fields which have been altered -or mutated.

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

#### _UpdatableInterface.php_
This interface is just to validate we have the setUpdatedAt method available in our listener.

```php

namespace Acme\Component\Listener;

interface UpdatableInterface
{
   public function setUpdatedAt(\DateTime $now);
}


```

#### Registering the Annotation
All we have to do now is put the @Tracked annotation and Interface on our Entity.

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

    public function setUpdatedAt(\DateTime $dt)
    {
        $this->changed_at = $dt;
    }
}

```

### What's next?

```php

$entity->setName('henk');
$em->flush();
// Voila, your updatedAt is set filled in

```
