<?php

/**
 * Route cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Closure;
use Blush\Container\Attributes\Defer;
use Blush\Core\AppConfig;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Support\PhpArrayFile;

/**
 * Loads the route table: from `storage/cache/routes.php` when it exists
 * (except in development, where routes change constantly), otherwise by
 * compiling it from the route sources. `cache:compile` writes the file and
 * `cache:clear` deletes it (D-060). The compiler, and so every route
 * source, is only built when there's no cache to read.
 *
 * @phpstan-import-type RouteTableArray from RouteTable
 */
final readonly class RouteCache
{
	/**
	 * @param Closure(): RouteCompiler $compiler
	 */
	public function __construct(
		private Paths $paths,
		private AppConfig $config,
		#[Defer(RouteCompiler::class)] private Closure $compiler
	) {}

	/**
	 * Returns the route table.
	 *
	 * @throws InvalidRoute
	 */
	public function load(): RouteTable
	{
		if (! $this->config->environment->isDevelopment()) {
			/** @var ?RouteTableArray $cached Written by `write()`. */
			$cached = $this->file()->read();

			if ($cached !== null) {
				return new RouteTable($cached);
			}
		}

		return $this->compile();
	}

	/**
	 * Compiles the route table from its sources.
	 *
	 * @throws InvalidRoute
	 */
	public function compile(): RouteTable
	{
		return ($this->compiler)()->compile();
	}

	/**
	 * Compiles the route table and writes it to the cache.
	 *
	 * @throws InvalidRoute
	 */
	public function write(): RouteTable
	{
		$table = $this->compile();

		$this->file()->write($table->toArray());

		return $table;
	}

	/**
	 * Returns the cache file's path.
	 */
	public function path(): string
	{
		return $this->file()->path;
	}

	/**
	 * Returns the cache file.
	 */
	private function file(): PhpArrayFile
	{
		return new PhpArrayFile("{$this->paths->cache}/" . CompiledCache::Routes->value . '.php');
	}
}
