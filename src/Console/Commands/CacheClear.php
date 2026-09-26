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

use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;

/**
 * Deletes the compiled caches in `storage/cache` (D-060) and clears the
 * cache store (pages, rendered bodies, tokens, and fragments), moving the
 * content version on: all of them, or only those named by flags (D-128).
 */
#[Command('cache:clear', 'Clear the compiled caches and the cache store.')]
final readonly class CacheClear
{
	public function __construct(
		private Bootstrap $bootstrap,
		private Paths $paths,
		private Caches $caches,
		private ContentVersion $version
	) {}

	public function __invoke(
		Output $output,
		#[Option('Clear the compiled config.')] bool $config = false,
		#[Option('Clear the extension discovery cache.')] bool $extensions = false,
		#[Option('Clear the compiled container plans.')] bool $container = false,
		#[Option('Clear the compiled route table.')] bool $routes = false,
		#[Option('Clear the compiled content types.')] bool $types = false,
		#[Option('Clear the theme discovery cache.')] bool $themes = false,
		#[Option('Clear the cache store and move the content version on.')] bool $store = false
	): ExitCode {
		$compiled = $config || $extensions || $container || $routes || $types || $themes;

		$caches = array_values(array_filter([
			$config ? CompiledCache::Config : null,
			$extensions ? CompiledCache::Extensions : null,
			$container ? CompiledCache::Container : null,
			$routes ? CompiledCache::Routes : null,
			$types ? CompiledCache::ContentTypes : null,
			$themes ? CompiledCache::Themes : null
		]));

		if ($compiled || ! $store) {
			$caches = $caches === [] ? CompiledCache::cases() : $caches;

			$this->bootstrap->clearCompiled(...$caches);

			foreach ($caches as $cache) {
				$output->line(sprintf('Cleared %s', $this->paths->relative($this->bootstrap->compiledPath($cache))), Verbosity::Verbose);
			}

			$output->success(sprintf('Cleared %d compiled cache(s).', count($caches)));
		}

		if ($store || ! $compiled) {
			$namespaces = $this->caches->clear();

			$output->success(sprintf('Cleared the cache store (%s); the content version is now %s.', implode(', ', $namespaces), $this->version->bump()));
		}

		return ExitCode::Success;
	}
}
