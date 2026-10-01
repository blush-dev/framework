<?php

/**
 * Fixture: registers an extension's field sets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Field;

use Blush\Core\ServiceProvider;
use Blush\Field\FieldSetSource;

/**
 * Registers an extension's field sets.
 */
final class SeoProvider extends ServiceProvider
{
	protected const array TAGS = [
		FieldSetSource::TAG => [SeoFields::class]
	];
}
