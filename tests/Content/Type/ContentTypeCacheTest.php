<?php

/**
 * Content type cache tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ContentTypeCache::class)]
final class ContentTypeCacheTest extends TestCase
{
	use BuildsContentSite;

	private function types(string $environment): ContentTypes
	{
		return $this->site($environment)->container()->make(ContentTypes::class);
	}

	public function testProductionServesTheCompiledTypes(): void
	{
		$this->contentConfig(['types' => ['post' => ['path' => '_posts']]]);

		new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production'])->compile();

		$this->assertFileExists($this->temporaryDirectory() . '/storage/cache/content-types.php');

		$this->contentConfig(['types' => ['movie' => ['path' => 'movies']]]);
		unlink($this->temporaryDirectory() . '/storage/cache/config.php');

		$this->assertTrue($this->types('production')->has('post'));
		$this->assertFalse($this->types('production')->has('movie'));
		$this->assertTrue($this->types('development')->has('movie'));
	}

	public function testWritesTheCache(): void
	{
		$cache = $this->site()->container()->make(ContentTypeCache::class);

		$this->assertSame(['page', 'profile'], array_keys($cache->write()->all()));
		$this->assertSame(['page', 'profile'], array_keys($cache->load()->all()));
	}
}
