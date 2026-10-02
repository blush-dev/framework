<?php

/**
 * Theme preview tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Theme\PreviewLayout;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\ThemePreview;

#[CoversClass(ThemePreview::class)]
final class ThemePreviewTest extends TestCase
{
	private const array PALETTE = [
		'background' => ['#FFF', '#0f1115'],
		'surface'    => '#f7f8fa',
		'text'       => ['#16181d', '#e8eaef'],
		'muted'      => '#6b7280',
		'accent'     => ['#1f4ed8', '#7aa2ff'],
		'border'     => ['#e5e7eb', '#262b34']
	];

	public function testReadsAPreview(): void
	{
		$preview = ThemePreview::fromArray('acme/nova', ['layout' => 'sidebar', 'type' => ' Serif headings · sans body ', 'palette' => self::PALETTE]);

		$this->assertSame(PreviewLayout::Sidebar, $preview->layout);
		$this->assertSame('Serif headings · sans body', $preview->type);
		$this->assertSame(['#ffffff', '#0f1115'], $preview->palette['background'], 'Short hex is spelled out and lowercased.');
		$this->assertSame(['#f7f8fa', '#f7f8fa'], $preview->palette['surface'], 'One color is both halves.');
		$this->assertSame(ThemePreview::ROLES, array_keys($preview->palette));
	}

	public function testDefaultsToACenteredLayoutWithoutAPalette(): void
	{
		$preview = ThemePreview::fromArray('acme/nova', []);

		$this->assertSame(['layout' => 'centered', 'type' => '', 'palette' => null], $preview->toArray());
	}

	public function testManifestsReadTheirPreview(): void
	{
		$theme = ThemeManifest::fromArray('/themes/nova', ['name' => 'acme/nova', 'label' => 'Nova', 'namespace' => 'nova', 'preview' => ['layout' => 'wide']]);

		$this->assertSame(PreviewLayout::Wide, $theme->preview?->layout);
		$this->assertNull(ThemeManifest::fromArray('/themes/nova', ['name' => 'acme/nova', 'label' => 'Nova', 'namespace' => 'nova'])->preview);
	}

	/**
	 * @return iterable<string, array{array<string, mixed>}>
	 */
	public static function brokenPreviews(): iterable
	{
		yield 'an unknown key'  => [['colors' => []]];
		yield 'an unknown layout' => [['layout' => 'grid']];
		yield 'a type that isn\'t text' => [['type' => 5]];
		yield 'a missing role'  => [['palette' => array_diff_key(self::PALETTE, ['border' => true])]];
		yield 'an unknown role' => [['palette' => [...self::PALETTE, 'link' => '#000']]];
		yield 'a named color'   => [['palette' => [...self::PALETTE, 'text' => 'black']]];
		yield 'three colors'    => [['palette' => [...self::PALETTE, 'text' => ['#000', '#111', '#222']]]];
		yield 'a list palette'  => [['palette' => ['#000']]];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	#[DataProvider('brokenPreviews')]
	public function testRefusesBrokenPreviews(array $data): void
	{
		$this->expectException(ThemeException::class);

		ThemePreview::fromArray('acme/nova', $data);
	}

	public function testManifestsRefuseAPreviewThatIsntAnObject(): void
	{
		$this->expectException(ThemeException::class);

		ThemeManifest::fromArray('/themes/nova', ['name' => 'acme/nova', 'label' => 'Nova', 'namespace' => 'nova', 'preview' => 'wide']);
	}
}
