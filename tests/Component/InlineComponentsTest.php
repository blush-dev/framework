<?php

/**
 * Inline component tests.
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
use Blush\Core\AppConfig;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Component\Inline\Kbd;
use Blush\Component\Inline\Time;

#[CoversClass(Kbd::class)]
#[CoversClass(Time::class)]
final class InlineComponentsTest extends TestCase
{
	use BootsScratchSite;

	public function testKeyCombinationsAreSplit(): void
	{
		$this->assertSame(['Ctrl', 'Shift', 'P'], new Kbd('Ctrl + Shift+P')->keys);
		$this->assertSame(['Enter'], new Kbd('Enter')->keys);
		$this->assertSame(['Ctrl++'], new Kbd('Ctrl++')->keys);
		$this->assertSame(['+'], new Kbd('+')->keys);
		$this->assertSame([], new Kbd()->keys);
	}

	public function testDatesAreCheckedAndShownInTheSitesLanguage(): void
	{
		$app = new AppConfig(timezone: 'America/Chicago', locale: 'en_US');

		$cases = [
			'2026'                    => ['2026', '2026'],
			'2026-10'                 => ['2026-10', 'October 2026'],
			'2026-10-06'              => ['2026-10-06', 'October 6, 2026'],
			'2026-10-06T14:30'        => ['2026-10-06T14:30', 'October 6, 2026 at 2:30 PM'],
			'2026-10-06T19:30Z'       => ['2026-10-06T19:30Z', 'October 6, 2026 at 2:30 PM'],
			'14:30'                   => ['14:30', '2:30 PM'],
			'PT2H30M'                 => ['PT2H30M', 'PT2H30M'],
			'2026-02-30'              => [null, '2026-02-30'],
			'next Tuesday'            => [null, 'next Tuesday'],
			'2026-10-06"><script>'    => [null, '2026-10-06"><script>']
		];

		foreach ($cases as $value => [$machine, $text]) {
			$value = (string) $value;
			$time  = new Time($app, $value);

			$this->assertSame($machine, $time->machine, $value);
			$this->assertSame($text, str_replace("\u{202F}", ' ', $time->formatted), $value);
		}

		$this->assertSame('6. Oktober 2026', new Time(new AppConfig(locale: 'de_DE'), '2026-10-06')->formatted);
	}

	public function testTheyRenderInsideSentences(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			title: Home
			---
			A :abbr[CMS]{title="content management system"}, saved with :kbd[Ctrl+S] on :time[Tuesday]{datetime=2026-10-06}.

			Plain :abbr[FAQ] and :kbd[Esc].

			::time{datetime=2026-10-06}
			MD);

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString('<p>A <abbr class="component-abbr" title="content management system">CMS</abbr>, saved with <kbd class="component-kbd"><kbd>Ctrl</kbd>+<kbd>S</kbd></kbd> on <time class="component-time" datetime="2026-10-06">Tuesday</time>.</p>', $html);
		$this->assertStringContainsString('<p>Plain <abbr class="component-abbr">FAQ</abbr> and <kbd class="component-kbd">Esc</kbd>.</p>', $html);
		$this->assertStringContainsString('<time class="component-time" datetime="2026-10-06">October 6, 2026</time>', $html);
	}
}
