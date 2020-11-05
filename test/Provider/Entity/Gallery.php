<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Gallery
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $address;

    /**
     * @ORM\ManyToMany(targetEntity="Visitor", cascade={"persist"})
     * @ORM\JoinTable(
     *     joinColumns={@ORM\JoinColumn(name="contract_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="visitor_id", referencedColumnName="id")}
     * )
     * @var Collection
     */
    private $visitors;

    /**
     * @param string $address
     */
    public function __construct($address)
    {
        $this->address  = $address;
        $this->visitors = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param string $name
     * @return Visitor
     */
    public function addVisitor($name)
    {
        $visitor = new Visitor($name);

        $this->visitors->add($visitor);

        return $visitor;
    }
}
