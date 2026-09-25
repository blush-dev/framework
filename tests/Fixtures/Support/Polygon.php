<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Support;

/**
 * A subclass that is still abstract, so a registry must reject it.
 */
abstract class Polygon extends Shape
{
}
