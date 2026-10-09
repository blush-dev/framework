<?php

/**
 * Inline directive tests.
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
use Blush\Core\AppConfig;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Directive\Inline\Ins;
use Blush\Directive\Inline\Kbd;
use Blush\Directive\Inline\Time;

#[CoversClass(Ins::class)]
#[CoversClass(Kbd::class)]
#[CoversClass(Time::class)]
final class InlineDirectivesTest extends TestCase
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

		$formats = new AppConfig(timezone: 'America/Chicago', dateFormat: 'y-MM-dd', timeFormat: 'HH:mm');
		$this->assertSame('2026-10-06', new Time($formats, '2026-10-06')->formatted, 'A date takes the site\'s date format (D-445).');
		$this->assertSame('14:30', new Time($formats, '14:30')->formatted);
		$this->assertSame('2026-10-06, 14:30', new Time($formats, '2026-10-06T14:30')->formatted);
		$this->assertSame('October 2026', new Time($formats, '2026-10')->formatted, 'A month has no day to format.');
	}

	public function testInsertionsTakeOnlyRealDates(): void
	{
		$this->assertSame('2026-10-06', new Ins('2026-10-06')->machine);
		$this->assertSame('2026-10-06T14:30-05:00', new Ins(' 2026-10-06T14:30-05:00 ')->machine);
		$this->assertNull(new Ins('2026-02-30')->machine);
		$this->assertNull(new Ins('2026-10')->machine, 'A month isn\'t a date.');
		$this->assertNull(new Ins('14:30')->machine);
		$this->assertNull(new Ins('yesterday')->machine);
	}

	public function testTheyRenderInsideSentences(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			A :abbr[CMS]{title="content management system"}, saved with :kbd[Ctrl+S] on :time[Tuesday]{datetime=2026-10-06}.

			Plain :abbr[FAQ] and :kbd[Esc].

			:time[]{datetime=2026-10-06}

			New :badge[Beta]{variant=info} and :badge[Plain], from :cite[The Hobbit], where :dfn[Blush]{title="Blush CMS"} is ~~paid~~ :ins[free]{datetime=2026-10-06 cite=/changes}.

			It prints :samp[Not found], :small[fine print], and :var[x].

			A [plain span]{.note #first} too.
			MD);

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString('<p>A <abbr class="directive-abbr" title="content management system">CMS</abbr>, saved with <kbd class="directive-kbd"><kbd>Ctrl</kbd>+<kbd>S</kbd></kbd> on <time class="directive-time" datetime="2026-10-06">Tuesday</time>.</p>', $html);
		$this->assertStringContainsString('<p>Plain <abbr class="directive-abbr">FAQ</abbr> and <kbd class="directive-kbd">Esc</kbd>.</p>', $html);
		$this->assertStringContainsString('<time class="directive-time" datetime="2026-10-06">October 6, 2026</time>', $html);
		$this->assertStringContainsString('<p>New <span class="directive-badge directive-badge--info">Beta</span> and <span class="directive-badge">Plain</span>, from <cite class="directive-cite">The Hobbit</cite>, where <dfn class="directive-dfn" title="Blush CMS">Blush</dfn> is <del>paid</del> <ins class="directive-ins" datetime="2026-10-06" cite="/changes">free</ins>.</p>', $html);
		$this->assertStringContainsString('<p>It prints <samp class="directive-samp">Not found</samp>, <small class="directive-small">fine print</small>, and <var class="directive-var">x</var>.</p>', $html);
		$this->assertStringContainsString('<p>A <span class="note" id="first">plain span</span> too.</p>', $html);
	}
}
