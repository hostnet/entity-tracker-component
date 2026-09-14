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
     * @param string[] $mutated_fields
     */
    public function __construct(
        private EntityManagerInterface $em,
        private object $current_entity,
        private ?object $original_entity,
        private array $mutated_fields
    ) {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * This entity is not managed!
     */
    public function getOriginalEntity(): ?object
    {
        return $this->original_entity;
    }

    /**
     * The current state of the entity, the version
     * that is persisted and ready to be flushed
     */
    public function getCurrentEntity(): object
    {
        return $this->current_entity;
    }

    /**
     * @return string[]
     */
    public function getMutatedFields(): array
    {
        return $this->mutated_fields;
    }
}
