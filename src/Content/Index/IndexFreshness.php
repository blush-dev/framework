<?php

/**
 * Index freshness.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Closure;
use Blush\Container\Attributes\Defer;
use Blush\Content\ContentConfig;
use Blush\Core\AppConfig;

/**
 * The content index, brought up to date once a request before it's read:
 * indexed when it's missing or built with other types, relations, or
 * settings (its fingerprint), and in development, on the first read of
 * every request (`autoIndex`). The repository and the filesystem
 * driver's content store (`IndexStore`) read the index through it.
 */
final class IndexFreshness
{
	private bool $checked = false;

	/**
	 * @param Closure(): Indexer $indexer
	 */
	public function __construct(
		private readonly ContentIndex $index,
		private readonly IndexFingerprint $fingerprint,
		private readonly ContentConfig $config,
		private readonly AppConfig $app,
		#[Defer(Indexer::class)] private readonly Closure $indexer
	) {}

	/**
	 * Returns the index, indexing first when it's due.
	 */
	public function fresh(): ContentIndex
	{
		if (! $this->checked) {
			$this->checked = true;

			$stale = ! $this->index->exists() || ! $this->fingerprint->matches($this->index->snapshot());

			if ($stale || ($this->config->autoIndex && $this->app->environment->isDevelopment())) {
				($this->indexer)()->index();
			}
		}

		return $this->index;
	}
}
