<?php
/**
 * @copyright 2017-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class B
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="A", inversedBy="bees")
     * @var A
     */
    public $a;

    /**
     * @ORM\OneToMany(targetEntity="C", mappedBy="c", cascade={"persist"})
     * @var c[]|ArrayCollection
     */
    public $cees;

    public function __construct()
    {
        $this->cees = new ArrayCollection();
    }
}
