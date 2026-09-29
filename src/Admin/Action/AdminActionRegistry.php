<?php

/**
 * Admin action registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

use Blush\Support\Registry;

/**
 * Admin action classes by name. The name is the action's URL segment,
 * so it's lowercase letters, digits, and hyphens.
 *
 * @extends Registry<AdminAction>
 */
final class AdminActionRegistry extends Registry
{
	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = AdminAction::class;
}
