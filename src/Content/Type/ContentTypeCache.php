<?php

/**
 * Content type cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Closure;
use Blush\Container\Attributes\Defer;
use Blush\Content\Schema\FieldFactory;
use Blush\Core\AppConfig;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Support\PhpArrayFile;

/**
 * Loads the content types: from `storage/cache/content-types.php` when it
 * exists (except in development), otherwise by loading and checking them
 * from every source. `cache:compile` writes the file and `cache:clear`
 * deletes it (D-060), so production never reads `user/data/types` or
 * builds extension sources per request.
 */
final readonly class ContentTypeCache
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private Paths $paths,
		private AppConfig $config,
		private FieldFactory $fields,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns the content types.
	 *
	 * @throws InvalidContentType
	 */
	public function load(): ContentTypes
	{
		if (! $this->config->environment->isDevelopment()) {
			$cached = $this->file()->read();

			if ($cached !== null) {
				return ContentTypes::fromArray($cached, $this->fields);
			}
		}

		return ($this->loader)()->load();
	}

	/**
	 * Loads the types from their sources and writes them to the cache.
	 *
	 * @throws InvalidContentType
	 */
	public function write(): ContentTypes
	{
		$types = ($this->loader)()->load();

		$this->file()->write($types->toArray());

		return $types;
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
		return new PhpArrayFile("{$this->paths->cache}/" . CompiledCache::ContentTypes->value . '.php');
	}
}
