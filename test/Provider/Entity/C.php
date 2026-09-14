<?php
/**
 * @copyright 2017-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Provider\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class C
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    /**
     * @var C
     */
    #[ORM\ManyToOne(targetEntity: 'B', inversedBy: 'cees')]
    public $b;

    public function __construct()
    {
    }
}
