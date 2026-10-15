<?php

/**
 * Workflow source interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

/**
 * Supplies workflows from core or an extension (D-735). A provider tags
 * its source with `WorkflowSource::TAG` in its `TAGS`. Core's come first
 * (`CoreWorkflows`), and when two sources register a workflow by one
 * name, the first is kept (D-597).
 */
interface WorkflowSource
{
	/**
	 * The container tag for workflow sources.
	 */
	public const string TAG = 'workflow.workflows';

	/**
	 * Returns the workflows.
	 *
	 * @return iterable<Workflow>
	 */
	public function workflows(): iterable;
}
