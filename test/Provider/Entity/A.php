<?php
/**
 * @copyright 2017-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class A
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * @ORM\OneToMany(targetEntity="B", mappedBy="a", cascade={"persist"})
     * @var B[]|ArrayCollection
     */
    public $bees;

    public function __construct()
    {
        $this->bees = new ArrayCollection();
    }
}
