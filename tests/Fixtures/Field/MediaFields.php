<?php

/**
 * Fixture: a plugin's media fields.
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
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\TextField;
use Blush\Field\RegisteredField;

/**
 * Registers fields on kinds of media: an image's photographer (once
 * `shot_by`), and every kind's required license. The site's own fields
 * reach media kinds with their screens (D-858).
 */
final class MediaFields implements FieldSource
{
	#[Override]
	public function fields(): iterable
	{
		yield new RegisteredField('test/media', new TextField('photographer')->aliases('shot_by'), ['media:image']);
		yield new RegisteredField('test/media', new EnumField('license', ['cc-by', 'all-rights'])->required(), ['media:*']);
	}
}
