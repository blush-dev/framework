<?php

/**
 * Avatar tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\View\Avatar;

#[CoversClass(Avatar::class)]
final class AvatarTest extends TestCase
{
	/**
	 * The admin's `initials()` cases (`people.ts`): its rule and this one
	 * must agree.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function names(): array
	{
		return [
			'two words'        => ['Jane Doe', 'JD'],
			'one word'         => ['jane', 'J'],
			'more than two'    => ['Mary Jane Watson', 'MJ'],
			'extra spaces'     => ["  Jane \t Doe  ", 'JD'],
			'beyond ASCII'     => ['élodie Ørsted', 'ÉØ'],
			'beyond the BMP'   => ['𝒜da Lovelace', '𝒜L'],
			'nothing'          => ['', '?'],
			'only spaces'      => ['   ', '?']
		];
	}

	#[DataProvider('names')]
	public function testInitialsMatchTheAdmin(string $name, string $initials): void
	{
		$this->assertSame($initials, new Avatar($name)->initials());
	}

	public function testDrawsASquareBoxTheThemeStyles(): void
	{
		$html = new Avatar('Jane Doe', 64)->html();

		$this->assertStringStartsWith('<svg class="avatar" width="64" height="64" viewBox="0 0 100 100" aria-hidden="true" focusable="false">', $html, 'Hidden beside a printed name.');
		$this->assertStringContainsString('<rect width="100" height="100" style="fill: var(--avatar-background, #e7eaf0)"/>', $html, 'Gray, unless the theme says otherwise.');
		$this->assertStringContainsString('fill: var(--avatar-color, #535b6b); font-family: inherit;', $html);
		$this->assertStringContainsString('>JD</text></svg>', $html);
	}

	public function testALabelNamesTheImage(): void
	{
		$html = new Avatar('Jane <Doe>')->html('Jane "JD" <Doe>');

		$this->assertStringContainsString('width="48" height="48"', $html, 'The default size.');
		$this->assertStringContainsString('role="img" aria-label="Jane &quot;JD&quot; &lt;Doe&gt;"', $html);
		$this->assertStringNotContainsString('aria-hidden', $html);
		$this->assertStringContainsString('>J&lt;</text>', $html, 'Initials are escaped too.');
	}
}
