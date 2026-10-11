<?php

/**
 * Clear caches tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use Override;
use Blush\Auth\Capability;
use Blush\Cache\CacheException;
use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolResult;

/**
 * `core/clear-caches`: clears the cache store and moves the content
 * version on, as `cache:clear --store` does (D-761). Compiled files
 * (config, routes, container plans) are left alone: they're deploy
 * artifacts, rebuilt with `cache:compile`. It's for looking after the
 * site, so apps aren't offered it.
 */
final class ClearCaches extends Tool
{
	public function __construct(
		private readonly Caches $caches,
		private readonly ContentVersion $version
	) {}

	#[Override]
	public function title(): string
	{
		return 'Clear Caches';
	}

	#[Override]
	public function description(): string
	{
		return 'Empty the page, body, and fragment caches, so every page renders fresh.';
	}

	#[Override]
	public function capability(): Capability
	{
		return Capability::CacheClear;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true);
	}

	#[Override]
	public function mcp(): bool
	{
		return false;
	}

	/**
	 * @inheritDoc
	 * @throws CacheException
	 */
	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$cleared = $this->caches->clear();
		$this->version->bump();

		return ToolResult::done(sprintf('Cleared the caches (%s).', implode(', ', $cleared)));
	}
}
