<?php

/**
 * Button component tests.
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
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Component\Button;
use Blush\Component\ButtonVariant;
use Blush\Component\IconPosition;

#[CoversClass(Button::class)]
#[CoversClass(ButtonVariant::class)]
#[CoversClass(IconPosition::class)]
final class ButtonTest extends TestCase
{
	use BootsScratchSite;

	private function render(string $markdown): string
	{
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n{$markdown}\n");

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		// Just the entry's content, with each icon shortened to its name.
		$start = strpos($html, '<div class="entry__content">');
		$html  = substr($html, (int) $start, (int) strpos($html, '</article>') - (int) $start);

		return (string) preg_replace('#<svg [^>]*class="component-icon"[^>]*>.*?</svg>#s', '[icon]', $html);
	}

	public function testButtonsAreLinksWithOptionalIcons(): void
	{
		$html = $this->render(<<<'MD'
			::button[Get started]{url=/start}

			::button[Download]{url=/file.pdf icon=download variant=secondary .wide}

			::button[Next]{url=/next icon=arrow-right iconPosition=end}
			MD);

		$this->assertStringContainsString('<a class="component-button component-button--primary" href="http://localhost/start"><span class="component-button__text">Get started</span></a>', $html);
		$this->assertStringContainsString('<a class="component-button component-button--secondary wide" href="http://localhost/file.pdf">[icon]<span class="component-button__text">Download</span></a>', $html);
		$this->assertStringContainsString('<a class="component-button component-button--primary" href="http://localhost/next"><span class="component-button__text">Next</span>[icon]</a>', $html);
	}

	public function testIconOnlyButtonsAreNamedByTheirLabel(): void
	{
		$html = $this->render(<<<'MD'
			::button[Share this post]{url=/share icon=share-2 iconOnly}

			::button[Unknown icon]{url=/x icon=nope iconOnly}
			MD);

		$this->assertStringContainsString('<a class="component-button component-button--primary component-button--icon-only" aria-label="Share this post" title="Share this post" href="http://localhost/share">[icon]</a>', $html);

		// Without its icon, it shows its text.
		$this->assertStringContainsString('<a class="component-button component-button--primary" href="http://localhost/x"><span class="component-button__text">Unknown icon</span></a>', $html);
	}

	public function testButtonsNeedTextAndASafeLink(): void
	{
		$html = $this->render(<<<'MD'
			::button{url=/no-label}

			::button[No link]

			::button[Sneaky]{url="javascript:alert(1)"}

			Read the :button[docs]{url=/docs variant=bogus} first.
			MD);

		$this->assertStringNotContainsString('no-label', $html);
		$this->assertStringNotContainsString('No link', $html);
		$this->assertStringNotContainsString('Sneaky', $html);

		// Inline, and an unknown variant falls back to primary.
		$this->assertStringContainsString('<p>Read the <a class="component-button component-button--primary" href="http://localhost/docs"><span class="component-button__text">docs</span></a> first.</p>', $html);
	}
}
