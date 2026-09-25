<?php

/**
 * Design token tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Theme\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Theme\Token\Contrast;
use Blush\Theme\Token\Token;
use Blush\Theme\Token\TokenSet;

#[CoversClass(TokenSet::class)]
#[CoversClass(Token::class)]
#[CoversClass(Contrast::class)]
final class TokenSetTest extends TestCase
{
	private function set(): TokenSet
	{
		return TokenSet::fromArray([
			'color' => [
				'$type'       => 'color',
				'$description' => 'ignored metadata',
				'bg'          => ['$value' => '#ffffff', '$extensions' => ['blush' => ['modes' => ['dark' => '#111111', 'Bad Mode' => '#000']]]],
				'text'        => ['$value' => '#222222', '$extensions' => ['blush.modes' => ['dark' => '#eeeeee']]],
				'link'        => ['$value' => '{color.text}'],
				'hex'         => ['$value' => ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'hex' => '#ff0000']],
				'p3'          => ['$value' => ['colorSpace' => 'display-p3', 'components' => [1, 0.5, 0], 'alpha' => 0.5]]
			],
			'space'  => ['$type' => 'dimension', 'm' => ['$value' => ['value' => 1, 'unit' => 'rem']]],
			'font'   => ['body' => ['$type' => 'fontFamily', '$value' => ['Open Sans', 'system-ui', 'sans-serif']]],
			'ease'   => ['$type' => 'cubicBezier', '$value' => [0.4, 0, 0.2, 1]],
			'shadow' => ['$type' => 'shadow', '$value' => [
				['offsetX' => '0', 'offsetY' => '1px', 'blur' => '2px', 'spread' => '0', 'color' => '{color.text}'],
				['offsetX' => '0', 'offsetY' => '4px', 'blur' => '8px', 'spread' => '0', 'color' => '#0003', 'inset' => true]
			]],
			'border' => ['$type' => 'border', '$value' => ['width' => '1px', 'style' => 'solid', 'color' => '{color.text}']],
			'gap'    => 'calc({space.m} * 2)',
			'ratio'  => 1.5,
			'odd name!' => 'x'
		]);
	}

	public function testReadsGroupsTypesAndShorthands(): void
	{
		$set = $this->set();

		$this->assertSame('color', $set->get('color.bg')?->type);
		$this->assertSame('--color-bg', $set->get('color.bg')->property());
		$this->assertSame(['dark' => '#111111'], $set->get('color.bg')->modes);
		$this->assertSame(['dark' => '#eeeeee'], $set->get('color.text')?->modes);
		$this->assertNull($set->get('color.$description'));
		$this->assertSame('calc({space.m} * 2)', $set->get('gap')?->value);
		$this->assertSame(['dark'], $set->modes());
		$this->assertSame(['tokens: "odd name!" isn\'t a usable token name.'], $set->problems);
		$this->assertFalse($set->isEmpty());
		$this->assertTrue(new TokenSet()->isEmpty());
	}

	public function testCompilesToCustomProperties(): void
	{
		$css = $this->set()->css();

		$expected = [
			'--color-bg: #ffffff;',
			'--color-link: var(--color-text);',
			'--color-hex: #ff0000;',
			'--color-p3: color(display-p3 1 0.5 0 / 0.5);',
			'--space-m: 1rem;',
			'--font-body: "Open Sans", system-ui, sans-serif;',
			'--ease: cubic-bezier(0.4, 0, 0.2, 1);',
			'--shadow: 0 1px 2px 0 var(--color-text), inset 0 4px 8px 0 #0003;',
			'--border: 1px solid var(--color-text);',
			'--gap: calc(var(--space-m) * 2);',
			'--ratio: 1.5;',
			"@media (prefers-color-scheme: dark) {\n:root:not([data-scheme=\"light\"]) {\n\t--color-bg: #111111;\n\t--color-text: #eeeeee;\n}\n}",
			":root[data-scheme=\"dark\"] {\n\t--color-bg: #111111;\n\t--color-text: #eeeeee;\n}"
		];

		foreach ($expected as $line) {
			$this->assertStringContainsString($line, $css);
		}

		$this->assertStringStartsWith(":root {\n", $css);
		$this->assertSame('', new TokenSet()->css());
	}

	public function testResolvesConcreteValuesThroughAliases(): void
	{
		$set = $this->set();

		$this->assertSame('#222222', $set->value('color.link'));
		$this->assertSame('#eeeeee', $set->value('color.link', 'dark'));
		$this->assertSame('#ffffff', $set->value('color.bg', 'sepia'));
		$this->assertSame('calc(1rem * 2)', $set->value('gap'));
		$this->assertSame('1px solid #222222', $set->value('border'));
		$this->assertNull($set->value('missing'));
	}

	public function testRefusesValuesThatCouldEscapeTheirDeclaration(): void
	{
		$set = TokenSet::fromArray([
			'a' => 'red; } body { display: none',
			'b' => '</style><script>',
			'c' => 'url(x)\\',
			'd' => '{missing}',
			'e' => '{f}',
			'f' => '{e}',
			'g' => true,
			'h' => ['$type' => 'typography', '$value' => ['fontFamily' => 'x']]
		]);

		// Unsafe values are dropped; aliases that don't resolve stay (the
		// browser treats them as unset) and are reported.
		$this->assertSame(":root {\n\t--d: var(--missing);\n\t--e: var(--f);\n\t--f: var(--e);\n}\n", $set->css());
		$this->assertSame([
			'Token "a" has a value that can\'t be used in CSS.',
			'Token "b" has a value that can\'t be used in CSS.',
			'Token "c" has a value that can\'t be used in CSS.',
			'Token "d" refers to a missing token, or to itself.',
			'Token "e" refers to a missing token, or to itself.',
			'Token "f" refers to a missing token, or to itself.',
			'Token "g" has a value that can\'t be used in CSS.',
			'Token "h" has a value that can\'t be used in CSS.'
		], $set->compileProblems());
	}

	public function testOverridesReplaceTokensInEveryMode(): void
	{
		$theme = $this->set();
		$site  = $theme->merge(TokenSet::fromArray(['color' => ['text' => '#000000', 'new' => ['$value' => '#123456']]]));

		$this->assertSame('#000000', $site->value('color.text', 'dark'));
		$this->assertSame('color', $site->get('color.text')?->type);
		$this->assertSame('#111111', $site->value('color.bg', 'dark'));

		$entry = TokenSet::fromArray(['color' => ['bg' => '#fafafa', 'text' => ['$value' => '#010101', '$extensions' => ['blush' => ['modes' => ['dark' => '#fefefe']]]]]])->over($theme);

		$this->assertSame(['dark' => '#fafafa'], $entry->get('color.bg')?->modes);
		$this->assertSame(['dark' => '#fefefe'], $entry->get('color.text')?->modes);
		$this->assertStringContainsString(":root[data-scheme=\"dark\"] {\n\t--color-bg: #fafafa;\n\t--color-text: #fefefe;\n}", $entry->css());
	}

	public function testMeasuresContrast(): void
	{
		$this->assertSame(21.0, round((float) Contrast::ratio('#000', '#ffffff'), 2));
		$this->assertSame(1.0, Contrast::ratio('#abcdef', '#ABCDEF'));
		$this->assertEqualsWithDelta(4.54, (float) Contrast::ratio('#767676', 'rgb(255, 255, 255)'), 0.01);
		$this->assertEqualsWithDelta(4.54, (float) Contrast::ratio('#767676ff', 'rgba(255 255 255 / 1)'), 0.01);
		$this->assertNull(Contrast::ratio('oklch(50% 0.1 20)', '#fff'));
	}
}
