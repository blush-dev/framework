<?php

/**
 * Recipe relations fixture.
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

final class RecipeRelations implements RelationSource
{
	#[Override]
	public function relations(): iterable
	{
		yield new Relation('ingredient', RelationKind::Classify, from: ['recipe'], to: ['ingredient'], create: true);
	}
}
