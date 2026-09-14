<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Functional\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Tool
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public $id;

    /**
     * @var Toolbox
     */
    #[ORM\ManyToOne(targetEntity: 'Toolbox', inversedBy: 'tools')]
    public $toolbox;

    #[ORM\Column(type: 'string')]
    public $name;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}
