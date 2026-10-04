<?php

/**
 * Time zones tests.
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
use Blush\Clock\TimeZones;

#[CoversClass(TimeZones::class)]
final class TimeZonesTest extends TestCase
{
	/**
	 * @return array<string, array{value: string, label: string, hint: ?string, group: ?string, search: string}>
	 */
	private static function byZone(string $date): array
	{
		$options = TimeZones::options(new DateTimeImmutable($date, new DateTimeZone('UTC')));

		return array_combine(array_column($options, 'value'), $options);
	}

	public function testNamesEachZoneByCityWithItsCommonNameAndOffset(): void
	{
		$zones = self::byZone('2026-07-01');

		$this->assertSame(DateTimeZone::listIdentifiers(), array_values(array_intersect(DateTimeZone::listIdentifiers(), array_keys($zones))), 'Every zone, saved by its IANA name.');
		$this->assertCount(count(DateTimeZone::listIdentifiers()), $zones);

		$this->assertSame('Chicago', $zones['America/Chicago']['label']);
		$this->assertSame('America', $zones['America/Chicago']['group']);
		$this->assertSame('Central Time · UTC−5', $zones['America/Chicago']['hint']);
		$this->assertSame('Indianapolis, Indiana', $zones['America/Indiana/Indianapolis']['label']);
		$this->assertSame('India Standard Time · UTC+5:30', $zones['Asia/Kolkata']['hint']);
		$this->assertSame(['UTC', null, null], [$zones['UTC']['label'], $zones['UTC']['group'], $zones['UTC']['hint']]);
	}

	public function testTheOffsetIsTheOneInEffect(): void
	{
		$this->assertSame('Central Time · UTC−6', self::byZone('2026-01-15')['America/Chicago']['hint']);
	}

	public function testSearchesFindAbbreviationsOffsetsAndOldNames(): void
	{
		$search = self::byZone('2026-07-01')['America/Chicago']['search'];

		foreach (['CST', 'CDT', '-05:00', 'UTC-5', 'US/Central'] as $word) {
			$this->assertStringContainsString($word, $search);
		}

		$this->assertStringContainsString('Asia/Calcutta', self::byZone('2026-07-01')['Asia/Kolkata']['search'], 'Through ICU\'s names, which aren\'t always IANA\'s.');
	}

	public function testListsUtcFirstThenRegionsWithCitiesInOrder(): void
	{
		$options = TimeZones::options(new DateTimeImmutable('2026-07-01'));
		$america = array_values(array_filter($options, static fn (array $option): bool => $option['group'] === 'America'));
		$labels  = array_column($america, 'label');
		$at      = array_flip($labels);

		$this->assertSame('UTC', $options[0]['value']);
		$this->assertSame('Africa', $options[1]['group']);
		$this->assertLessThan($at['Chicago'], $at['Buenos Aires, Argentina'], 'By city, not by the zone\'s name.');
	}
}
