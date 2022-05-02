<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Create an entity based on the original $entity_meta and
     * hydrate it with the original data.
     *
     * @param EntityManagerInterface $em
     * @param mixed                  $entity
     * @return object
     */
    public function createOriginalEntity(EntityManagerInterface $em, $entity)
    {
        $uow      = $em->getUnitOfWork();
        $id_data  = $uow->isInIdentityMap($entity) ? $uow->getEntityIdentifier($entity) : [];
        $data     = $uow->getOriginalEntityData($entity);
        $metadata = $em->getClassMetadata(get_class($entity));
        $original = $metadata->newInstance();
        $fields   = array_merge($metadata->getFieldNames(), $metadata->getAssociationNames());

        if (empty($data) && !empty($fields)) {
            return null;
        }

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $metadata->setFieldValue($original, $field, $data[$field]);
            } elseif (isset($id_data[$field])
                && $metadata->isIdentifier($field)
                && $metadata->isIdGeneratorIdentity()
            ) {
                $metadata->setFieldValue($original, $field, $id_data[$field]);
            } elseif ($metadata->isAssociationInverseSide($field)) {
                $metadata->setFieldValue($original, $field, $metadata->getFieldValue($entity, $field));
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
     * @return string[]
     * @throws \InvalidArgumentException
     */
    public function getMutatedFields(EntityManagerInterface $em, $entity, $original)
    {
        $mutation_data = [];
        /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
        $metadata     = $em->getClassMetadata(get_class($entity));
        $fields       = $metadata->getFieldNames();
        $associations = [];

        foreach ($metadata->getAssociationMappings() as $name => $mapping) {
            if ($mapping['type'] === ClassMetadataInfo::ONE_TO_ONE && !$mapping['isOwningSide']) {
                continue;
            }

            if ($mapping['type'] & ClassMetadataInfo::TO_MANY) {
                continue;
            }
            $associations[] = $name;
        }

        // New entity, everything changed.
        if ($entity !== null && $original === null) {
            return array_merge($fields, $associations);
        }

        foreach ($fields as $field) {
            $left  = $metadata->getFieldValue($entity, $field);
            $right = $metadata->getFieldValue($original, $field);

            if ($left !== $right) {
                $mutation_data[] = $field;
            }
        }
        // Owning side of OneToOne associations and ManyToOne associations.
        foreach ($associations as $association) {
            $association_target_meta = $em->getClassMetadata($metadata->getAssociationTargetClass($association));
            $association_value_left  = $metadata->getFieldValue($entity, $association);
            $association_value_right = $metadata->getFieldValue($original, $association);

            if ($this->hasAssociationChanged(
                $association_target_meta,
                $association_value_left,
                $association_value_right
            )) {
                $mutation_data[] = $association;
            }
        }

        return $mutation_data;
    }

    /**
     * @param ClassMetadata $association_meta
     * @param string        $left
     * @param string        $right
     * @return bool
     */
    private function hasAssociationChanged(ClassMetadata $association_meta, $left, $right)
    {
        // check if the PK of the related entity has changed (thus different link)
        if (null !== $left && null !== $right) {
            $left_values  = $association_meta->getIdentifierValues($left);
            $right_values = $association_meta->getIdentifierValues($right);

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
                $this->logger->info(
                    'Association Change detected on owning ONE side',
                    [
                        'left'  => $left_values,
                        'right' => $right_values,
                    ]
                );

                return true;
            }
        }

        return $left != $right;
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

    /**
     * @param EntityManagerInterface $em
     * @return array
     */
    public function getFullChangeSet(EntityManagerInterface $em)
    {
        $change_set = [];

        $managed = $em->getUnitOfWork()->getIdentityMap();
        $new     = $em->getUnitOfWork()->getScheduledEntityInsertions();

        foreach ($managed as $class => $entities) {
            $metadata = $em->getClassMetadata($class);

            foreach ($entities as $entity) {
                $this->addToChangeSet($em, $metadata, $entity, $change_set);
            }
        }

        foreach ($new as $entity) {
            $metadata = $em->getClassMetadata(get_class($entity));

            $this->addToChangeSet($em, $metadata, $entity, $change_set);
        }

        return $change_set;
    }

    /**
     * Add an entity to the change set. This also adds any elements to the
     * change set that are in the associations.
     *
     * @param EntityManagerInterface $em
     * @param ClassMetadata          $metadata
     * @param mixed                  $entity
     * @param array                  $change_set
     */
    private function addToChangeSet(
        EntityManagerInterface $em,
        ClassMetadata $metadata,
        $entity,
        array &$change_set
    ) {
        if (!isset($change_set[$metadata->rootEntityName])) {
            $change_set[$metadata->rootEntityName] = [];
        }

        if (!in_array($entity, $change_set[$metadata->rootEntityName], true)) {
            $change_set[$metadata->rootEntityName][] = $entity;

            // recursively find all changes
            $this->appendAssociations($em, $metadata, $entity, $change_set);
        }
    }

    /**
     * @param EntityManagerInterface $em
     * @param ClassMetadata          $metadata
     * @param mixed                  $entity
     * @param array                  $change_set
     */
    private function appendAssociations(
        EntityManagerInterface $em,
        ClassMetadata $metadata,
        $entity,
        array &$change_set
    ) {
        // does the entity have any associations?
        // Look for changes in associations of the entity
        foreach ($metadata->associationMappings as $field => $assoc) {
            if (($val = $metadata->reflFields[$field]->getValue($entity)) === null) {
                continue;
            }

            if ($val instanceof PersistentCollection) {
                $unwrapped_value = $val->unwrap();
            } elseif ($val instanceof Collection) {
                $unwrapped_value = $val;
            } else {
                $unwrapped_value = [$val];
            }

            $target_class = $em->getClassMetadata($assoc['targetEntity']);

            foreach ($unwrapped_value as $key => $entry) {
                if (UnitOfWork::STATE_NEW !== $em->getUnitOfWork()->getEntityState(
                    $entry,
                    UnitOfWork::STATE_NEW
                )
                ) {
                    continue;
                }

                if (!$assoc['isCascadePersist']) {
                    continue;
                }

                $this->addToChangeSet($em, $target_class, $entry, $change_set);
            }
        }
    }
}
