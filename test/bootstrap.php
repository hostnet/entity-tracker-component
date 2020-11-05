<?php
/**
 * @copyright 2014-present Hostnet B.V.
 */
declare(strict_types=1);

$loader = include __DIR__ . '/../vendor/autoload.php';

use Doctrine\Common\Annotations\AnnotationRegistry;

AnnotationRegistry::registerLoader([$loader, 'loadClass']);
