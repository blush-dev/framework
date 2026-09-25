<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Support;

use Blush\Support\Registry;

/**
 * @extends Registry<Shape>
 */
final class ShapeRegistry extends Registry
{
	protected const string CONTRACT = Shape::class;
}
