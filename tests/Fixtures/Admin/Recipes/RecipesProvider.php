<?php

/**
 * A fixture extension's provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Admin\Recipes;

use Override;
use Blush\Admin\Action\AdminActionRegistry;
use Blush\Directive\DirectiveRegistry;
use Blush\Console\CommandRegistry;
use Blush\Content\Type\ContentTypeSource;
use Blush\Core\ServiceProvider;
use Blush\Icon\IconRegistry;
use Blush\Tests\Fixtures\Directive\Stamp;

/**
 * Adds a little of everything the Extensions screen lists.
 */
final class RecipesProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		ContentTypeSource::TAG => [RecipeTypes::class],
		CommandRegistry::TAG   => [ImportRecipes::class]
	];

	#[Override]
	public function boot(): void
	{
		$this->container->make(DirectiveRegistry::class)->register('fixture/recipe-card', Stamp::class);
		$this->container->make(IconRegistry::class)->add('fixture', __DIR__);
		$this->container->make(AdminActionRegistry::class)->register('import-recipes', ImportAction::class);
	}
}
