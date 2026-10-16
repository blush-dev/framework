<?php

/**
 * Neutral tokens tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Admin\Theme\AdminToken;
use Blush\Admin\Theme\AdminTokens;
use Blush\Admin\Theme\TokenType;
use Blush\Core\Framework;

/**
 * Neutral is the admin's own tokens (`tokens.css`), and its
 * `tokens.json` says the same, for previews, contrast checks, and as the
 * example every other theme is written from (D-827). These keep the two
 * alike.
 */
#[CoversClass(AdminTokens::class)]
final class NeutralTokensTest extends TestCase
{
	/**
	 * Returns what `tokens.css`'s `:root` declares, by custom property.
	 *
	 * @return array<string, string>
	 */
	private static function declared(): array
	{
		$css = (string) file_get_contents(Framework::path('resources/admin/css/tokens.css'));
		$css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

		preg_match('/^:root \{(.*?)^\}/ms', $css, $root);
		preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/', $root[1] ?? '', $declarations, PREG_SET_ORDER);

		$declared = [];

		foreach ($declarations as [, $property, $value]) {
			$declared[$property] = trim((string) preg_replace('/\s+/', ' ', $value));
		}

		return $declared;
	}

	public function testTokensCssDeclaresEveryToken(): void
	{
		$declared = self::declared();

		foreach (AdminToken::cases() as $token) {
			$this->assertArrayHasKey($token->property(), $declared, "--{$token->property()} is in tokens.css.");
		}
	}

	public function testNeutralsTokensJsonIsTokensCss(): void
	{
		$declared = self::declared();
		$neutral  = AdminTokens::load(Framework::path('resources/admin-themes/neutral'));

		foreach (AdminToken::cases() as $token) {
			// Neutral's families are the platform's (D-568), which a list of names can't say.
			if ($token->type() === TokenType::FontFamily) {
				$this->assertFalse($neutral->has($token), "Neutral leaves {$token->value} to the platform.");

				continue;
			}

			// The chrome's are the page's until a theme sets them (D-878).
			if ($token->fallback() !== null) {
				$this->assertFalse($neutral->has($token), "Neutral leaves {$token->value} to the page's.");
				$this->assertSame("var(--{$token->fallback()->property()})", $declared[$token->property()] ?? null, "{$token->value} is {$token->fallback()->value} in tokens.css.");

				continue;
			}

			$this->assertSame($declared[$token->property()] ?? null, $neutral->get($token), "{$token->value} is the same in both.");
		}
	}
}
