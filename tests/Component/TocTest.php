<?php

/**
 * Table of contents tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Component;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\ContentRepository;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Markdown\CommonMark\Directive\CollectOutline;
use Blush\Tests\BootsScratchSite;
use Blush\Component\Toc;

#[CoversClass(Toc::class)]
#[CoversClass(CollectOutline::class)]
final class TocTest extends TestCase
{
	use BootsScratchSite;

	private const string GUIDE = <<<'MD'
		---
		title: Guide
		---
		::toc[On this page]

		Intro words here.

		## Install *it*

		### Requirements

		#### Too deep

		## Setup

		## Setup
		MD;

	private function app(string $markdownConfig = ''): Application
	{
		$this->writeTemporaryFile('user/content/guide.md', self::GUIDE);

		if ($markdownConfig !== '') {
			$this->writeTemporaryFile('config/markdown.php', $markdownConfig);
		}

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	private function page(Application $app, string $path): string
	{
		return (string) $app->container()->make(Kernel::class)->handle(Request::create($path))->getBody();
	}

	/**
	 * Returns a tree's ids, with children under their parent's id.
	 *
	 * @param  array<mixed> $items
	 * @return list<mixed>
	 */
	private static function shape(array $items): array
	{
		$shape = [];

		foreach ($items as $item) {
			if (is_array($item) && is_string($item['id'] ?? null) && is_array($item['children'] ?? null)) {
				$shape[] = $item['children'] === [] ? $item['id'] : [$item['id'] => self::shape($item['children'])];
			}
		}

		return $shape;
	}

	public function testHeadingsNestByLevel(): void
	{
		$headings = [
			['level' => 3, 'text' => 'Early', 'id' => 'early'],
			['level' => 2, 'text' => 'One', 'id' => 'one'],
			['level' => 4, 'text' => 'Skipped a level', 'id' => 'skip'],
			['level' => 3, 'text' => 'One A', 'id' => 'one-a'],
			['level' => 2, 'text' => 'Two', 'id' => 'two'],
			['level' => 5, 'text' => 'Out of range', 'id' => 'out']
		];

		$this->assertSame(['early', ['one' => ['skip', 'one-a']], 'two'], self::shape(new Toc(2, 4, $headings)->items));
		$this->assertSame(['one', 'two'], self::shape(new Toc(2, 2, $headings)->items));
		$this->assertSame(self::shape(new Toc(2, 4, $headings)->items), self::shape(new Toc(4, 2, $headings)->items));
		$this->assertFalse(new Toc(6, 6, $headings)->shouldRender());
		$this->assertFalse(new Toc()->shouldRender());
	}

	public function testItLinksToHeadingsItGivesIds(): void
	{
		$html = $this->page($this->app(), '/guide');

		$this->assertStringContainsString('<nav class="component-toc" aria-label="On this page">', $html);
		$this->assertStringContainsString('<p class="component-toc__title">On this page</p>', $html);
		$this->assertStringContainsString(
			'<ol class="component-toc__list"><li class="component-toc__item"><a class="component-toc__link" href="#install-it">Install it</a>'
				. '<ol class="component-toc__list"><li class="component-toc__item"><a class="component-toc__link" href="#requirements">Requirements</a></li></ol></li>'
				. '<li class="component-toc__item"><a class="component-toc__link" href="#setup">Setup</a></li>'
				. '<li class="component-toc__item"><a class="component-toc__link" href="#setup-1">Setup</a></li></ol>',
			$html
		);
		$this->assertStringContainsString('<h2 id="install-it">Install <em>it</em></h2>', $html);
		$this->assertStringContainsString('<h4 id="too-deep">Too deep</h4>', $html);
		$this->assertStringContainsString('<h2 id="setup-1">Setup</h2>', $html);
		$this->assertStringNotContainsString('Too deep</a>', $html);
	}

	public function testItFollowsHeadingPermalinks(): void
	{
		$config = <<<'PHP'
			<?php

			declare(strict_types=1);

			use Blush\Markdown\MarkdownConfig;
			use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

			return new MarkdownConfig(
				options: ['heading_permalink' => ['id_prefix' => 'h', 'fragment_prefix' => 'h']],
				extensions: [...MarkdownConfig::DEFAULT_EXTENSIONS, HeadingPermalinkExtension::class]
			);
			PHP;

		$html = $this->page($this->app($config), '/guide');

		$this->assertStringContainsString('<a class="component-toc__link" href="#h-install-it">Install it</a>', $html);
		$this->assertStringContainsString('id="h-install-it" href="#h-install-it"', $html);
		$this->assertStringContainsString('<a class="component-toc__link" href="#h-setup-1">Setup</a>', $html);

		// The permalink's anchor holds the id, so the heading gets none.
		$this->assertStringNotContainsString('<h2 id=', $html);
	}

	public function testPagesWithoutOneAreUnchangedAndExcerptsSkipIt(): void
	{
		$app = $this->app();
		$this->writeTemporaryFile('user/content/plain.md', "---\ntitle: Plain\n---\n## A heading\n\nText.\n");

		$this->assertStringContainsString('<h2>A heading</h2>', $this->page($app, '/plain'));

		$guide = $app->container()->make(ContentRepository::class)->named('page', 'guide');

		$this->assertNotNull($guide);
		$this->assertStringStartsWith('<p>Intro words here. Install it', $guide->excerpt());
	}
}
