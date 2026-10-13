<?php

/**
 * Admin themes tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\AdminTheme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\AdminTheme\AdminTheme;
use Blush\AdminTheme\AdminThemeCache;
use Blush\AdminTheme\AdminThemeConfig;
use Blush\AdminTheme\AdminThemeContrast;
use Blush\AdminTheme\AdminThemeDiscovery;
use Blush\AdminTheme\AdminThemes;
use Blush\AdminTheme\AdminThemeSource;
use Blush\Config\InvalidConfig;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionState;
use Blush\Support\PhpArrayFile;
use Blush\Tests\BootsScratchSite;

#[CoversClass(AdminTheme::class)]
#[CoversClass(AdminThemes::class)]
#[CoversClass(AdminThemeDiscovery::class)]
#[CoversClass(AdminThemeCache::class)]
#[CoversClass(AdminThemeConfig::class)]
#[CoversClass(AdminThemeContrast::class)]
final class AdminThemesTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A local theme, one from Composer, a broken one, and one taking a
	 * framework theme's name.
	 */
	private function themes(): AdminThemes
	{
		$this->writeTemporaryFile('extensions/acme/harbor/admin-theme.json', '{"name": "acme/harbor", "label": "Harbor", "description": "Sea blues", "version": "1.0.0"}');
		$this->writeTemporaryFile('extensions/acme/harbor/tokens.json', '{"color": {"accent": "light-dark(#1d4e89, #8fb8e8)"}}');
		$this->writeTemporaryFile('extensions/acme/broken/admin-theme.json', '{"name": "acme/broken", "label": "Broken"}');
		$this->writeTemporaryFile('extensions/acme/broken/tokens.json', '{"color": {"accent": "red; }"}}');
		$this->writeTemporaryFile('extensions/acme/empty/admin-theme.json', '{"name": "acme/empty"}');
		$this->writeTemporaryFile('extensions/blush/editorial/admin-theme.json', '{"name": "blush/editorial", "label": "Fake"}');
		$this->writeTemporaryFile('extensions/blush/editorial/tokens.json', '{}');
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode(['packages' => [
			['name' => 'acme/dusk', 'type' => 'blush-admin-theme', 'install-path' => '../acme/dusk']
		]]));
		$this->writeTemporaryFile('vendor/acme/dusk/admin-theme.json', '{"label": "Dusk"}');
		$this->writeTemporaryFile('vendor/acme/dusk/tokens.json', '{"color": {"background": "#101010"}}');

		return new AdminThemeDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();
	}

	public function testDiscoversTheFrameworksLocalAndComposerThemes(): void
	{
		$themes = $this->themes();

		$this->assertSame(['acme/dusk', 'acme/harbor', 'blush/editorial', 'blush/neutral'], array_keys($themes->all()));
		$this->assertSame(AdminThemeSource::Framework, $themes->find('blush/neutral')?->source);
		$this->assertSame(AdminThemeSource::Composer, $themes->find('acme/dusk')?->source, 'A Composer theme takes its package\'s name.');
		$this->assertSame('Editorial', $themes->find('blush/editorial')?->label, 'A local theme can\'t take a framework theme\'s name.');
		$this->assertStringContainsString('"blush/editorial" is a framework admin theme\'s name.', $themes->invalid()['extensions/blush/editorial'] ?? '');
		$this->assertStringContainsString('"color.accent" must be a color', $themes->invalid()['extensions/acme/broken'] ?? '');
		$this->assertStringContainsString('it has no tokens.json', $themes->invalid()['extensions/acme/empty'] ?? '');
	}

	public function testWhichThemesAreOnAndOffered(): void
	{
		$themes = $this->themes();

		$this->assertSame(['acme/dusk', 'blush/editorial', 'blush/neutral'], array_keys($themes->enabled()), 'The framework\'s and Composer\'s; a local one only when named (D-390).');
		$this->assertSame(['blush/neutral', 'acme/dusk', 'blush/editorial'], array_map(static fn (AdminTheme $theme): string => $theme->name, $themes->offered()), 'Neutral first, then by label.');

		$named = $themes->withConfig(new AdminThemeConfig(enabled: ['acme/harbor']));

		$this->assertTrue($named->isOffered('acme/harbor'));

		$saved = $themes->withConfig(new AdminThemeConfig(saved: ['acme/harbor']));

		$this->assertSame(['acme/harbor', 'blush/neutral'], array_keys($saved->enabled()), 'The admin\'s list is all of what\'s on, and Neutral always is (D-391).');
	}

	public function testTheThemeAnAccountSees(): void
	{
		$themes = $this->themes()->withConfig(new AdminThemeConfig(default: 'blush/editorial'));

		$this->assertSame('blush/editorial', $themes->inUse()?->name, 'The site\'s default, when it has no choice.');
		$this->assertSame('acme/dusk', $themes->inUse('acme/dusk')?->name, 'Its own choice.');
		$this->assertSame('blush/editorial', $themes->inUse('acme/harbor')?->name, 'A choice that isn\'t offered falls back to the default.');
		$this->assertSame('blush/neutral', $themes->withConfig(new AdminThemeConfig(default: 'acme/gone'))->inUse()?->name, 'Then to Neutral.');
	}

	public function testAPreviewIsNeutralsColorsUnderATheme(): void
	{
		$themes = $this->themes()->withConfig(new AdminThemeConfig(enabled: ['acme/harbor']));
		$harbor = $themes->find('acme/harbor');

		$this->assertNotNull($harbor);
		$this->assertSame([
			'background' => 'light-dark(#f5f6f9, #0b0d12)',
			'surface'    => 'light-dark(#ffffff, #13161d)',
			'accent'     => 'light-dark(#1d4e89, #8fb8e8)',
			'text'       => 'light-dark(#11141b, #e9ebf0)'
		], $themes->preview($harbor));
		$this->assertStringContainsString(":root[data-admin-theme=\"acme/harbor\"] {\n\t--color-accent: light-dark(#1d4e89, #8fb8e8);\n}", $harbor->stylesheet(), 'Its stylesheet holds only what it sets.');
	}

	public function testTheCacheKeepsThemesAndTheirTokens(): void
	{
		$themes = $this->themes();
		$file   = new PhpArrayFile($this->temporaryDirectory() . '/storage/cache/admin-themes.php');
		$cache  = new AdminThemeCache($file);

		$cache->write($themes);
		$read = $cache->read();

		$this->assertNotNull($read);
		$this->assertSame(array_keys($themes->all()), array_keys($read->all()));
		$this->assertSame($themes->find('blush/editorial')?->stylesheet(), $read->find('blush/editorial')?->stylesheet());
		$this->assertSame($themes->invalid(), $read->invalid());
	}

	public function testAnAdminThemeHasNoNamespace(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('has a "namespace", which admin themes don\'t');

		AdminTheme::fromArray('/tmp/x', ['name' => 'acme/x', 'namespace' => 'x'], tokens: []);
	}

	public function testTheDefaultIsAName(): void
	{
		$this->expectException(InvalidConfig::class);

		(void) new AdminThemeConfig(default: 'editorial');
	}

	public function testAThemesNameIsOneExtensionsAcrossKinds(): void
	{
		$this->writeTemporaryFile('extensions/acme/twin/plugin.json', '{"name": "acme/twin", "label": "Twin"}');
		$this->writeTemporaryFile('extensions/acme/twin2/admin-theme.json', '{"name": "acme/twin2"}');
		$this->writeTemporaryFile('extensions/acme/twin2/tokens.json', '{}');
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode(['packages' => [
			['name' => 'acme/twin', 'type' => 'blush-admin-theme', 'install-path' => '../acme/twin']
		]]));
		$this->writeTemporaryFile('vendor/acme/twin/tokens.json', '{}');

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$themes = $app->container()->make(AdminThemes::class);

		$this->assertNull($themes->find('acme/twin'));
		$this->assertStringContainsString('Its name, "acme/twin", is a plugin\'s too.', $themes->invalid()['acme/twin'] ?? '');
	}

	public function testAThemeWhoseRequirementsArentMetIsntOffered(): void
	{
		$this->writeTemporaryFile('extensions/acme/future/admin-theme.json', '{"name": "acme/future", "require": {"blush-dev/framework": "^9.0"}}');
		$this->writeTemporaryFile('extensions/acme/future/tokens.json', '{}');
		$this->writeTemporaryFile('config/admin-themes.php', "<?php\n\nreturn new Blush\\AdminTheme\\AdminThemeConfig(enabled: ['acme/future']);\n");

		$app    = $this->scratchApplication(['APP_ENV' => 'development']);
		$themes = $app->container()->make(AdminThemes::class);

		$this->assertTrue($themes->isEnabled('acme/future'), 'It\'s on.');
		$this->assertFalse($themes->isOffered('acme/future'), 'But no one may choose it.');
		$this->assertArrayHasKey('acme/future', $themes->unmet());
		$this->assertFalse($app->container()->make(ExtensionState::class)->runs('acme/future'));
		$this->assertTrue($app->container()->make(ExtensionState::class)->runs('blush/editorial'));
	}

	public function testTheBundledThemesAreReadable(): void
	{
		$themes = new AdminThemeDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();

		foreach ($themes->all() as $theme) {
			$this->assertSame([], AdminThemeContrast::check($themes->resolved($theme)), "{$theme->label}'s colors are easy to read.");
		}

		$this->assertSame([], $themes->invalid());
	}

	public function testLowContrastIsWarnedOf(): void
	{
		$this->writeTemporaryFile('extensions/acme/faint/admin-theme.json', '{"name": "acme/faint"}');
		$this->writeTemporaryFile('extensions/acme/faint/tokens.json', '{"color": {"text": {"default": "light-dark(#cccccc, #e9ebf0)"}}}');

		$themes = new AdminThemeDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();
		$faint  = $themes->find('acme/faint');

		$this->assertNotNull($faint);

		$warnings = AdminThemeContrast::check($themes->resolved($faint));

		$this->assertCount(2, $warnings, 'On the background and the surface, in light only.');
		$this->assertStringStartsWith('In the light scheme, color.text.default on color.background is 1.', $warnings[0]);
		$this->assertStringEndsWith('under the 4.5:1 it needs.', $warnings[0]);
	}
}
