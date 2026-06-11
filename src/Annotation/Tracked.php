<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

namespace Hostnet\Component\EntityTracker\Annotation;

use Hostnet\Component\EntityTracker\Attributes\Tracked as TrackedAttribute;

/**
 * @Annotation
 * @Target({"CLASS"})
 *
 * @deprecated Please use the Attribute instead
 */
class Tracked extends TrackedAttribute
{
}
