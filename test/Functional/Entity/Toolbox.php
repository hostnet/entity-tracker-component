<?php
namespace Hostnet\Component\EntityTracker\Functional\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Hostnet\Component\EntityTracker\Annotation\Tracked;

/**
 * @ORM\Entity
 * @Tracked
 */
class Toolbox
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    public $tag;

    /**
     * @ORM\OneToMany(
     *     targetEntity="Tool",
     *     mappedBy="toolbox",
     *     cascade="persist",
     *     orphanRemoval=true
     * )
     * @var Tool[]
     */
    public $tools;

    /**
     * @param Tool[] ...$tools
     */
    public function __construct(...$tools)
    {
        foreach ($tools as $tool) {
            $tool->toolbox = $this;
        }
        $this->tools = $tools;
    }
}
