<?php

/**
 * More recipe relations (a fixture).
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Override;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationSource;

/**
 * A second extension's relation, by a name `RecipeRelations` takes first.
 */
final class MoreRecipeRelations implements RelationSource
{
	#[Override]
	public function relations(): iterable
	{
		yield new Relation('ingredient', RelationKind::Reference, from: ['recipe'], to: ['recipe']);
	}
}
