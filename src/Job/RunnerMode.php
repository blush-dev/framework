<?php

/**
 * Runner mode.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

/**
 * When jobs run besides a runner started from the command line (D-621):
 *
 * - `auto`: also after a page is served, while no cron or worker has run
 *   lately, where PHP-FPM can finish the response first.
 * - `cron`: never on a visit; cron or a worker does the work.
 * - `sync`: when they're queued, start to finish, for tests and local
 *   development.
 *
 * Either way, the admin runs the jobs a person starts and waits on.
 */
enum RunnerMode: string
{
	case Auto = 'auto';
	case Cron = 'cron';
	case Sync = 'sync';
}
