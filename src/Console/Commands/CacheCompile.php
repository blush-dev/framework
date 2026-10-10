<?php

/**
 * Cache compile command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;

/**
 * Compiles the config, plugin discovery, route table, and container
 * plans into `storage/cache` (D-060, D-066). Run it on deploy; the compiled files are
 * used outside development until cleared. Since a deploy can change
 * templates, it also clears the cache store and moves the content
 * version on (D-128).
 */
#[Command('cache:compile', 'Compile config, plugins, routes, and container plans.')]
final readonly class CacheCompile
{
	public function __construct(
		private Bootstrap $bootstrap,
		private Paths $paths,
		private Caches $caches,
		private ContentVersion $version
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$plans = $this->bootstrap->compile();

		$output->status(array_map(
			fn (CompiledCache $cache): StatusLine => new StatusLine(Outcome::Pass, 'wrote', $this->paths->relative($this->bootstrap->compiledPath($cache))),
			CompiledCache::cases()
		));

		$this->caches->clear();
		$this->version->bump();

		$output->success(sprintf('Compiled %s, and cleared the cache store.', Output::count($plans, 'container plan', 'container plans')));

		return ExitCode::Success;
	}
}
