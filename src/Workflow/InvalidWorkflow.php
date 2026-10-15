<?php

/**
 * Invalid workflow exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use LogicException;
use Blush\Core\BlushException;

/**
 * Thrown for a workflow or transition that can't be one, such as a name
 * with capitals, or a source that isn't a `WorkflowSource`.
 */
final class InvalidWorkflow extends LogicException implements BlushException
{
}
