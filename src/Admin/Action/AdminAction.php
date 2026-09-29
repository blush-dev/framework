<?php

/**
 * Admin action base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

/**
 * Something the admin can run with one button, such as publishing or
 * clearing caches (D-222). It's described in PHP, and the admin draws
 * the button, asks to confirm when `confirm()` says so, and shows the
 * result. So an extension adds its own without writing JavaScript:
 *
 *     $container->get(AdminActionRegistry::class)->register('shop-sync', SyncOrders::class);
 *
 * Actions are built through the container, so constructors can ask for
 * services. `run()` should finish within a request.
 */
abstract class AdminAction
{
	/**
	 * Returns the button's label.
	 */
	abstract public function label(): string;

	/**
	 * Returns a sentence on what the action does.
	 */
	abstract public function description(): string;

	/**
	 * Returns the capability an account needs to run it.
	 */
	abstract public function capability(): string;

	/**
	 * Runs the action.
	 */
	abstract public function run(): ActionResult;

	/**
	 * Returns a question to confirm before running, or `null` to run
	 * straight away.
	 */
	public function confirm(): ?string
	{
		return null;
	}
}
