<?php

/**
 * Schedule run command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeImmutable;
use DateTimeInterface;
use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\AppConfig;

/**
 * The optional cron entry (D-040): moves the content version on if a
 * scheduled entry's time has come, and removes expired cache entries.
 * Requests do the first by themselves, so this only makes a go-live
 * happen on time for a site that nobody visits, and keeps the store
 * tidy.
 *
 *     * * * * * cd /path/to/site && php bin/blush schedule:run
 */
#[Command('schedule:run', 'Process scheduled go-live times and prune the cache store.')]
final readonly class RunSchedule
{
	public function __construct(
		private ContentVersion $version,
		private Caches $caches,
		private AppConfig $app
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$version = $this->version->current();
		$next    = $this->version->scheduled();
		$pruned  = $this->caches->prune();

		$output->success(sprintf(
			'The content version is %s; next go-live: %s. Pruned %d expired cache entr%s.',
			$version,
			$next === null ? 'none' : DateTimeImmutable::createFromTimestamp($next)->setTimezone($this->app->timezone())->format(DateTimeInterface::ATOM),
			$pruned,
			$pruned === 1 ? 'y' : 'ies'
		));

		return ExitCode::Success;
	}
}
