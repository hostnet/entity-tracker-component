<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Visitor
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
     * @ORM\Column(type="string")
     * @var string
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity="Visit", mappedBy="visitor", cascade={"persist"})
     * @var ArrayCollection|Visit[]
     */
    private $visits;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name   = $name;
        $this->visits = new ArrayCollection();
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getVisits()
    {
        return $this->visits;
    }

    public function addVisit(Visit $visit)
    {
        $this->visits->add($visit);
    }
}
