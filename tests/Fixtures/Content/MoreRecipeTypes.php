<?php

/**
 * Duplicate extension content type source fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Override;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypeSource;

final class MoreRecipeTypes implements ContentTypeSource
{
	#[Override]
	public function types(): iterable
	{
		yield new ContentType('recipe', path: 'cookbook');
	}
}
