<?php

/**
 * A fixture extension's content types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Admin\Recipes;

use Override;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentTypeSource;
use Blush\Content\Type\TypeOrder;

final class RecipeTypes implements ContentTypeSource
{
	#[Override]
	public function types(): iterable
	{
		yield new Collection('recipe', folder: 'recipes');
		yield new Collection('cuisine', folder: 'recipes/cuisines', people: false, llms: false, order: TypeOrder::Position);
	}
}
