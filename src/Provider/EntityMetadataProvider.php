<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Hostnet\Component\EntityTracker\Annotation\Tracked as TrackedAnnotation;
use Hostnet\Component\EntityTracker\Attributes\Tracked as Tracked;

class EntityMetadataProvider
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @param Reader $reader
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @param mixed                  $entity
     *
     * @deprecated Please use the Tracked attribute instead
     */
    public function isTracked(EntityManagerInterface $em, $entity): bool
    {
        $class       = get_class($entity);
        $annotations = $this->reader->getClassAnnotations($em->getClassMetadata($class)->getReflectionClass());

        foreach ($annotations as $annotation) {
            if ($annotation instanceof TrackedAnnotation) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param EntityManagerInterface $em
     * @param mixed                  $entity
     * @param string                 $annotation
     *
     * @deprecated Please use the Tracked attribute instead
     */
    public function getAnnotationFromEntity(EntityManagerInterface $em, $entity, $annotation): mixed
    {
        return $this->reader->getClassAnnotation(
            $em->getClassMetadata(get_class($entity))->getReflectionClass(),
            $annotation
        );
    }

    public function getAttributeFromEntity(string $attribute_class, EntityManagerInterface $em, mixed $entity): ?Tracked
    {
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
