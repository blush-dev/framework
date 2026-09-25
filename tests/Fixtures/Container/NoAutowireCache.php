<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Container\Attributes\NoAutowire;

/**
 * Its dependency is autowirable — the container could build a `FileCache` —
 * but `#[NoAutowire]` suppresses that so the parameter keeps its default.
 */
final class NoAutowireCache
{
	public function __construct(
		#[NoAutowire] public readonly ?Cache $cache = null
	) {}
}
