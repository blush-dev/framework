<?php

/**
 * Admin action factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

use Blush\Container\Container;

/**
 * Builds the registered admin actions through the container.
 */
final readonly class AdminActions
{
	public function __construct(
		private AdminActionRegistry $registry,
		private Container $container
	) {}

	/**
	 * Returns an action, or `null` when none has that name.
	 */
	public function get(string $name): ?AdminAction
	{
		$class = $this->registry->get($name);

		return $class === null ? null : $this->container->make($class);
	}

	/**
	 * Returns every action, by name, in registration order.
	 *
	 * @return array<string, AdminAction>
	 */
	public function all(): array
	{
		$actions = [];

		foreach (array_keys($this->registry->all()) as $name) {
			$action = $this->get($name);

			if ($action !== null) {
				$actions[$name] = $action;
			}
		}

		return $actions;
	}
}
