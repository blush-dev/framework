<?php

/**
 * Clear caches action.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

use Override;
use Blush\Auth\Capability;
use Blush\Cache\CacheException;
use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;

/**
 * Clears the cache store and moves the content version on, as
 * `cache:clear --store` does. Compiled files (config, routes, container
 * plans) are left alone: they're deploy artifacts, rebuilt with
 * `cache:compile`.
 */
final class ClearCachesAction extends AdminAction
{
	public function __construct(
		private readonly Caches $caches,
		private readonly ContentVersion $version
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Clear caches';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function description(): string
	{
		return 'Empty the page, body, and fragment caches, so every page renders fresh.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function capability(): string
	{
		return Capability::CacheClear->value;
	}

	/**
	 * @inheritDoc
	 * @throws CacheException
	 */
	#[Override]
	public function run(): ActionResult
	{
		$cleared = $this->caches->clear();
		$this->version->bump();

		return ActionResult::success(sprintf('Cleared the caches (%s).', implode(', ', $cleared)));
	}
}
