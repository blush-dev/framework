<?php

/**
 * Fixture: an extension's registered fields.
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
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\TextField;
use Blush\Field\RegisteredField;

/**
 * Registers SEO fields on posts and pages.
 */
final class SeoFields implements FieldSource
{
	#[Override]
	public function fields(): iterable
	{
		yield new RegisteredField('acme/seo', new TextField('meta_title'), ['type:post', 'type:page']);
		yield new RegisteredField('acme/seo', new BoolField('noindex'), ['type:post', 'type:page']);
	}
}
