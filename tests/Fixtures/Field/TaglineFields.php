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

use Override;
use Blush\Field\FieldSource;
use Blush\Field\Fields\TextField;
use Blush\Field\RegisteredField;

/**
 * Registers a theme's `tagline` setting on the General screen (D-858: the
 * site's own fields reach Settings screens with their editor).
 */
final class TaglineFields implements FieldSource
{
	#[Override]
	public function fields(): iterable
	{
		yield new RegisteredField('acme/noted', new TextField('tagline')->default('Plain tagline'), ['settings:general']);
	}
}
