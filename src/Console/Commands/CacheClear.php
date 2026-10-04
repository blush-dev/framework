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

use Blush\Cache\CacheNamespace;
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
 * cache store (pages, rendered bodies, and fragments), moving the
 * content version on: all of them, or only those named by flags (D-128).
 * `--embeds` also empties the oEmbed answers, which nothing else clears,
 * along with the store, whose pages and bodies hold the old embeds.
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
		#[Option('Clear the plugin discovery cache.')] bool $plugins = false,
		#[Option('Clear the compiled container plans.')] bool $container = false,
		#[Option('Clear the compiled route table.')] bool $routes = false,
		#[Option('Clear the compiled content types.')] bool $types = false,
		#[Option('Clear the theme discovery cache.')] bool $themes = false,
		#[Option('Clear the icon pack discovery cache.')] bool $iconPacks = false,
		#[Option('Clear the cache store and move the content version on.')] bool $store = false,
		#[Option('Clear the oEmbed answers, so providers are asked again, and the cache store.')] bool $embeds = false
	): ExitCode {
		$compiled = $config || $plugins || $container || $routes || $types || $themes || $iconPacks;
		$store    = $store || $embeds;

		$caches = array_values(array_filter([
			$config ? CompiledCache::Config : null,
			$plugins ? CompiledCache::Plugins : null,
			$container ? CompiledCache::Container : null,
			$routes ? CompiledCache::Routes : null,
			$types ? CompiledCache::ContentTypes : null,
			$themes ? CompiledCache::Themes : null,
			$iconPacks ? CompiledCache::IconPacks : null
		]));

		if ($compiled || ! $store) {
			$caches = $caches === [] ? CompiledCache::cases() : $caches;

			$this->bootstrap->clearCompiled(...$caches);

			foreach ($caches as $cache) {
				$output->line(sprintf('Cleared %s', $this->paths->relative($this->bootstrap->compiledPath($cache))), Verbosity::Verbose);
			}

			$output->success(sprintf('Cleared %d compiled cache(s).', count($caches)));
		}

		if ($embeds) {
			$this->caches->persistent(CacheNamespace::Embeds)->clear();

			$output->success('Cleared the oEmbed answers.');
		}

		if ($store || ! $compiled) {
			$namespaces = $this->caches->clear();

			$output->success(sprintf('Cleared the cache store (%s); the content version is now %s.', implode(', ', $namespaces), $this->version->bump()));
		}

		return ExitCode::Success;
	}
}
