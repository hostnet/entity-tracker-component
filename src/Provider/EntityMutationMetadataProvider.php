<?php
namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 */
class EntityMutationMetadataProvider
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Reader          $reader
     * @param LoggerInterface $logger
     */
    public function __construct(Reader $reader, LoggerInterface $logger = null)
    {
        $this->reader = $reader;
        $this->logger = $logger ? : new NullLogger();
    }

    /**
     * Create an entity based on the original $entity_meta and
     * hydrate it with the original data.
     *
     * @param EntityManagerInterface $em
     * @param mixed                  $data
     * @return object
     */
    public function createOriginalEntity(EntityManagerInterface $em, $entity)
    {
        $data     = $em->getUnitOfWork()->getOriginalEntityData($entity);
        $metadata = $em->getClassMetadata(get_class($entity));
        $original = $metadata->newInstance();
        $fields   = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        if (empty($data) && !empty($fields)) {
            return null;
        }

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $metadata->setFieldValue($original, $field, $data[$field]);
            }
        }

        return $original;
    }

    /**
     * Return the field names of fields that had their value/relation changed
     *
     * @param EntityManagerInterface $em
     * @param mixed                  $entity
     * @param mixed                  $original
     * @return bool
     */
    public function getMutatedFields(EntityManagerInterface $em, $entity, $original)
    {
        $mutation_data = [];
        $metadata      = $em->getClassMetadata(get_class($entity));
        $fields        = $metadata->getFieldNames();
        $associations  = $metadata->getAssociationNames();

        if ($entity !== null && $original === null) {
            return array_merge($fields, $associations);
        }

        for ($i = 0, $n = count($fields); $i < $n; $i++) {
            $field = $fields[$i];
            $left  = $metadata->getFieldValue($entity, $field);
            $right = $metadata->getFieldValue($original, $field);

            if ($left !== $right) {
                $mutation_data[] = $field;
            }
        }

        for ($i = 0, $n = count($associations); $i < $n; $i++) {
            $association             = $associations[$i];
            $association_meta        = $em->getClassMetadata($metadata->getAssociationTargetClass($association));
            $association_value_left  = $metadata->getFieldValue($entity, $association);
            $association_value_right = $metadata->getFieldValue($original, $association);

            if ($this->hasAssociationChanged($association_meta, $association_value_left, $association_value_right)) {
                $mutation_data[] = $association;
            }
        }

        return $mutation_data;
    }

    /**
     * @param ClassMetaData $association_meta
     * @param string        $left
     * @param string        $right
     * @return bool
     */
    private function hasAssociationChanged(ClassMetadata $association_meta, $left, $right)
    {
        // check if the PK of the related entity has changed (thus different link)
        if (null !== $left && null !== $right) {
            $left_values = $association_meta->getIdentifierValues($left);
            $right_values = $association_meta->getIdentifierValues($right);

            $this->logger->info(
                "hasAssociationChanged",
                [
                    'left' => $left_values,
                    'right' => $right_values
                ]
            );
            $diff = array_udiff(
                $left_values,
                $right_values,
                function ($a, $b) {
                    // note that equal returns 0, difference should return -1 or 1
                    if (!is_object($a) && !is_object($b)) {
                        return (string) $a === (string) $b ? 0 : 1;
                    }

                    // prevent casting objects to strings
                    return $a === $b ? 0 : 1;
                }
            );
            if (!empty($diff)) {
                return true;
            }
        }

        return $left != $right;
    }

    /**
     * Return the full set of changes, including inserts
     *
     * @param EntityManagerInterface $em
     * @return array
     */
    public function getFullChangeSet(EntityManagerInterface $em)
    {
        $uow     = $em->getUnitOfWork();
        $changes = $uow->getIdentityMap();
        $inserts = $uow->getScheduledEntityInsertions();

        foreach ($inserts as $entity) {
            $class = get_class($entity);
            if (!isset($changes[$class])) {
                $changes[$class] = [];
            }
            if (!in_array($entity, $changes[$class], true)) {
                $changes[$class][] = $entity;
            }
        }

        return $changes;
    }

    /**
     * @param EntityManagerInterface $em
     * @param mixed                  $entity
     * @return bool
     */
    public function isEntityManaged(EntityManagerInterface $em, $entity)
    {
        return $em->getUnitOfWork()->getEntityState($entity) === UnitOfWork::STATE_MANAGED;
    }
}
