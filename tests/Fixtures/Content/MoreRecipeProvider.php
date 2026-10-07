<?php

/**
 * Duplicate content fixture provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Blush\Content\Relation\RelationSource;
use Blush\Content\Type\ContentTypeSource;
use Blush\Core\ServiceProvider;

/**
 * Registers a second extension that also defines `recipe`.
 */
final class MoreRecipeProvider extends ServiceProvider
{
	protected const array TAGS = [
		ContentTypeSource::TAG => [MoreRecipeTypes::class],
		RelationSource::TAG    => [MoreRecipeRelations::class]
	];
}
