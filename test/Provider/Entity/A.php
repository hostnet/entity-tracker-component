<?php
/**
 * @copyright 2017-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class A
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public $id;

    /**
     * @var B[]|ArrayCollection
     */
    #[ORM\OneToMany(targetEntity: 'B', mappedBy: 'a', cascade: ['persist'])]
    public $bees;

    public function __construct()
    {
        $this->bees = new ArrayCollection();
    }
}
