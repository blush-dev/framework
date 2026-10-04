<?php

/**
 * Date format tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Clock;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Clock\DateFormat;
use Blush\Clock\DateStyle;
use Blush\Config\InvalidConfig;
use Blush\Core\AppConfig;

#[CoversClass(DateFormat::class)]
#[CoversClass(DateStyle::class)]
final class DateFormatTest extends TestCase
{
	private static function when(): DateTimeImmutable
	{
		return new DateTimeImmutable('2026-10-04 19:30:00', new DateTimeZone('UTC'));
	}

	/**
	 * ICU puts a narrow space before AM and PM.
	 */
	private static function format(string $locale, ?string $date, ?string $time = null): string
	{
		return str_replace("\u{202F}", ' ', DateFormat::format(self::when(), $locale, 'America/Chicago', $date, $time));
	}

	public function testStylesFollowTheLanguage(): void
	{
		$this->assertSame('October 4, 2026', self::format('en_US', 'long'));
		$this->assertSame('4. Oktober 2026', self::format('de_DE', 'long'));
		$this->assertSame('2:30 PM', self::format('en_US', null, 'short'));
		$this->assertSame('14:30', self::format('de_DE', null, 'short'));
	}

	public function testStylesCanBeGivenAsTheEnum(): void
	{
		$when = self::when();

		$this->assertSame('10/4/26', DateFormat::format($when, 'en_US', 'America/Chicago', DateStyle::Short));
		$this->assertSame('October 4, 2026 at 14:30', str_replace("\u{202F}", ' ', DateFormat::format($when, 'en_US', 'America/Chicago', DateStyle::Long, 'HH:mm')));
		$this->assertSame(['full', 'long', 'medium', 'short'], array_column(DateStyle::cases(), 'value'));
	}

	public function testPatternsAreFixedButNamedInTheLanguage(): void
	{
		$this->assertSame('2026-10-04', self::format('en_US', 'y-MM-dd'));
		$this->assertSame('4 octobre 2026', self::format('fr_FR', 'd MMMM y'));
		$this->assertSame('14:30', self::format('en_US', null, 'HH:mm'));
	}

	public function testADateAndTimeAreJoinedAsTheLanguageJoinsThem(): void
	{
		$this->assertSame('October 4, 2026 at 2:30 PM', self::format('en_US', 'long', 'short'));
		$this->assertSame('4. Oktober 2026 um 14:30', self::format('de_DE', 'long', 'short'));
		$this->assertSame('October 4, 2026 at 14:30', self::format('en_US', 'long', 'HH:mm'), 'A pattern takes its style\'s place.');
		$this->assertSame('2026-10-04, 14:30', self::format('en_US', 'y-MM-dd', 'HH:mm'), 'Two patterns join as medium does.');
	}

	public function testPatternsAreCheckedMoreCloselyThanIcuChecksThem(): void
	{
		foreach (['long', 'short', 'MMMM d, y', "d 'de' MMMM 'de' y", "h 'o''clock'", 'EEEE'] as $format) {
			$this->assertNull(DateFormat::problem($format), $format);
		}

		foreach (['', '   ', 'MMMM d, y at h:mm', "d 'de MMMM", 'jj:mm', '---', "MMMM\ny", str_repeat('d', 101)] as $format) {
			$this->assertNotNull(DateFormat::problem($format), $format);
		}
	}

	public function testTheMenuShowsEachFormatAsItReadsWithoutRepeats(): void
	{
		$options = DateFormat::options('date', self::when(), 'en_US', 'America/Chicago');
		$labels  = array_column($options, 'label', 'value');

		$this->assertSame(['full', 'long', 'medium', 'short'], array_slice(array_column($options, 'value'), 0, 4));
		$this->assertSame('October 4, 2026', $labels['long'] ?? null);
		$this->assertSame('2026-10-04', $labels['y-MM-dd'] ?? null);
		$this->assertArrayNotHasKey('MMMM d, y', $labels, 'Long already reads that way in English.');
		$this->assertSame(array_unique($labels), $labels);
		$this->assertSame(['From the language', 'Fixed'], array_values(array_unique(array_column($options, 'group'))));
		$this->assertContains('HH:mm', array_column(DateFormat::options('time', self::when(), 'en_US', 'America/Chicago'), 'value'));
	}

	public function testTheAppConfigTakesOnlyFormatsThatHold(): void
	{
		$app = AppConfig::fromArray(['dateFormat' => 'd MMMM y', 'timeFormat' => 'HH:mm']);

		$this->assertSame(['d MMMM y', 'HH:mm'], [$app->dateFormat, $app->timeFormat]);
		$this->assertSame(['long', 'short'], [new AppConfig()->dateFormat, new AppConfig()->timeFormat]);

		$this->expectException(InvalidConfig::class);
		new AppConfig(dateFormat: 'MMMM d at y');
	}
}
