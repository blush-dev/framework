<?php

/**
 * Locales tests.
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
use Blush\Translation\Locales;

#[CoversClass(Locales::class)]
final class LocalesTest extends TestCase
{
	public function testListsLocalesByLanguageWithRegionsUnderEach(): void
	{
		$options = Locales::options();
		$values  = array_column($options, 'value');
		$at      = array_flip($values);

		$this->assertSame(['value' => 'en', 'label' => 'English', 'hint' => null, 'depth' => 0], $options[$at['en']]);
		$this->assertSame(['value' => 'en_US', 'label' => 'English (United States)', 'hint' => null, 'depth' => 1], $options[$at['en_US']]);
		$this->assertSame(['value' => 'de_AT', 'label' => 'Deutsch (Österreich)', 'hint' => 'German (Austria)', 'depth' => 1], $options[$at['de_AT']], 'Named in its own language, then in English.');
		$this->assertSame('Français', $options[$at['fr']]['label'], 'The first letter is a capital.');
		$this->assertSame('日本語', $options[$at['ja']]['label']);
		$this->assertLessThan($at['ja'], $at['de'], 'Ordered by the English names (German, Japanese), whatever the script.');
		$this->assertGreaterThan($at['en'], $at['en_US'], 'A region sits under its language.');
		$this->assertLessThan($at['fr'], $at['en_US'], 'Languages are alphabetical, each with its regions.');
		$this->assertLessThan($at['zh_Hans_CN'], $at['zh_Hans'], 'A script comes before its regions.');
		$this->assertNotContains('en_US_POSIX', $values, 'Variants are left out.');
		$this->assertSame($values, array_values(array_unique($values)));
	}
}
