<?php

/**
 * Progress and meter directive tests.
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
use Blush\Directive\MeasureNumbers;
use Blush\Directive\Meter;
use Blush\Directive\Progress;

#[CoversClass(MeasureNumbers::class)]
#[CoversClass(Meter::class)]
#[CoversClass(Progress::class)]
final class MeasureDirectivesTest extends TestCase
{
	use BootsScratchSite;

	public function testNumbersAreWrittenForMachinesAndPeople(): void
	{
		$this->assertSame(['12', '0.5', '0', '1250.25', '0'], array_map(MeasureNumbers::attribute(...), [12.0, 0.5, 0.0, 1250.25, -0.0]));
		$this->assertSame('1,250.5', new MeasureNumbers('en_US')->number(1250.5));
		$this->assertSame('1.250,5', new MeasureNumbers('de_DE')->number(1250.5));
		$this->assertSame('24%', new MeasureNumbers('en_US')->percent(0.24));
		$this->assertSame("24\u{a0}%", new MeasureNumbers('fr_FR')->percent(0.24));
	}

	public function testProgressIsClampedOrIndeterminate(): void
	{
		$app = new AppConfig();

		$books = new Progress($app, 12, 50);
		$this->assertSame(['12', '50', '24%', '12', '50'], [$books->valueAttribute, $books->maxAttribute, $books->percent, $books->valueText, $books->maxText]);
		$this->assertFalse($books->isPercent());

		$this->assertSame(['100', '100%'], [new Progress($app, 140)->valueAttribute, new Progress($app, 140)->percent]);
		$this->assertSame('0', new Progress($app, -5)->valueAttribute);
		$this->assertSame(['30', '100'], [new Progress($app, 30, -1)->valueAttribute, new Progress($app, 30, -1)->maxAttribute]);
		$this->assertTrue(new Progress($app, 30)->isPercent());

		$working = new Progress($app);
		$this->assertSame([null, '', ''], [$working->valueAttribute, $working->percent, $working->valueText]);
	}

	public function testMeterRangesAreKeptInOrder(): void
	{
		$app = new AppConfig();

		$meter = new Meter($app, 7, 0, 10, low: 8, high: 3, optimum: 20);
		$this->assertSame(['7', '0', '10', '3', '8', '10'], [$meter->valueAttribute, $meter->minAttribute, $meter->maxAttribute, $meter->lowAttribute, $meter->highAttribute, $meter->optimumAttribute]);
		$this->assertSame(['70%', '7', '10'], [$meter->percent, $meter->valueText, $meter->maxText]);
		$this->assertFalse($meter->isPercent());

		$plain = new Meter($app, 62);
		$this->assertSame([null, null, null], [$plain->lowAttribute, $plain->highAttribute, $plain->optimumAttribute]);
		$this->assertTrue($plain->isPercent());

		// A range that isn't one is 0–100.
		$bad = new Meter($app, 50, 10, 5);
		$this->assertSame(['0', '100', '50'], [$bad->minAttribute, $bad->maxAttribute, $bad->valueAttribute]);
		$this->assertSame('-5', new Meter($app, -9, -5, 5)->valueAttribute);
	}

	public function testTheyRenderLabeledFromMarkdown(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			::progress[Reading challenge]{value=12 max=50}

			::progress{value=30}

			::progress

			::meter[Battery]{value=62 low=20 high=80 optimum=100}
			MD);

		$app = $this->scratchApplication();
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertMatchesRegularExpression('#<label class="directive-progress">\s*<span class="directive-progress__label">Reading challenge</span>\s*<progress class="directive-progress__bar" max="50" value="12">12 of 50</progress>\s*<span class="directive-progress__value">12 of 50</span>\s*</label>#', $html);
		$this->assertStringContainsString('<progress class="directive-progress__bar" max="100" value="30" aria-label="Progress">30%</progress>', $html);
		$this->assertStringContainsString('<progress class="directive-progress__bar" max="100" aria-label="Progress"></progress>', $html);
		$this->assertStringContainsString('<meter class="directive-meter__gauge" value="62" min="0" max="100" low="20" high="80" optimum="100">62%</meter>', $html);
		$this->assertStringContainsString('<span class="directive-meter__label">Battery</span>', $html);
	}
}
