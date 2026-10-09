<?php

/**
 * Button directive tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Directive\Button;
use Blush\Directive\IconPosition;

#[CoversClass(Button::class)]
#[CoversClass(IconPosition::class)]
final class ButtonTest extends TestCase
{
	use BootsScratchSite;

	private function render(string $markdown): string
	{
		$this->writeTemporaryFile('user/content/index.md', "---\nid: d680e8a8-54a7-cbad-6d49-0c445cba2eba\ntitle: Home\n---\n{$markdown}\n");

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		// Just the entry's content, with each icon shortened to its name.
		$start = strpos($html, '<div class="entry__content">');
		$html  = substr($html, (int) $start, (int) strpos($html, '</article>') - (int) $start);

		return (string) preg_replace('#<svg [^>]*class="directive-icon"[^>]*>.*?</svg>#s', '[icon]', $html);
	}

	public function testButtonsAreLinksWithOptionalIcons(): void
	{
		$html = $this->render(<<<'MD'
			:button[Get started]{url=/start}

			:button[Download]{url=/file.pdf icon=download variant=secondary .wide}

			:button[Next]{url=/next icon=arrow-right iconPosition=end}
			MD);

		$this->assertStringContainsString('<a class="directive-button" href="http://localhost/start"><span class="directive-button__text">Get started</span></a>', $html);
		$this->assertStringContainsString('<a class="directive-button directive-button--secondary wide" href="http://localhost/file.pdf">[icon]<span class="directive-button__text">Download</span></a>', $html);
		$this->assertStringContainsString('<a class="directive-button" href="http://localhost/next"><span class="directive-button__text">Next</span>[icon]</a>', $html);
	}

	public function testIconOnlyButtonsAreNamedByTheirLabel(): void
	{
		$html = $this->render(<<<'MD'
			:button[Share this post]{url=/share icon=share-2 iconOnly}

			:button[Unknown icon]{url=/x icon=nope iconOnly}
			MD);

		$this->assertStringContainsString('<a class="directive-button directive-button--icon-only" aria-label="Share this post" title="Share this post" href="http://localhost/share">[icon]</a>', $html);

		// Without its icon, it shows its text.
		$this->assertStringContainsString('<a class="directive-button" href="http://localhost/x"><span class="directive-button__text">Unknown icon</span></a>', $html);
	}

	public function testButtonsNeedTextAndASafeLink(): void
	{
		$html = $this->render(<<<'MD'
			::button{url=/no-label}

			:button[No link]

			:button[Sneaky]{url="javascript:alert(1)"}

			Read the :button[docs]{url=/docs variant=primary} first.
			MD);

		$this->assertStringNotContainsString('no-label', $html);
		$this->assertStringNotContainsString('No link', $html);
		$this->assertStringNotContainsString('Sneaky', $html);

		// Inline, and a variant it doesn't have (`primary` is its Default)
		// renders as Default.
		$this->assertStringContainsString('<p>Read the <a class="directive-button" href="http://localhost/docs"><span class="directive-button__text">docs</span></a> first.</p>', $html);
	}
}
