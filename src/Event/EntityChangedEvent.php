<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Event;

use Doctrine\Common\EventArgs;
use Doctrine\ORM\EntityManagerInterface;

class EntityChangedEvent extends EventArgs
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var mixed
     */
    private $current_entity;

    /**
     * @var mixed
     */
    private $original_entity;

    /**
     * @var string[]
     */
    private $mutated_fields;

    /**
     * @param EntityManagerInterface $em
     * @param mixed                  $current_entity
     * @param mixed                  $original_entity
     * @param string[]               $mutated_fields
     */
    public function __construct(
        EntityManagerInterface $em,
        $current_entity,
        $original_entity,
        array $mutated_fields
    ) {
        $this->em              = $em;
        $this->current_entity  = $current_entity;
        $this->original_entity = $original_entity;
        $this->mutated_fields  = $mutated_fields;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->em;
    }

    /**
     * This entity is not managed!
     *
     * @return mixed
     */
    public function getOriginalEntity()
    {
        return $this->original_entity;
    }

    /**
     * The current state of the entity, the version
     * that is persisted and ready to be flushed
     *
     * @return mixed
     */
    public function getCurrentEntity()
    {
        return $this->current_entity;
    }

    /**
     * @return string[]
     */
    public function getMutatedFields()
    {
        return $this->mutated_fields;
    }
}
