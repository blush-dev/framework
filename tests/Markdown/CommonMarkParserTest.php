<?php

/**
 * CommonMark parser tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use Blush\Config\InvalidConfig;
use Blush\Event\EventDispatcher;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Markdown\CommonMarkParser;
use Blush\Markdown\Events\MarkdownEnvironmentBuilding;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\MarkdownException;
use Blush\Tests\Fixtures\Markdown\ShoutParser;

#[CoversClass(CommonMarkParser::class)]
#[CoversClass(MarkdownConfig::class)]
#[CoversClass(MarkdownEnvironmentBuilding::class)]
#[CoversClass(MarkdownException::class)]
final class CommonMarkParserTest extends TestCase
{
	private ListenerRegistry $listeners;

	protected function setUp(): void
	{
		$this->listeners = new ListenerRegistry();
	}

	private function parser(MarkdownConfig $config = new MarkdownConfig()): CommonMarkParser
	{
		return new CommonMarkParser($config, new EventDispatcher($this->listeners));
	}

	public function testConvertsMarkdownWithTheDefaultExtensions(): void
	{
		$html = $this->parser()->toHtml("# Hi\n\n~~old~~ https://example.com\n\n| a |\n|---|\n| b |\n\n<b>raw</b>");

		$this->assertStringContainsString('<h1>Hi</h1>', $html);
		$this->assertStringContainsString('<del>old</del>', $html);
		$this->assertStringContainsString('<a href="https://example.com">', $html);
		$this->assertStringContainsString('<table>', $html);
		$this->assertStringContainsString('<b>raw</b>', $html);
	}

	public function testUsesTheConfiguredOptionsExtensionsAndInlineParsers(): void
	{
		$parser = $this->parser(new MarkdownConfig(
			options: ['html_input' => 'escape', 'renderer' => ['soft_break' => '<br />']],
			extensions: [CommonMarkCoreExtension::class, SmartPunctExtension::class],
			inlineParsers: [ShoutParser::class]
		));

		$html = $parser->toHtml("\"quoted\"\nnext !!loud <b>x</b>");

		$this->assertSame("<p>“quoted”<br />next LOUD &lt;b&gt;x&lt;/b&gt;</p>\n", $html);
	}

	public function testListenersCanExtendTheEnvironmentOnce(): void
	{
		$built = 0;

		$this->listeners->listen(MarkdownEnvironmentBuilding::class, static function (MarkdownEnvironmentBuilding $event) use (&$built): void {
			$built++;
			$event->environment->addInlineParser(new ShoutParser());
		});

		$parser = $this->parser();

		$this->assertSame("<p>HEY</p>\n", $parser->toHtml('!!hey'));
		$this->assertSame("<p>YOU</p>\n", $parser->toHtml('!!you'));
		$this->assertSame(1, $built);
	}

	public function testBadOptionsFailOnFirstUse(): void
	{
		$this->expectException(MarkdownException::class);

		$this->parser(new MarkdownConfig(options: ['max_nesting_level' => 'deep']))->toHtml('text');
	}

	public function testConfigAcceptsThe1xKeys(): void
	{
		$config = MarkdownConfig::fromArray([
			'config'         => ['html_input' => 'strip'],
			'extensions'     => [CommonMarkCoreExtension::class],
			'inline_parsers' => [ShoutParser::class]
		]);

		$this->assertSame(['html_input' => 'strip'], $config->options);
		$this->assertSame([ShoutParser::class], $config->inlineParsers);
		$this->assertEquals($config, MarkdownConfig::fromArray($config->toArray()));
		$this->assertSame(MarkdownConfig::DEFAULT_EXTENSIONS, MarkdownConfig::fromArray([])->extensions);
	}

	public function testRejectsClassesOfTheWrongKindOnFirstUse(): void
	{
		$parser = $this->parser(MarkdownConfig::fromArray(['extensions' => [ShoutParser::class]]));

		$this->expectException(MarkdownException::class);
		$this->expectExceptionMessage('MarkdownConfig "extensions"');

		$parser->toHtml('text');
	}

	public function testConfigOptionsMustBeAMap(): void
	{
		$this->expectException(InvalidConfig::class);

		MarkdownConfig::fromArray(['options' => ['a', 'b']]);
	}
}
