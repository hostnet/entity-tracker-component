<?php
namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Node
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * @ORM\Column
     * @var string
     */
    public $name;

    /**
     * @ORM\ManyToOne(targetEntity="Node", inversedBy="children")
     * @var Node
     */
    public $parent;

    /**
     * @ORM\OneToMany(targetEntity="Node", mappedBy="parent")
     * @var Node[]
     */
    public $children;

    /**
     * @ORM\OneToOne(targetEntity="Node", inversedBy="mirrored_by")
     * @var Node
     */
    public $mirror;


    /**
     * @ORM\OneToOne(targetEntity="Node", mappedBy="mirror")
     * @var Node
     */
    public $mirrored_by;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}
