<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Node
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public $id;

    #[ORM\Column(type: 'string')]
    public $name;

    /**
     * @var Node
     */
    #[ORM\ManyToOne(targetEntity: 'Node', inversedBy: 'children')]
    public $parent;

    /**
     * @var Node[]
     */
    #[ORM\OneToMany(targetEntity: 'Node', mappedBy: 'parent')]
    public $children;

    /**
     * @var Node
     */
    #[ORM\OneToOne(targetEntity: 'Node', inversedBy: 'mirrored_by')]
    public $mirror;

    /**
     * @var Node
     */
    #[ORM\OneToOne(targetEntity: 'Node', mappedBy: 'mirror')]
    public $mirrored_by;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name     = $name;
        $this->children = new ArrayCollection();
    }
}
