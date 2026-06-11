<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Hostnet\Component\EntityTracker\Annotation\Tracked;

/**
 * @deprecated Please use the Tracked and related attributes instead
 */
class EntityAnnotationMetadataProvider
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
            if ($annotation instanceof Tracked) {
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
}
