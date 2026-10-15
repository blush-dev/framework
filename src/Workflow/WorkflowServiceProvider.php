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

use Override;
use Blush\Core\ServiceProvider;
use Blush\Storage\Record\TableRegistry;

/**
 * Registers the status API (D-732 to D-735, D-850, D-851): core's
 * statuses and workflow, the `Statuses` and `Workflows` they're read
 * through, the `Transitions` every status change goes through, and the
 * site's own statuses and workflows as tables (D-853), kept on files as
 * folders under `user/data`.
 */
final class WorkflowServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		CoreStatuses::class,
		Statuses::class,
		CoreWorkflows::class,
		Workflows::class,
		Transitions::class,
		WorkflowTables::class,
		WorkflowWriter::class,
		StatusRepair::class
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(WorkflowTables::statusesTable());
				$tables->register(WorkflowTables::workflowsTable());
			}
		});
	}
}
