<?php

/**
 * Fixture: a theme's settings fields.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Field;

use Blush\Core\ServiceProvider;
use Blush\Field\FieldSource;

/**
 * Registers a theme's settings fields.
 */
final class TaglineProvider extends ServiceProvider
{
	protected const array TAGS = [
		FieldSource::TAG => [TaglineFields::class]
	];
}
