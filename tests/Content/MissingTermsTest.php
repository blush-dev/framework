<?php

/**
 * Missing terms tests.
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
use Blush\Content\ContentRepository;
use Blush\Content\CreatedTerms;
use Blush\Content\MissingTerms;

#[CoversClass(MissingTerms::class)]
#[CoversClass(CreatedTerms::class)]
final class MissingTermsTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('_posts/2009-01-01.dangling.md', "title: Dangling\npublished: 2009-01-01\ncategory: [art, Lost Cause, zebra]\nauthors: [justintadlock, nobody]");
	}

	public function testFindsSlugsWithNoFile(): void
	{
		$this->assertSame([
			'category' => ['lost-cause' => 'Lost Cause', 'zebra' => 'zebra'],
			'profile'  => ['nobody' => 'nobody']
		], $this->site()->container()->make(MissingTerms::class)->report(), 'Titled as entries first wrote them, or by the slug (D-584).');
	}

	public function testWritesThemPublished(): void
	{
		$app     = $this->site();
		$created = $app->container()->make(MissingTerms::class)->create();

		$this->assertSame(['category/lost-cause' => 'topics/lost-cause.md', 'category/zebra' => 'topics/zebra.md', 'profile/nobody' => 'profiles/n/nobody.md'], $created->created);
		$this->assertSame([], $created->failed);
		$this->assertMatchesRegularExpression('/\A---\ntitle: "Lost Cause"\npublished: 2026-06-01 12:00:00 -05:00\nid: [0-9a-f-]{36}\n---\n/', (string) file_get_contents($this->temporaryDirectory() . '/user/content/topics/lost-cause.md'));

		$content = $app->container()->make(ContentRepository::class);

		$this->assertSame('Lost Cause', $content->term('category', 'lost-cause')?->title);
		$this->assertTrue($content->term('profile', 'nobody')?->isPublished());
		$this->assertSame([], $app->container()->make(MissingTerms::class)->report());
	}

	public function testOnlyTheTypesAllowed(): void
	{
		$created = $this->site()->container()->make(MissingTerms::class)->create(static fn (string $type): bool => $type === 'profile');

		$this->assertSame(['profile/nobody' => 'profiles/n/nobody.md'], $created->created);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/topics/zebra.md');
	}
}
