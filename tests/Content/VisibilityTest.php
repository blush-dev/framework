<?php

/**
 * Visibility tests.
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
use Blush\Content\Entry\Entry;
use Blush\Content\Index\HiddenKeys;
use Blush\Content\Lint\Linter;
use Blush\Content\Type\ContentType;
use Blush\Content\Visibility;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;

/**
 * Who sees an entry (D-855): front matter alone says its visibility,
 * core's own places are hidden, a type can turn visibility off, and an
 * entry links only when it's linkable.
 */
#[CoversClass(Entry::class)]
#[CoversClass(HiddenKeys::class)]
#[CoversClass(ContentType::class)]
#[CoversClass(Linter::class)]
final class VisibilityTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->contentConfig(['types' => [
			'post' => ['kind' => 'collection'],
			'note' => ['kind' => 'collection', 'visibility' => false],
			'card' => ['kind' => 'collection', 'public' => false]
		]]);
		$this->entry('about.md', 'title: About');
		$this->entry('quiet.md', "title: Quiet\nvisibility: unlisted");
		$this->entry('secret.md', "title: Secret\nvisibility: hidden");
		$this->entry('_old.md', 'title: Old');
		$this->entry('_post/live.md', "title: Live\ndate: 2026-01-01");
		$this->entry('_post/rough.md', "title: Rough\ndate: 2026-01-01\nstatus: draft");
		$this->entry('_note/open.md', "title: Open\nvisibility: hidden");
		$this->entry('_card/one.md', 'title: One');

		$this->app = $this->site();
	}

	private function named(string $type, string $key): Entry
	{
		$entry = $this->app->container()->make(Entries::class)->named($type, $key);
		$this->assertNotNull($entry, "{$type}: {$key}");

		return $entry;
	}

	public function testLinksOnlyWhatIsLinkable(): void
	{
		$this->assertTrue($this->named('page', 'about')->isLinkable());
		$this->assertSame('<a href="/about">About</a>', (string) $this->named('page', 'about')->link());
		$this->assertTrue($this->named('page', 'quiet')->isLinkable(), 'Unlisted has an address.');
		$this->assertFalse($this->named('page', 'quiet')->isListed());

		$secret = $this->named('page', 'secret');
		$this->assertFalse($secret->isLinkable());
		$this->assertSame('Secret', (string) $secret->link(), 'The title alone.');

		$rough = $this->named('post', 'rough');
		$this->assertFalse($rough->isLinkable(), 'A draft isn\'t live.');
		$this->assertSame('Rough', (string) $rough->link());
		$this->assertNotSame('', $rough->url(), 'A draft still has its address.');
		$this->assertTrue($rough->isDraft());
		$this->assertFalse($rough->isPublished());
		$this->assertTrue($this->named('post', 'live')->isPublished());
	}

	public function testAnUnderscoreNameMeansNothing(): void
	{
		$old = $this->named('page', '_old');

		$this->assertSame(Visibility::Public, $old->visibility);
		$this->assertTrue($old->isLive());

		$files = $this->app->container()->make(Linter::class)->lint()->files;
		$kinds = static fn (string $path): array => array_map(static fn (Violation $violation): ?ViolationKind => $violation->kind, $files[$path] ?? []);

		$this->assertContains(ViolationKind::Prefix, $kinds('_old.md'), 'Lint warns of it.');
		$this->assertNotContains(ViolationKind::Prefix, $kinds('secret.md'));
	}

	public function testATypeCanTurnVisibilityOff(): void
	{
		$open = $this->named('note', 'open');

		$this->assertFalse($open->type->hasVisibility());
		$this->assertSame(Visibility::Public, $open->visibility, 'Front matter is ignored.');
		$this->assertSame(['name' => 'note', 'kind' => 'collection'], array_intersect_key($open->type->toArray(), array_flip(['name', 'kind', 'visibility'])), 'Off is the default, so it isn\'t written (D-866).');
		$this->assertTrue($this->named('post', 'live')->type->hasVisibility());
		$this->assertFalse($this->named('card', 'one')->type->hasVisibility(), 'A type off the site has no addresses.');
		$this->assertFalse($this->named('card', 'one')->isLinkable());
	}
}
