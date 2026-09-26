<?php

/**
 * Export finished event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Events;

use Blush\Export\ExportReport;

/**
 * Dispatched after a static export, with its report. Extensions listen
 * for it to add files to the output or deploy it.
 */
final readonly class ExportFinished
{
	public function __construct(public ExportReport $report)
	{}
}
