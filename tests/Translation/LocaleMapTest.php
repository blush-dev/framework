<?php

/**
 * Locale map tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Translation\LocaleMap;

#[CoversClass(LocaleMap::class)]
final class LocaleMapTest extends TestCase
{
	public function testRecognizesLocaleMaps(): void
	{
		$this->assertTrue(LocaleMap::isMap(['en' => 'About', 'fr-CA' => 'À propos', 'zh_Hant_TW' => '關於']));
		$this->assertFalse(LocaleMap::isMap(['About']));
		$this->assertFalse(LocaleMap::isMap([]));
		$this->assertFalse(LocaleMap::isMap(['en' => 'About', 'count' => 'x']));
		$this->assertFalse(LocaleMap::isMap(['en' => ['nested']]));
		$this->assertFalse(LocaleMap::isMap('About'));
	}

	public function testPicksTextWithCatalogFallback(): void
	{
		$map = ['en' => 'About', 'fr' => 'À propos', 'fr-CA' => 'À propos (CA)'];

		$this->assertSame('À propos (CA)', LocaleMap::text($map, 'fr_CA', 'en_US'));
		$this->assertSame('À propos', LocaleMap::text($map, 'fr_BE', 'en_US'));
		$this->assertSame('About', LocaleMap::text($map, 'de', 'en_US'));
		$this->assertSame('Über', LocaleMap::text(['de' => 'Über', 'es' => 'Acerca'], 'ja', 'en_US'));
		$this->assertSame('Plain', LocaleMap::text('Plain', 'fr', 'en'));
		$this->assertNull(LocaleMap::text(['a', 'b'], 'fr', 'en'));
		$this->assertNull(LocaleMap::text(3, 'fr', 'en'));
	}

	public function testResolvesMapsAtAnyDepth(): void
	{
		$data = [
			'label' => ['en' => 'Hi', 'fr' => 'Salut'],
			'items' => [['title' => ['en' => 'One', 'fr' => 'Un']], 'plain'],
			'count' => 2
		];

		$this->assertSame(
			['label' => 'Salut', 'items' => [['title' => 'Un'], 'plain'], 'count' => 2],
			LocaleMap::resolve($data, 'fr', 'en')
		);
	}
}
