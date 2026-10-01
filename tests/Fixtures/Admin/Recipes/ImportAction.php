<?php

/**
 * A fixture extension's admin action.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Admin\Recipes;

use Override;
use Blush\Admin\Action\ActionResult;
use Blush\Admin\Action\AdminAction;

final class ImportAction extends AdminAction
{
	#[Override]
	public function label(): string
	{
		return 'Import recipes';
	}

	#[Override]
	public function description(): string
	{
		return 'Imports recipes.';
	}

	#[Override]
	public function capability(): string
	{
		return 'site.settings';
	}

	#[Override]
	public function run(): ActionResult
	{
		return ActionResult::success('Imported.');
	}
}
