<?php

/**
 * Theme command tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\ActivateTheme;
use Blush\Console\Commands\CheckTheme;
use Blush\Console\Commands\CreateTheme;
use Blush\Console\Commands\ExplainView;
use Blush\Console\Commands\ListComponents;
use Blush\Console\Commands\ListThemes;
use Blush\Console\Commands\PublishThemes;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\InstalledExtensions;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeChecker;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeReport;

#[CoversClass(ListThemes::class)]
#[CoversClass(ActivateTheme::class)]
#[CoversClass(CreateTheme::class)]
#[CoversClass(InstalledExtensions::class)]
#[CoversClass(CheckTheme::class)]
#[CoversClass(ExplainView::class)]
#[CoversClass(ListComponents::class)]
#[CoversClass(PublishThemes::class)]
#[CoversClass(ThemeChecker::class)]
#[CoversClass(ThemeReport::class)]
final class ThemeCommandsTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Runs a command in a freshly booted site.
	 *
	 * @param string|list<string> $command
	 */
	private function command(string|array $command): CommandResult
	{
		$app = $this->scratchApplication(['APP_ENV' => 'production']);
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	private function root(): string
	{
		return $this->temporaryDirectory();
	}

	public function testListsThemes(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova", "version": "2.1.0", "parent": "blush/default"}');
		$this->writeTemporaryFile('extensions/acme/broken/theme.json', '{broken');

		$result = $this->command('theme:list');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('#\* blush/default\s*\|\s*Default\s*\|\s*default\s*\|\s*1\.0\.0\s*\|\s*\|\s*framework#', $result->output);
		$this->assertMatchesRegularExpression('#  acme/nova\s*\|\s*Nova\s*\|\s*nova\s*\|\s*2\.1\.0\s*\|\s*blush/default\s*\|\s*local#', $result->output);
		$this->assertStringContainsString('extensions/acme/broken:', $result->output . $result->errors);

		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/gone');\n");

		$this->assertSame(ExitCode::Failure, $this->command('theme:list')->exitCode);
	}

	public function testCreatesThemes(): void
	{
		$result = $this->command(['theme:new', 'acme/nova', '--parent=blush/default', '--label=Nova Theme']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertSame(
			[
				'$schema'   => '../../../vendor/blush-dev/framework/resources/schemas/theme.schema.json',
				'name'      => 'acme/nova',
				'label'     => 'Nova Theme',
				'namespace' => 'acme-nova',
				'version'   => '1.0.0',
				'parent'    => 'blush/default',
				'styles'    => ['style.css']
			],
			json_decode((string) file_get_contents($this->root() . '/extensions/acme/nova/theme.json'), true)
		);
		$this->assertFileExists($this->root() . '/extensions/acme/nova/style.css');
		$this->assertStringContainsString('theme:activate acme/nova', $result->output);
		$this->assertSame(ExitCode::Success, $this->command(['theme:new', 'acme/dusk-mode', '--namespace=dusk'])->exitCode);
		$this->assertStringContainsString('"label": "Dusk Mode"', (string) file_get_contents($this->root() . '/extensions/acme/dusk-mode/theme.json'));
		$this->assertStringContainsString('"namespace": "dusk"', (string) file_get_contents($this->root() . '/extensions/acme/dusk-mode/theme.json'));

		$this->writeTemporaryFile('extensions/other/stray/readme.md', 'Not an extension.');

		$this->assertSame(ExitCode::Failure, $this->command(['theme:new', 'other/stray'])->exitCode, 'The folder is taken.');
		$this->assertSame(ExitCode::Success, $this->command(['theme:new', 'other/nova', '--namespace=other-nova'])->exitCode, 'Another vendor\'s nova has its own folder.');
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/nova'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/nova2', '--namespace=acme-nova'])->exitCode, 'The namespace is taken.');
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'nova'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'Bad Slug'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'blush/default'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/x', '--namespace=app'])->exitCode, 'A reserved namespace.');
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/kid', '--parent=missing'])->exitCode);

		// Names and namespaces are checked against plugins and icon packs too.
		$this->writeTemporaryFile('extensions/acme/shop/plugin.json', '{"name": "acme/shop", "label": "Shop", "namespace": "shop", "provider": "Acme\\\\Shop\\\\ShopServiceProvider"}');
		$this->writeTemporaryFile('extensions/acme/brands/icons.json', '{"name": "acme/brands", "label": "Brands", "namespace": "brands"}');

		$plugin = $this->command(['theme:new', 'other/shop', '--namespace=shop']);

		$this->assertSame(ExitCode::Invalid, $plugin->exitCode);
		$this->assertStringContainsString('The "acme/shop" plugin already has the namespace "shop"', $plugin->errors);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/shop', '--namespace=free'])->exitCode, 'A plugin has the name.');
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'other/x', '--namespace=brands'])->exitCode, 'An icon pack has the namespace.');
	}

	public function testActivatesThemes(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/dusk/theme.json', '{"name": "acme/dusk", "label": "Dusk", "namespace": "dusk"}');

		$bootstrap = new Bootstrap(Paths::fromRoot($this->root()), ['APP_ENV' => 'production']);
		$bootstrap->compile();

		$result = $this->command(['theme:activate', 'acme/nova']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertFileDoesNotExist($bootstrap->compiledPath(CompiledCache::Config));
		$this->assertFileDoesNotExist($bootstrap->compiledPath(CompiledCache::Themes));
		$this->assertSame('acme/nova', $this->config()->active);

		$this->assertSame(ExitCode::Success, $this->command(['theme:activate', 'acme/dusk'])->exitCode);
		$this->assertSame('acme/dusk', $this->config()->active);

		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Theme\\ThemeConfig::fromArray(['active' => getenv('THEME') ?: 'acme/nova']);\n");

		$this->assertSame(ExitCode::Failure, $this->command(['theme:activate', 'acme/dusk'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:activate', 'missing'])->exitCode);
	}

	public function testActivatingClearsTheThemeSavedInTheAdmin(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/dusk/theme.json', '{"name": "acme/dusk", "label": "Dusk", "namespace": "dusk"}');
		$this->writeTemporaryFile('user/data/settings.json', '{"app": {"name": "Field Notes"}, "theme": {"active": "acme/dusk"}}');

		$result = $this->command(['theme:activate', 'acme/nova']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Cleared the theme activated in the admin', $result->output);
		$this->assertSame(['app' => ['name' => 'Field Notes']], json_decode((string) file_get_contents($this->root() . '/user/data/settings.json'), true), 'Other settings stay.');
		$this->assertSame('acme/nova', $this->config()->active);
	}

	private function config(): ThemeConfig
	{
		$config = require $this->root() . '/config/theme.php';

		$this->assertInstanceOf(ThemeConfig::class, $config);

		return $config;
	}

	public function testChecksTheDefaultTheme(): void
	{
		$result = $this->command(['theme:check', '--strict']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->output);
		$this->assertStringContainsString('Checked the "blush/default" theme: 0 error(s), 0 warning(s), 0 notice(s).', $result->output);
	}

	public function testChecksReportProblems(): void
	{
		$this->writeTemporaryFile('extensions/acme/rough/theme.json', json_encode([
			'name'      => 'acme/rough',
			'label'     => 'Rough',
			'namespace' => 'rough',
			'provider'  => 'Nope\\Provider',
			'require' => ['blush-dev/framework' => '^2.0'],
			'settings'  => ['size' => ['type' => 'number', 'default' => 1]]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('extensions/acme/rough/theme.yaml', "name: acme/rough\nlabel: Shadowed\nnamespace: rough");
		$this->writeTemporaryFile('extensions/acme/rough/views/layouts/base.php', '<!DOCTYPE html><html><body><div><?= $template->section("content") ?></div></body></html>');
		$this->writeTemporaryFile('user/data/theme.json', '{"settings": {"size": "big"}}');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": 1}');

		$result = $this->command(['theme:check', 'acme/rough', '--strict']);
		$output = $result->output . $result->errors;

		$this->assertSame(ExitCode::Failure, $result->exitCode);

		$expected = [
			'warning theme extensions/acme/other:',
			'warning manifest: theme.yaml is ignored; the "acme/rough" theme\'s theme.json wins',
			'error   provider: The "acme/rough" theme\'s provider Nope\\Provider isn\'t a service provider class',
			'notice  require:',
			'warning setting size:',
			'error   layout: The base layout\'s <html> has no lang attribute.',
			'error   layout: The base layout needs one <main> landmark; it has 0.',
			'error   layout: The base layout needs a skip link',
			'warning layout: The base layout has no <header> landmark.',
			'warning layout: The base layout has no <footer> landmark.'
		];

		foreach ($expected as $line) {
			$this->assertStringContainsString($line, $output);
		}

		$this->assertStringNotContainsString('notice', $this->command(['theme:check', 'acme/rough'])->output);
		$this->assertStringContainsString('error   manifest: The "acme/missing" theme is not installed.', $this->command(['theme:check', 'acme/missing'])->output);
	}

	public function testListsComponents(): void
	{
		$this->writeTemporaryFile('resources/views/components/app-badge.php', 'badge');
		$this->writeTemporaryFile('resources/views/components/loose.php', 'loose');

		$result = $this->command('component:list');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertMatchesRegularExpression('#\| app/badge\s*\| Badge\s*\|\s*\|\s*\|\s*\| resources/views/components/app-badge\.php#', $result->output);
		$this->assertMatchesRegularExpression('#\| blush/callout\s*\| Callout\s*\| yes\s*\| Blush\\\\Component\\\\Callout\s*\| [^|]*\| \(its own\)#', $result->output);
		$this->assertMatchesRegularExpression('#\| blush/embed\s*\| Embed\s*\| yes\s*\| Blush\\\\Component\\\\Embed#', $result->output);
		$this->assertStringNotContainsString('can\'t render', $result->output . $result->errors);
		$this->assertStringContainsString('resources/views/components/loose.php isn\'t named for a component, so nothing renders it. Name it {namespace}-loose.php.', $result->output . $result->errors);
	}

	public function testComponentsWithoutTemplatesAreFlagged(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\Component\\OrphanProvider::class]);\n");

		$list = $this->command('component:list');

		$this->assertMatchesRegularExpression('#\| app/orphan\s*\| Orphan\s*\| yes\s*\| .*Orphan\s*\|\s*\| \(none\)#', $list->output);
		$this->assertStringContainsString('"app/orphan" has no components/app-orphan.php template, so it can\'t render.', $list->output . $list->errors);

		$check = $this->command('theme:check');

		$this->assertStringContainsString('warning component app/orphan: The "app/orphan" component (Blush\\Tests\\Fixtures\\Component\\Orphan) has no components/app-orphan.php template in the chain.', $check->output);
	}

	public function testThemeCheckFlagsComponentTemplatesNotNamedForAComponent(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/card.php', 'card');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/nova-badge.php', 'badge');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/blush-callout.php', 'callout');
		$this->writeTemporaryFile('resources/views/components/loose.php', 'not the theme\'s');

		$check = $this->command(['theme:check', 'acme/nova']);

		$this->assertStringContainsString('warning component card: components/card.php isn\'t named for a component, so it never renders; name it components/nova-card.php.', $check->output);
		$this->assertStringNotContainsString('nova-badge', $check->output);
		$this->assertStringNotContainsString('blush-callout', $check->output);
		$this->assertStringNotContainsString('loose', $check->output);
	}

	public function testAnotherThemesComponentsAreLeftOut(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\Component\\NovaProvider::class]);\n");
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');

		$missing = 'The "nova/badge" component (no class) has no components/nova-badge.php template in the chain.';

		$this->assertStringNotContainsString('nova/badge', $this->command(['theme:check', 'blush/default'])->output);
		$this->assertStringNotContainsString('nova/badge', $this->command(['component:list', '--theme=blush/default'])->output);
		$this->assertStringContainsString($missing, $this->command(['theme:check', 'acme/nova'])->output);
		$this->assertStringContainsString('nova/badge', $this->command(['component:list', '--theme=acme/nova'])->output);
	}

	public function testThemeCheckNotesRegisteredComponentsWithoutALabel(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\Component\\NovaProvider::class]);\n");
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/nova-badge.php', 'badge');

		$notice = 'notice  component nova/badge: The "nova/badge" component has no label; add "components.badge.label" to the theme\'s lang/ catalog.';

		$this->assertStringContainsString($notice, $this->command(['theme:check', 'acme/nova', '--strict'])->output);

		$this->writeTemporaryFile('extensions/acme/nova/lang/en.json', '{"components": {"badge": {"label": "Badge"}}}');

		$this->assertStringNotContainsString('nova/badge', $this->command(['theme:check', 'acme/nova', '--strict'])->output);
	}

	public function testThemeCheckFlagsVariantProblems(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\Component\\NovaProvider::class]);\n");
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova", "variants": {"callout": ["bordered", "Bad"], "nova/nothing": ["wide"], "nova/badge": ["pill"], "image": ["polaroid", "Bad", "inline-left"]}}');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/nova-badge.php', 'badge');
		$this->writeTemporaryFile('extensions/acme/nova/lang/en.json', '{"components": {"badge": {"label": "Badge"}, "callout": {"variants": {"bordered": {"label": "Bordered"}}}}}');

		$check = $this->command(['theme:check', 'acme/nova', '--strict'])->output;

		$this->assertStringContainsString('warning variants nova/nothing: theme.json lists variants for "nova/nothing", which isn\'t a component.', $check);
		$this->assertStringContainsString('warning variants blush/callout: theme.json lists a variant of "blush/callout" that isn\'t valid', $check);
		$this->assertStringContainsString('notice  variants nova/badge: The "pill" variant of "nova/badge" has no label; add "components.badge.variants.pill.label" to the theme\'s lang/ catalog.', $check);
		$this->assertStringNotContainsString('"bordered" variant', $check);
		$this->assertStringContainsString('warning variants image: theme.json lists an image variant that isn\'t valid', $check);
		$this->assertStringContainsString('notice  variants image: The "polaroid" image variant has no label; add "images.variants.polaroid.label" to the theme\'s lang/ catalog.', $check);
		$this->assertStringNotContainsString('"inline-left" image variant', $check, 'The default theme has its label.');
		$this->assertStringNotContainsString('"image", which isn\'t a component', $check);
		$this->assertMatchesRegularExpression('#\| blush/callout\s*\| Callout\s*\| yes\s*\| Blush\\\\Component\\\\Callout\s*\| info, tip, warning, danger, bordered\s*\|#', $this->command(['component:list', '--theme=acme/nova'])->output);
	}

	public function testExplainsWhichViewWins(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova/views/single.php', '');
		$this->writeTemporaryFile('resources/views/single.php', '');

		$result = $this->command(['theme:why', 'single', '--theme=acme/nova']);

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('#uses    resources/views/single.php\nshadows extensions/acme/nova/views/single.php\nshadows .+resources/themes/default/views/single.php#', $result->output);

		$missing = $this->command(['theme:why', 'nope']);

		$this->assertSame(ExitCode::Failure, $missing->exitCode);
		$this->assertStringContainsString('resources/views/themes/blush/default/nope.php', $missing->output);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:why', '../x'])->exitCode);
	}

	public function testPublishesThemeAssets(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova/style.css', 'nova');
		$this->writeTemporaryFile('extensions/acme/nova/fonts/a.woff2', 'font');
		$this->writeTemporaryFile('extensions/acme/nova/views/single.php', '<?php');
		$this->writeTemporaryFile('extensions/acme/nova/src/Provider.php', '<?php');
		$this->writeTemporaryFile('extensions/acme/nova/.hidden/x.css', '');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": "acme/other", "label": "Other", "namespace": "other"}');
		$this->writeTemporaryFile('extensions/acme/other/o.css', '');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/nova');\n");

		$result = $this->command('theme:publish');
		$public = $this->root() . '/public/themes';

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('acme/nova: copied 2, 0 already current, removed 0.', $result->output);
		$this->assertSame('nova', file_get_contents("{$public}/acme/nova/style.css"));
		$this->assertFileExists("{$public}/acme/nova/fonts/a.woff2");
		$this->assertFileExists("{$public}/blush/default/style.css");
		$this->assertFileDoesNotExist("{$public}/acme/nova/views/single.php");
		$this->assertFileDoesNotExist("{$public}/acme/nova/src/Provider.php");
		$this->assertFileDoesNotExist("{$public}/acme/nova/theme.json");
		$this->assertFileDoesNotExist("{$public}/acme/nova/.hidden/x.css");
		$this->assertDirectoryDoesNotExist("{$public}/acme/other");

		unlink($this->root() . '/extensions/acme/nova/fonts/a.woff2');

		$this->assertStringContainsString('acme/nova: copied 0, 1 already current, removed 1.', $this->command('theme:publish')->output);
		$this->assertFileDoesNotExist("{$public}/acme/nova/fonts/a.woff2");

		$this->command(['theme:publish', '--all']);

		$this->assertFileExists("{$public}/acme/other/o.css");
	}
}
