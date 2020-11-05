<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Functional\Entity;

use Doctrine\ORM\Mapping as ORM;
use Hostnet\Component\EntityTracker\Annotation\Tracked;

/**
 * @ORM\Entity
 * @Tracked
 */
class Book
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column
     * @var string
     */
    public $title;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="Author",
     *     inversedBy="books"
     * )
     * @var Author[]
     */
    public $authors;

    /**
     * @param string $title
     */
    public function __construct($title)
    {
        $this->title = $title;
    }
}
