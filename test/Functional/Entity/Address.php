<?php
/**
 * @copyright 2016-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Functional\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable
 */
class Address
{
    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    public $street;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @var int
     */
    public $house_number;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    public $house_number_addition;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    public $city;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    public $country;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}
