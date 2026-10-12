<?php

/**
 * Undeclared keys test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Entries;
use Blush\Content\UndeclaredKeys;

#[CoversClass(UndeclaredKeys::class)]
final class UndeclaredKeysTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->standardContent();
	}

	public function testFindsAndRemovesKeysNothingDeclares(): void
	{
		$app     = $this->site();
		$content = $app->container()->make(Entries::class);
		$keys    = $app->container()->make(UndeclaredKeys::class);
		$id      = (string) $content->named('post', 'spring')?->id;

		$this->assertSame(['tag'], $keys->report()[$id]['keys'] ?? null, 'Not its id, refs, or declared fields (D-786).');

		$done = $keys->remove([$id]);

		$this->assertSame([$id], $done['changed']);
		$this->assertSame([], $done['failed']);
		$this->assertArrayNotHasKey('tag', $content->editable($id)->frontMatter);
		$this->assertSame('spring', $content->named('post', 'spring')?->title, 'What\'s declared stays.');
		$this->assertArrayNotHasKey($id, $keys->report());
	}
}
