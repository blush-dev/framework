<?php

/**
 * Workflow service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Blush\Core\ServiceProvider;

/**
 * Registers the status API (D-732 to D-735, D-850): core's statuses and
 * the `Statuses` every status is read through.
 */
final class WorkflowServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		CoreStatuses::class,
		Statuses::class
	];
}
