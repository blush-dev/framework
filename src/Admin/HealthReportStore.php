<?php

/**
 * Site Health report store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

/**
 * Keeps Site Health's last report (D-545), so the screen shows it at once
 * and checks again only when asked. It's derived from the site, like the
 * content index, so losing it costs only a check; a site keeping its data
 * in a database (D-486) can keep it there by binding another store.
 */
interface HealthReportStore
{
	/**
	 * Returns the last report, or `null` when there's none (or it can't
	 * be read).
	 *
	 * @return ?array<string, mixed>
	 */
	public function load(): ?array;

	/**
	 * Keeps a report in place of the last.
	 *
	 * @param array<string, mixed> $report
	 */
	public function save(array $report): void;
}
