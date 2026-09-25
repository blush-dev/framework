<?php

/**
 * Cache clear command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;

/**
 * Deletes the compiled caches in `storage/cache` (D-060): all of them, or
 * only those named by flags. Page caches and the content version join this
 * in M6.
 */
#[Command('cache:clear', 'Clear the compiled caches.')]
final readonly class CacheClear
{
	public function __construct(
		private Bootstrap $bootstrap,
		private Paths $paths
	) {}

	public function __invoke(
		Output $output,
		#[Option('Clear the compiled config.')] bool $config = false,
		#[Option('Clear the extension discovery cache.')] bool $extensions = false,
		#[Option('Clear the compiled container plans.')] bool $container = false,
		#[Option('Clear the compiled route table.')] bool $routes = false
	): ExitCode {
		$caches = array_values(array_filter([
			$config ? CompiledCache::Config : null,
			$extensions ? CompiledCache::Extensions : null,
			$container ? CompiledCache::Container : null,
			$routes ? CompiledCache::Routes : null
		]));

		$caches = $caches === [] ? CompiledCache::cases() : $caches;

		$this->bootstrap->clearCompiled(...$caches);

		foreach ($caches as $cache) {
			$output->line(sprintf('Cleared %s', $this->paths->relative($this->bootstrap->compiledPath($cache))), Verbosity::Verbose);
		}

		$output->success(sprintf('Cleared %d compiled cache(s).', count($caches)));

		return ExitCode::Success;
	}
}
