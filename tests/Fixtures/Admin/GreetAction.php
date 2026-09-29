<?php

/**
 * Extension-style admin action fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Admin;

use Override;
use Blush\Admin\Action\ActionResult;
use Blush\Admin\Action\AdminAction;

final class GreetAction extends AdminAction
{
	#[Override]
	public function label(): string
	{
		return 'Greet';
	}

	#[Override]
	public function description(): string
	{
		return 'Says hello.';
	}

	#[Override]
	public function capability(): string
	{
		return 'shop.greet';
	}

	#[Override]
	public function confirm(): string
	{
		return 'Say hello?';
	}

	#[Override]
	public function run(): ActionResult
	{
		return ActionResult::success('Hello.', ['from PHP']);
	}
}
