<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Hostnet\Component\EntityTracker\Attributes\Tracked;

class EntityMetadataProvider
{
    /**
     * @param class-string<Tracked> $attribute_class
     */
    public function getAttributeFromEntity(
        string $attribute_class,
        EntityManagerInterface $em,
        object $entity
    ): ?Tracked {
        $class = get_class($entity);
        if ($entity instanceof Proxy) {
            $class = $em->getClassMetadata($class)->getName();
        }

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes($attribute_class, \ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            return null;
        }

        /** @var Tracked $attribute */
        $attribute = $attributes[0]->newInstance();

        return $attribute;
    }
}
