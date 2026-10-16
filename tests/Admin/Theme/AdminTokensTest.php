<?php

/**
 * Admin tokens tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Admin\Theme\AdminToken;
use Blush\Admin\Theme\AdminTokens;
use Blush\Admin\Theme\BackgroundImage;
use Blush\Admin\Theme\ColorValue;
use Blush\Admin\Theme\CssColor;
use Blush\Admin\Theme\FontFace;
use Blush\Admin\Theme\FontFamily;
use Blush\Auth\ColorScheme;
use Blush\Extension\ExtensionException;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(AdminTokens::class)]
#[CoversClass(BackgroundImage::class)]
#[CoversClass(AdminToken::class)]
#[CoversClass(ColorValue::class)]
#[CoversClass(CssColor::class)]
#[CoversClass(FontFace::class)]
#[CoversClass(FontFamily::class)]
final class AdminTokensTest extends TestCase
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	public function testTokensAreNamedForTheirPlaceInTokensJson(): void
	{
		$this->assertSame('color-background', AdminToken::Background->property());
		$this->assertSame('color-surface-subtle', AdminToken::SurfaceSubtle->property());
		$this->assertSame('color-text', AdminToken::Text->property(), 'A default key drops from the name.');
		$this->assertSame('border-color', AdminToken::BorderColor->property());
		$this->assertSame('border-radius-small', AdminToken::BorderRadiusSmall->property());
		$this->assertSame('box-shadow-color', AdminToken::BoxShadowColor->property());
		$this->assertSame('font-family-ui', AdminToken::FontFamilyUi->property(), 'typography. drops too.');
		$this->assertSame('font-size-page-title', AdminToken::FontSizePageTitle->property());
		$this->assertSame('letter-spacing-entry-title', AdminToken::LetterSpacingEntryTitle->property());
		$this->assertSame('padding-inline', AdminToken::PaddingInline->property());
		$this->assertSame(AdminToken::Surface, AdminToken::at('color.surface'));
		$this->assertNull(AdminToken::at('spacing.huge'));
	}

	public function testReadsTokensAndWritesTheirStylesheet(): void
	{
		$tokens = AdminTokens::fromArray([
			'$schema' => 'admin-tokens.v1.schema.json',
			'color'   => [
				'background' => 'light-dark(#F6F2EC, #15120e)',
				'surface'    => '#fff',
				'accent'     => ['default' => 'oklch(55% 0.1 200)']
			],
			'border'     => ['radius' => ['small' => '8px', 'large' => '0']],
			'typography' => [
				'fontFamily'    => ['ui' => ['Karla', 'sans-serif'], 'title' => ['Newsreader']],
				'fontWeight'    => ['entryTitle' => 600],
				'letterSpacing' => ['pageTitle' => '-.014em']
			],
			'padding' => ['inline' => '26px']
		], $this->temporaryDirectory());

		$this->assertSame('light-dark(#f6f2ec, #15120e)', $tokens->get(AdminToken::Background));
		$this->assertSame('#fff', $tokens->get(AdminToken::Surface), 'A group\'s default may be the group itself.');
		$this->assertNull($tokens->get(AdminToken::SurfaceSubtle), 'What it leaves out is Neutral\'s.');

		$css = $tokens->css(':root[data-admin-theme="acme/harbor"]');

		$this->assertStringContainsString(":root[data-admin-theme=\"acme/harbor\"] {\n", $css);
		$this->assertStringContainsString("\t--color-background: light-dark(#f6f2ec, #15120e);\n", $css);
		$this->assertStringContainsString("\t--color-accent: oklch(55% 0.1 200);\n", $css);
		$this->assertStringContainsString("\t--border-radius-large: 0;\n", $css);
		$this->assertStringContainsString("\t--font-family-ui: \"Karla\", sans-serif;\n", $css);
		$this->assertStringContainsString("\t--font-family-title: \"Newsreader\", var(--font-system);\n", $css, 'A stack without a generic family falls back to the platform\'s.');
		$this->assertStringContainsString("\t--font-weight-entry-title: 600;\n", $css);
		$this->assertStringContainsString("\t--letter-spacing-page-title: -.014em;\n", $css);
		$this->assertStringContainsString("\t--padding-inline: 26px;\n", $css);
		$this->assertSame('', new AdminTokens()->css(':root'), 'No tokens, no rule.');
	}

	/**
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function refused(): array
	{
		return [
			'not a token'       => [['color' => ['sidebar' => '#fff']], '"color.sidebar", which isn\'t a token'],
			'layout'            => [['space' => ['gutter' => '40px']], '"space.gutter", which isn\'t a token'],
			'not a color'       => [['color' => ['background' => 'url(evil.png)']], '"color.background" must be a color'],
			'CSS in a color'    => [['color' => ['background' => '#fff; } body { display: none']], '"color.background" must be a color'],
			'one color of two'  => [['color' => ['background' => 'light-dark(#fff, nope)']], '"color.background" must be a color'],
			'too big'           => [['padding' => ['row' => '60px']], '"padding.row" must be from 10px to 20px'],
			'no unit'           => [['typography' => ['fontSize' => ['base' => '14']]], '"typography.fontSize.base" must be from 12px to 16px'],
			'other unit'        => [['border' => ['radius' => ['small' => '1rem']]], '"border.radius.small" must be from 0px to 24px'],
			'weight out'        => [['typography' => ['fontWeight' => ['entryTitle' => 900]]], 'must be a weight from 400 to 700'],
			'display too light' => [['typography' => ['fontWeight' => ['display' => 300]]], 'must be a weight from 400 to 900'],
			'shadow style'      => [['boxShadow' => ['style' => 'long']], '"boxShadow.style" must be one of "soft", "hard"'],
			'shadow geometry'   => [['boxShadow' => ['style' => '4px 4px 0 #000']], '"boxShadow.style" must be one of'],
			'family not a list' => [['typography' => ['fontFamily' => ['ui' => 'Karla']]], 'must be a list of font family names'],
			'family with CSS'   => [['typography' => ['fontFamily' => ['ui' => ['Karla"; color: red']]]], 'must be a list of font family names']
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	#[DataProvider('refused')]
	public function testRefusesWhatIsntATokensValue(array $data, string $message): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage($message);

		AdminTokens::fromArray($data, $this->temporaryDirectory());
	}

	public function testReadsFontFacesInsideTheTheme(): void
	{
		$this->writeTemporaryFile('fonts/karla.woff2', 'wOF2');

		$tokens = AdminTokens::fromArray(['typography' => ['fontFace' => [
			['fontFamily' => 'Karla', 'src' => 'fonts/karla.woff2', 'fontWeight' => '200 800', 'unicodeRange' => 'u+0000-00ff, U+0131']
		]]], $this->temporaryDirectory());

		$this->assertCount(1, $tokens->faces);
		$this->assertSame(<<<'CSS'
			@font-face {
				font-family: "Karla";
				font-style: normal;
				font-weight: 200 800;
				font-display: swap;
				src: url("fonts/karla.woff2") format("woff2");
				unicode-range: U+0000-00FF, U+0131;
			}
			CSS, $tokens->faces[0]->css());
		$this->assertStringStartsWith('@font-face {', $tokens->css(':root'));
	}

	/**
	 * @return array<string, array{array<string, mixed>, string}>
	 */
	public static function badFaces(): array
	{
		return [
			'outside the theme' => [['fontFamily' => 'Karla', 'src' => '../secret.woff2'], 'needs a "src": a .woff2 file inside the theme'],
			'absolute'          => [['fontFamily' => 'Karla', 'src' => '/etc/karla.woff2'], 'needs a "src"'],
			'not woff2'         => [['fontFamily' => 'Karla', 'src' => 'fonts/karla.ttf'], 'needs a "src"'],
			'missing file'      => [['fontFamily' => 'Karla', 'src' => 'fonts/gone.woff2'], 'isn\'t in the theme'],
			'no family'         => [['src' => 'fonts/karla.woff2'], 'needs a "fontFamily"'],
			'unknown key'       => [['fontFamily' => 'Karla', 'src' => 'fonts/karla.woff2', 'fontDisplay' => 'block'], '"fontDisplay", which isn\'t one of'],
			'bad weight'        => [['fontFamily' => 'Karla', 'src' => 'fonts/karla.woff2', 'fontWeight' => 'bold'], '"fontWeight" must be a weight'],
			'bad range'         => [['fontFamily' => 'Karla', 'src' => 'fonts/karla.woff2', 'unicodeRange' => 'all'], '"unicodeRange" must be code points']
		];
	}

	/**
	 * @param array<string, mixed> $face
	 */
	#[DataProvider('badFaces')]
	public function testRefusesAFontFaceItCantServe(array $face, string $message): void
	{
		$this->writeTemporaryFile('fonts/karla.woff2', 'wOF2');

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage($message);

		AdminTokens::fromArray(['typography' => ['fontFace' => [$face]]], $this->temporaryDirectory());
	}

	public function testWritesTheShadowsOfTheStyleItPicks(): void
	{
		$hard = AdminTokens::fromArray(['boxShadow' => ['color' => '#000', 'style' => 'hard'], 'typography' => ['fontWeight' => ['display' => 900]]], $this->temporaryDirectory())->css(':root');

		$this->assertStringContainsString("\t--box-shadow-style: hard;\n", $hard);
		$this->assertStringContainsString("\t--font-weight-display: 900;\n", $hard);
		$this->assertStringContainsString("\t--shadow-1: 4px 4px 0 var(--box-shadow-color);\n", $hard, 'Resting surfaces stand off the page too.');
		$this->assertStringContainsString("\t--shadow-2: 4px 4px 0 var(--box-shadow-color);\n", $hard);
		$this->assertStringContainsString("\t--shadow-3: 8px 8px 0 var(--box-shadow-color);\n", $hard);

		$soft = AdminTokens::fromArray(['boxShadow' => ['style' => 'soft']], $this->temporaryDirectory())->css(':root');

		$this->assertStringNotContainsString('--shadow-1', $soft, 'Soft shadows are the admin\'s own.');
	}

	public function testLaysOneThemesTokensOverAnothers(): void
	{
		$base  = AdminTokens::fromArray(['color' => ['background' => '#eee', 'text' => '#111']], $this->temporaryDirectory());
		$theme = AdminTokens::fromArray(['color' => ['background' => '#000']], $this->temporaryDirectory());
		$both  = $base->with($theme);

		$this->assertSame('#000', $both->get(AdminToken::Background));
		$this->assertSame('#111', $both->get(AdminToken::Text));
	}

	public function testTheChromeIsThePagesUntilAThemeSetsIt(): void
	{
		$base  = AdminTokens::fromArray(['color' => ['surface' => '#fff', 'accent' => '#00f']], $this->temporaryDirectory());
		$theme = AdminTokens::fromArray(['color' => ['surface' => '#eee', 'chrome' => ['accent' => '#ff0']]], $this->temporaryDirectory());
		$both  = $base->with($theme);

		$this->assertSame('color-chrome', AdminToken::Chrome->property());
		$this->assertSame('color-chrome-text-secondary', AdminToken::ChromeTextSecondary->property());
		$this->assertSame('#eee', $both->get(AdminToken::Chrome), 'Left out, it follows the theme\'s own surface.');
		$this->assertFalse($both->has(AdminToken::Chrome));
		$this->assertSame('#ff0', $both->get(AdminToken::ChromeAccent));
		$this->assertStringNotContainsString('--color-chrome:', $theme->css(':root'), 'Its stylesheet leaves the reference to tokens.css.');
	}

	public function testReadsAGroundTexture(): void
	{
		$tokens = AdminTokens::fromArray([
			'backgroundImage' => "radial-gradient(circle,  light-dark(rgb(20 24 29 / 16%), rgb(255 255 255 / 7%)) 1px, transparent 1.4px)",
			'backgroundSize'  => '20px 20px'
		], $this->temporaryDirectory());

		$this->assertSame('background-image', AdminToken::BackgroundImage->property());
		$this->assertStringContainsString("\t--background-image: radial-gradient(circle, light-dark(rgb(20 24 29 / 16%), rgb(255 255 255 / 7%)) 1px, transparent 1.4px);\n\t--background-size: 20px 20px;\n", $tokens->css(':root'), 'Its spaces collapse.');
	}

	/**
	 * @return iterable<string, array{string, ?string}>
	 */
	public static function images(): iterable
	{
		yield 'none'               => ['none', 'none'];
		yield 'two hatches'        => ['repeating-linear-gradient(45deg, rgba(61,52,46,.06) 0 1px, transparent 1px 6px), repeating-linear-gradient(-45deg, #3d342e0a 0 1px, transparent 1px 6px)', 'repeating-linear-gradient(45deg, rgba(61,52,46,.06) 0 1px, transparent 1px 6px), repeating-linear-gradient(-45deg, #3d342e0a 0 1px, transparent 1px 6px)'];
		yield 'a position'         => ['radial-gradient(ellipse at top left in oklch, oklch(70% 0.1 200), transparent 60%)', 'radial-gradient(ellipse at top left in oklch, oklch(70% 0.1 200), transparent 60%)'];
		yield 'a file'             => ['url(https://example.com/a.png)', null];
		yield 'a file in a list'   => ['linear-gradient(#000, #fff), url(a.png)', null];
		yield 'a file inside'      => ['linear-gradient(url(a.png), #fff)', null];
		yield 'another token'      => ['linear-gradient(var(--color-accent), #fff)', null];
		yield 'a way out'          => ['linear-gradient(#000, #fff); color: red', null];
		yield 'a way out of rules' => ['linear-gradient(#000, #fff)} body {', null];
		yield 'unclosed'           => ['linear-gradient(#000, #fff', null];
		yield 'closed twice'       => ['linear-gradient(#000, #fff))', null];
		yield 'a gradient inside'  => ['linear-gradient(linear-gradient(#000, #fff), #fff)', null];
		yield 'a color name'       => ['linear-gradient(red, #fff)', null];
		yield 'a bare color'       => ['#fff', null];
		yield 'a string'           => ['linear-gradient("a", #fff)', null];
		yield 'an escape'          => ['linear-gradient(\\75rl(a), #fff)', null];
		yield 'empty'              => ['', null];
	}

	#[DataProvider('images')]
	public function testReadsOnlyGradientsAsAnImage(string $value, ?string $css): void
	{
		$this->assertSame($css, BackgroundImage::parse($value));
	}

	public function testReadsABackgroundSize(): void
	{
		$this->assertSame('20px 20px', BackgroundImage::size(' 20px  20px '));
		$this->assertSame('auto', BackgroundImage::size('auto'));
		$this->assertSame('50% auto', BackgroundImage::size('50% auto'));
		$this->assertNull(BackgroundImage::size('cover; color: red'));
		$this->assertNull(BackgroundImage::size('20px 20px 20px'));
		$this->assertNull(BackgroundImage::size('20em'));
	}

	public function testReadsBackWhatTheCacheKept(): void
	{
		$tokens = AdminTokens::fromArray(['color' => ['background' => 'light-dark(#fff, #000)']], $this->temporaryDirectory());

		$this->assertSame($tokens->css(':root'), AdminTokens::fromCache($tokens->toArray(), $this->temporaryDirectory())->css(':root'));
	}

	/**
	 * @return array<string, array{string, ?string, ?array{int, int, int}}>
	 */
	public static function colors(): array
	{
		return [
			'hex'               => ['#2F5FE0', '#2f5fe0', [47, 95, 224]],
			'short hex'         => ['#fff', '#fff', [255, 255, 255]],
			'hex with alpha'    => ['#00000080', '#00000080', [0, 0, 0]],
			'rgb spaces'        => ['rgb(17 20 27 / 9%)', 'rgb(17 20 27 / 9%)', [17, 20, 27]],
			'rgba commas'       => ['rgba(0, 0, 0, 0.5)', 'rgba(0 0 0 / 0.5)', [0, 0, 0]],
			'hsl'               => ['hsl(0 100% 50%)', 'hsl(0 100% 50%)', [255, 0, 0]],
			'oklch white'       => ['oklch(100% 0 0)', 'oklch(100% 0 0)', [255, 255, 255]],
			'oklch blue'        => ['oklch(0.52 0.2 264)', 'oklch(0.52 0.2 264)', null],
			'transparent'       => ['transparent', 'transparent', [0, 0, 0]],
			'a name'            => ['rebeccapurple', null, null],
			'a variable'        => ['var(--color-text)', null, null],
			'color-mix'         => ['color-mix(in srgb, red, blue)', null, null],
			'four channels'     => ['rgb(1 2 3 4)', null, null],
			'nested'            => ['rgb(calc(1) 2 3)', null, null]
		];
	}

	/**
	 * @param ?array{int, int, int} $rgb
	 */
	#[DataProvider('colors')]
	public function testReadsTheColorsItKnows(string $value, ?string $css, ?array $rgb): void
	{
		$color = CssColor::parse($value);

		$this->assertSame($css, $color?->css);

		if ($color !== null && $rgb !== null) {
			$this->assertSame($rgb, [(int) round($color->red * 255), (int) round($color->green * 255), (int) round($color->blue * 255)]);
		}
	}

	public function testWorksOutContrast(): void
	{
		$white = CssColor::parse('#fff');
		$black = CssColor::parse('#000');

		$this->assertNotNull($white);
		$this->assertNotNull($black);
		$this->assertEqualsWithDelta(21.0, $white->contrast($black), 0.001);
		$this->assertEqualsWithDelta(1.0, $white->contrast($white), 0.001);
	}

	public function testAColorValueIsOneOrAPairInLightDark(): void
	{
		$pair = ColorValue::parse('light-dark(rgb(0, 0, 0), #ffffff)');

		$this->assertNotNull($pair);
		$this->assertSame('light-dark(rgb(0 0 0), #ffffff)', $pair->css(), 'The comma inside rgb() isn\'t the split.');
		$this->assertSame('#ffffff', $pair->in(ColorScheme::Dark)->css);
		$this->assertSame('rgb(0 0 0)', $pair->in(ColorScheme::Light)->css);
		$this->assertSame('#abc', ColorValue::parse('light-dark(#abc, #abc)')?->css(), 'The same twice is one.');
		$this->assertNull(ColorValue::parse('light-dark(#abc)'));
	}
}
