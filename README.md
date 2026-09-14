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
     - [Registering the Attribute on the Entity](#registering-the-attribute-on-the-entity)
     - [What's Next?](#whats-next)
   - [Extending the Tracker Attribute](#extending-the-tracker-attribute)
     - [Example Attribute](#example-attribute)
     - [Custom Attribute Resolvers](#custom-attribute-resolvers)
     - [Custom entityChanged Listener](#custom-entitychanged-listener)

What is the Entity Tracker?
---------------------------
The Entity Tracker Component is a library used to track changes within an Entity during a flush of the EntityManager. This makes it possible to do all sorts of things you want to automated during the `preFlush` event.

Entities become tracked when you implement the `#[Tracked]` attribute or a sub-class of `Tracked`. You have total control over what happens next and which events you will use to listen to the `entityChanged` event.

Let's say that every time you flush your User, you want to set when it was updated. By default, you would have to call `$user->setUpdatedAt()` manually or create a custom listener on preFlush that sets the updated at timestamp. Both are a lot of extra work and you have to write extra code to determine changes. Listening to preFlush will always trigger your listener and you don't want to make a huge if statement nor create a listener for each Entity.

Requirements
------------
The tracker component requires at least PHP 8.3 and runs on Doctrine2. For specific requirements, please check [composer.json](../master/composer.json)

Installation
------------

Installing is pretty easy, this package is available on [packagist](https://packagist.org/packages/hostnet/entity-tracker-component). You can register the package locked to a major as we follow [Semantic Versioning 2.0.0](http://semver.org/).

#### Example

```javascript
    "require" : {
        "hostnet/entity-tracker-component" : "^2.0.1"
    }

```
> Note: You can use dev-master if you want the latest changes, but this is not recommended for production code!


Documentation
=============

How does it work?
-----------------

It works by putting an attribute on your Entity and registering your listener on our event, assuming you have already registered our event to doctrine. That's all you need to do to start tracking the Entity so it will be available in the `entityChanged` event.

Setup
-----

#### Registering The Events

Here's an example of a very basic setup. Setting this up will be a lot easier if you use a framework that has a Dependency Injection Container.

> Note: If you use Symfony, you can take a look at the [hostnet/entity-tracker-bundle](https://github.com/hostnet/entity-tracker-bundle). This bundle is designed to configure the services for you.

```php

use Acme\Component\Listener\ChangedAtListener;
use Hostnet\Component\EntityTracker\Listener\EntityChangedListener;
use Hostnet\Component\EntityTracker\Provider\EntityMetadataProvider;
use Hostnet\Component\EntityTracker\Provider\EntityMutationMetadataProvider;

/* @var $em \Doctrine\ORM\EntityManager */
$event_manager = $em->getEventManager();

// setup required providers
$meta_provider               = new EntityMetadataProvider();
$mutation_metadata_provider  = new EntityMutationMetadataProvider();

// pre flush event listener that uses the Tracked attribute
$entity_changed_listener = new EntityChangedListener(
    $meta_provider,
    $mutation_metadata_provider
);

// our example listener
$listener = new ChangedAtListener(new DateTime());

// register the events
$event_manager->addEventListener('preFlush', $entity_changed_listener);
$event_manager->addEventListener('entityChanged', $listener);

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
Additionally to the `#[Tracked]` attribute, we want to determine if we can set and updated_at field within our Entity. This can be done by creating the following interface for our Entity.

```php

namespace Acme\Component\Listener;

interface UpdatableInterface
{
   public function setUpdatedAt(\DateTime $now);
}


```

#### Registering the Attribute on the Entity
All we have to do now is put the `#[Tracked]` attribute and Interface on our Entity and implement the required method

```php

use Acme\Component\Listener\UpdatableInterface;
use Doctrine\ORM\Mapping as ORM;
use Hostnet\Component\EntityTracker\Attributes\Tracked;

#[ORM\Entity]
#[Tracked]
class MyEntity implements UpdatableInterface
{
    #[ORM\Column]
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

### Extending the Tracker Attribute
You might want to extend the `Tracked` attribute. This allows you to add options and additional checks within your listener.

#### Example Attribute
In the following example, you will see how creating a custom attribute works.
 - You have to add `#[\Attribute(\Attribute::TARGET_CLASS)]`
 - It has to extend `Hostnet\Component\EntityTracker\Attributes\Tracked`

Using this attribute will give us specific access to options within our listener. We can now attempt to get this attribute in the listener and we get can call `getIgnoredFields()`. This example will ignore certain fields for entities using the attribute.

```php

use Hostnet\Component\EntityTracker\Attributes\Tracked;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Changed extends Tracked
{
    public $ignore_fields = [];

    public function getIgnoredFields()
    {
        if (empty($this->ignore_fields)) {
            return ['id'];
        }

        return $this->ignore_fields;
    }
}

```

#### Custom Attribute Resolvers
To obtain the attribute, we have implemented resolvers. The example below shows how you could implement it yourself.

```php

use Doctrine\ORM\EntityManagerInterface;
use Hostnet\Component\EntityTracker\Provider\EntityMetadataProvider;

class ChangedResolver
{
    private $attribute_class = Changed::class;

    private $provider;

    public function __construct(EntityMetadataProvider $provider)
    {
        $this->provider = $provider;
    }

    public function getChangedAttribute(EntityManagerInterface $em, $entity)
    {
        return $this->provider->getAttributeFromEntity($this->attribute_class, $em, $entity);
    }
}

```


#### Custom entityChanged Listener
The listener can now use the resolver to obtain the attribute so possible do something extra when a specific set of fields is changed.

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

        if (null === ($attribute = $this->resolver->getChangedAttribute($em, $entity))) {
            return;
        }

        $preferred_changes = array_diff($attribute->getIgnoredFields(), $event->getMutatedFields());

        // do something with them
    }
}
```
