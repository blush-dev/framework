<?php

/**
 * Fixture: an extension's field sets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Field;

use Override;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetSource;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\TextField;

/**
 * An extension's SEO fields for posts and pages.
 */
final class SeoFields implements FieldSetSource
{
	#[Override]
	public function fieldSets(): iterable
	{
		yield new FieldSet('seo', [new TextField('meta_title'), new BoolField('noindex')], ['type:post', 'type:page'], 'SEO');
	}
}
