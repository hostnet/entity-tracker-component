<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Functional\Entity;

use Doctrine\ORM\Mapping as ORM;
use Hostnet\Component\EntityTracker\Attributes\Tracked;

#[ORM\Entity]
#[Tracked]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string')]
    public $title;

    /**
     * @var Author[]
     */
    #[ORM\ManyToMany(targetEntity: 'Author', inversedBy: 'books')]
    public $authors;

    /**
     * @param string $title
     */
    public function __construct($title)
    {
        $this->title = $title;
    }
}
