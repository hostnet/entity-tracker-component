<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Functional\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Author
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
    public $name;

    /**
     * @ORM\Embedded(class="Address")
     * @var Address
     */
    public $address;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="Book",
     *     mappedBy="authors",
     *     cascade="persist"
     * )
     * @var Book[]
     */
    public $books;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name  = $name;
        $this->books = new ArrayCollection();
    }
}
