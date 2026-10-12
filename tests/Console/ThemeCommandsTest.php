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
use Blush\Console\Commands\ListDirectives;
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
use Blush\Tests\SavedSettings;
use Blush\Tests\WritesThemeViews;
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
#[CoversClass(ListDirectives::class)]
#[CoversClass(PublishThemes::class)]
#[CoversClass(ThemeChecker::class)]
#[CoversClass(ThemeReport::class)]
final class ThemeCommandsTest extends TestCase
{
	use BootsScratchSite;
	use SavedSettings;
	use WritesThemeViews;

	/**
	 * Runs a command in a freshly booted site.
	 *
	 * @param string|list<string> $command
	 */
	/**
	 * @param string|list<string> $command
	 * @param list<string>        $answers
	 */
	private function command(string|array $command, array $answers = []): CommandResult
	{
		$app = $this->scratchApplication(['APP_ENV' => 'production']);
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command, $answers);
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
		$this->assertMatchesRegularExpression('#\* blush/default\s+Default\s+default\s+1\.0\.0\s+framework#', $result->output);
		$this->assertMatchesRegularExpression('#  acme/nova\s+Nova\s+nova\s+2\.1\.0\s+blush/default\s+local#', $result->output);
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
				'parent'    => 'blush/default'
			],
			json_decode((string) file_get_contents($this->root() . '/extensions/acme/nova/theme.json'), true)
		);
		$this->assertFileDoesNotExist($this->root() . '/extensions/acme/nova/style.css', 'A child keeps its parent\'s stylesheet (D-618).');
		$this->assertSame(['@@locale' => 'en', '@@domain' => 'acme/nova'], json_decode((string) file_get_contents($this->root() . '/extensions/acme/nova/lang/en.json'), true));
		$this->assertStringContainsString('theme:activate acme/nova', $result->output);
		$this->assertSame(ExitCode::Success, $this->command(['theme:new', 'acme/dusk-mode', '--namespace=dusk'])->exitCode);
		$this->assertStringContainsString('"label": "Dusk Mode"', (string) file_get_contents($this->root() . '/extensions/acme/dusk-mode/theme.json'));
		$this->assertStringContainsString('"namespace": "dusk"', (string) file_get_contents($this->root() . '/extensions/acme/dusk-mode/theme.json'));
		$this->assertStringContainsString('"styles": [', (string) file_get_contents($this->root() . '/extensions/acme/dusk-mode/theme.json'));
		$this->assertFileExists($this->root() . '/extensions/acme/dusk-mode/style.css');

		$this->writeTemporaryFile('extensions/other/stray/readme.md', 'Not an extension.');

		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'other/stray'])->exitCode, 'The folder is taken.');
		$this->assertSame(ExitCode::Success, $this->command(['theme:new', 'other/nova', '--namespace=other-nova'])->exitCode, 'Another vendor\'s nova has its own folder.');
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/nova'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/nova2', '--namespace=acme-nova'])->exitCode, 'The namespace is taken.');
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'nova'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'Bad Slug'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'blush/default'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'acme/x', '--namespace=theme'])->exitCode, 'A reserved namespace.');
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

	public function testAsksForWhatsNotGiven(): void
	{
		$result = $this->command(['theme:new'], ['Bad Name', 'acme/dawn', '', '2', 'A bright theme.', 'y']);
		$file   = $this->root() . '/extensions/acme/dawn/theme.json';

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('can\'t be a theme\'s name', $result->errors, 'Asked again until it can be one.');
		$this->assertSame(
			['name' => 'acme/dawn', 'label' => 'Dawn', 'description' => 'A bright theme.', 'namespace' => 'acme-dawn', 'parent' => 'blush/default'],
			array_intersect_key((array) json_decode((string) file_get_contents($file), true), array_flip(['name', 'label', 'description', 'namespace', 'parent'])),
			'The label made from its name, the default theme listed first after none (D-719).'
		);
		$this->assertSame('acme/dawn', $this->config()->active, 'Activated.');
		$this->assertStringContainsString('wrote  extensions/acme/dawn/theme.json', $result->output);

		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', '-n'])->exitCode, 'The name can\'t be defaulted.');
		$this->assertSame(ExitCode::Success, $this->command(['theme:new', 'acme/noon', '-n'])->exitCode, 'Everything else can.');
		$this->assertStringNotContainsString('description', (string) file_get_contents($this->root() . '/extensions/acme/noon/theme.json'));
		$this->assertSame('acme/dawn', $this->config()->active, 'Not activated without --activate.');

		$flags = $this->command(['theme:new', 'acme/dusk', '--description=Evening.', '--activate', '-n']);

		$this->assertSame(ExitCode::Success, $flags->exitCode, $flags->errors);
		$this->assertStringContainsString('"description": "Evening."', (string) file_get_contents($this->root() . '/extensions/acme/dusk/theme.json'));
		$this->assertSame('acme/dusk', $this->config()->active);
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

	public function testRefusesAThemeWhoseRequirementsArentMet(): void
	{
		$this->writeTemporaryFile('extensions/acme/future/theme.json', '{"name": "acme/future", "require": {"acme/shop": "^2.0"}}');

		$result = $this->command(['theme:activate', 'acme/future']);

		$this->assertSame(ExitCode::Invalid, $result->exitCode);
		$this->assertStringContainsString('The "acme/future" theme can\'t be activated. Needs acme/shop ^2.0 (isn\'t installed).', $result->errors);
		$this->assertFileDoesNotExist($this->root() . '/config/theme.php');
	}

	public function testActivatingClearsTheThemeSavedInTheAdmin(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/dusk/theme.json', '{"name": "acme/dusk", "label": "Dusk", "namespace": "dusk"}');
		$this->writeSettings('{"app": {"name": "Field Notes"}, "theme": {"active": "acme/dusk"}}');

		$result = $this->command(['theme:activate', 'acme/nova']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Cleared the theme activated in the admin', $result->output);
		$this->assertSame(['app' => ['name' => 'Field Notes']], $this->savedSettings(), 'Other settings stay.');
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
		$this->assertStringContainsString('Checked the "blush/default" theme, no problems', $result->output);
	}

	public function testChecksAnInactiveThemesProvider(): void
	{
		$this->writeTemporaryFile('extensions/acme/coded/theme.json', (string) json_encode([
			'name'      => 'acme/coded',
			'label'     => 'Coded',
			'namespace' => 'coded',
			'provider'  => 'Acme\\Coded\\CodedServiceProvider',
			'autoload'  => ['psr-4' => ['Acme\\Coded\\' => 'src/']]
		]));
		$this->writeTemporaryFile('extensions/acme/coded/src/CodedServiceProvider.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Acme\\Coded;\n\nfinal class CodedServiceProvider extends \\Blush\\Core\\ServiceProvider\n{\n}\n");

		$result = $this->command(['theme:check', 'acme/coded']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->output);
		$this->assertStringNotContainsString('provider', $result->output);
	}

	public function testChecksAnInactiveThemeWithItsProviderRunning(): void
	{
		$this->writeTemporaryFile('extensions/acme/classy/theme.json', (string) json_encode([
			'name'      => 'acme/classy',
			'label'     => 'Classy',
			'namespace' => 'classy',
			'provider'  => 'Acme\\Classy\\ClassyServiceProvider',
			'autoload'  => ['psr-4' => ['Acme\\Classy\\' => 'src/']]
		]));
		$this->writeTemporaryFile('extensions/acme/classy/src/ClassyServiceProvider.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Acme\\Classy;\n\nfinal class ClassyServiceProvider extends \\Blush\\Core\\ServiceProvider\n{\n\tpublic function boot(): void\n\t{\n\t\t\$this->container->get(\\Blush\\Component\\ComponentRegistry::class)->register('classy/greeting', Greeting::class);\n\t}\n}\n");
		$this->writeTemporaryFile('extensions/acme/classy/src/Greeting.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Acme\\Classy;\n\nfinal class Greeting extends \\Blush\\Component\\Component\n{\n\tpublic function words(): string\n\t{\n\t\treturn 'Hello from a class';\n\t}\n\n\tpublic function render(): ?\\Blush\\Component\\ComponentView\n\t{\n\t\treturn null;\n\t}\n}\n");
		$this->writeTemporaryFile('extensions/acme/classy/views/components/classy-greeting.php', '<p><?= e($component->words()) ?></p>');
		$this->writeTemporaryFile('extensions/acme/classy/views/welcome.php', "<?php \$template->layout('base'); ?>\n<h1>Welcome</h1>\n<?= \$template->component('classy/greeting') ?>");

		$result = $this->command(['theme:check', 'acme/classy']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->output);
		$this->assertStringNotContainsString('doesn\'t render', $result->output);
		$this->assertMatchesRegularExpression('#notice\s+layout\s+The theme has no layouts\/base view, so pages render in the framework\'s skeleton#', $this->command(['theme:check', 'acme/classy', '--strict'])->output, 'D-632.');
	}

	public function testChecksReportProblems(): void
	{
		$this->writeTemporaryFile('extensions/acme/rough/theme.json', json_encode([
			'name'      => 'acme/rough',
			'label'     => 'Rough',
			'namespace' => 'rough',
			'provider'  => 'Nope\\Provider',
			'version'   => '1.0-final',
			'abandoned' => 'acme/smooth',
			'require'   => ['blush-dev/framework' => '^2.0', 'acme/absent' => '^1.0'],
			'settings'  => ['size' => ['type' => 'number', 'default' => 1]]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('extensions/acme/rough/views/layouts/base.php', '<!DOCTYPE html><html><head><meta charset="utf-8"><?= $template->head() ?></head><body><div><?= $template->section("content") ?></div></body></html>');
		$this->writeSettings('{"acme/rough": {"size": "big"}}');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": 1}');

		$result = $this->command(['theme:check', 'acme/rough', '--strict']);
		$output = $result->output . $result->errors;

		$this->assertSame(ExitCode::Failure, $result->exitCode);

		$expected = [
			'#warning\s+theme extensions\/acme\/other\s#',
			'#error\s+provider\s+The "acme\/rough" theme\'s provider Nope\\\\Provider isn\'t a service provider class#',
			'#warning\s+abandoned\s+The "acme\/rough" theme\: It\'s abandoned; use "acme\/smooth" instead\.#',
			'#warning\s+version\s+The "acme\/rough" theme\'s version, "1\.0\-final", isn\'t one Composer can read#',
			'#warning\s+require\s+Needs acme\/absent \^1\.0 \(isn\'t installed\)\. It can\'t be activated until that\'s fixed\.#',
			'#warning\s+setting size\s#',
			'#error\s+layout\s+The base layout\'s \<html\> has no lang attribute\.#',
			'#layout\s+The base layout\'s \<html\> has no dir attribute#',
			'#error\s+layout\s+The base layout needs one \<main\> landmark; it has 0\.#',
			'#error\s+layout\s+The base layout needs a skip link#',
			'#error\s+layout\s+The base layout doesn\'t print the footer; add \<\?\= \$template\-\>foot\(\) \?\> just before \<\/body\>#',
			'#warning\s+layout\s+The base layout has no \<header\> landmark\.#',
			'#warning\s+layout\s+The base layout has no \<footer\> landmark\.#',
			'#warning\s+layout\s+The base layout prints \<meta charset\> more than once#'
		];

		foreach ($expected as $pattern) {
			$this->assertMatchesRegularExpression($pattern, $output);
		}

		$this->assertStringNotContainsString('notice', $this->command(['theme:check', 'acme/rough'])->output);
		$this->assertMatchesRegularExpression('#error\s+manifest\s+The "acme\/missing" theme is not installed\.#', $this->command(['theme:check', 'acme/missing'])->output);
	}

	public function testListsDirectives(): void
	{
		$this->themeView('directives/loose.php', 'loose');

		$result = $this->command('directive:list');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertMatchesRegularExpression('#core/callout\s+Callout\s+Blush\\\\Directive\\\\Callout\s+[^|]*\s+\(its own\)#', $result->output);
		$this->assertMatchesRegularExpression('#core/embed\s+Embed\s+Blush\\\\Directive\\\\Embed#', $result->output);
		$this->assertStringNotContainsString('can\'t render', $result->output . $result->errors);
		$this->assertStringContainsString('extensions/test/site/views/directives/loose.php isn\'t for a registered directive, so nothing renders it.', $result->output . $result->errors);
	}

	public function testListsComponents(): void
	{
		$this->themeView('components/site-badge.php', 'badge');
		$this->themeView('components/loose.php', 'loose');

		$result = $this->command('component:list');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertMatchesRegularExpression('#site/badge\s+extensions/test/site/views/components/site-badge\.php#', $result->output);
		$this->assertStringNotContainsString('core/callout', $result->output, 'Directives aren\'t components (D-532).');
		$this->assertStringNotContainsString('can\'t render', $result->output . $result->errors);
		$this->assertStringContainsString("extensions/test/site/views/components/loose.php isn't named for a component, so nothing renders it.\n  Name it {namespace}-loose.php.", $result->output . $result->errors);
	}

	public function testDirectivesAndComponentsWithoutTemplatesAreFlagged(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\Component\\OrphanProvider::class, Blush\\Tests\\Fixtures\\Directive\\OrphanProvider::class]);\n");

		$components = $this->command('component:list');

		$this->assertMatchesRegularExpression('#acme/orphan\s+.*Component\\\\Orphan\s+\(none\)#', $components->output);
		$this->assertStringContainsString('"acme/orphan" has no components/acme-orphan template, so it can\'t render.', $components->output . $components->errors);

		$directives = $this->command('directive:list');

		$this->assertMatchesRegularExpression('#acme/orphan\s+Orphan\s+.*Directive\\\\Orphan\s+\(none\)#', $directives->output);
		$this->assertStringContainsString('"acme/orphan" has no directives/acme-orphan template, so it can\'t render.', $directives->output . $directives->errors);

		$check = $this->command('theme:check');

		$this->assertMatchesRegularExpression('#warning\s+component acme\/orphan\s+The "acme\/orphan" component \(Blush\\\\Tests\\\\Fixtures\\\\Component\\\\Orphan\) has no components\/acme\-orphan template in the chain\.#', $check->output);
		$this->assertMatchesRegularExpression('#warning\s+directive acme\/orphan\s+The "acme\/orphan" directive \(Blush\\\\Tests\\\\Fixtures\\\\Directive\\\\Orphan\) has no directives\/acme\-orphan template in the chain\.#', $check->output);
	}

	public function testThemeCheckFlagsTemplatesNotForADirectiveOrComponent(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/card.php', 'card');
		$this->writeTemporaryFile('extensions/acme/nova/views/components/nova-badge.php', 'badge');
		$this->writeTemporaryFile('extensions/acme/nova/views/directives/core-callout.php', 'callout');
		$this->writeTemporaryFile('extensions/acme/nova/views/directives/callout-warning.php', 'a variant');
		$this->writeTemporaryFile('extensions/acme/nova/views/directives/tabs.php', 'tabs');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": "acme/other", "label": "Other", "namespace": "other"}');
		$this->writeTemporaryFile('extensions/acme/other/views/components/loose.php', 'not in the chain');

		$check = $this->command(['theme:check', 'acme/nova']);

		$this->assertMatchesRegularExpression('#warning\s+component card\.php\s+components\/card\.php isn\'t named for a component, so it never renders; name it components\/nova\-card\.php\.#', $check->output);
		$this->assertMatchesRegularExpression('#warning\s+directive tabs\.php\s+directives\/tabs\.php isn\'t for a registered directive, so it never renders\. Themes can\'t add directives \(D\-532\)\: make it a component \(components\/nova\-tabs\.php\) for the theme\'s templates\.#', $check->output);
		$this->assertStringNotContainsString('nova-badge', $check->output);
		$this->assertStringNotContainsString('core-callout', $check->output);
		$this->assertStringNotContainsString('callout-warning', $check->output);
		$this->assertStringNotContainsString('loose', $check->output);
	}

	public function testThemeCheckFlagsEntryTemplatesWithoutAView(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\View\\TemplatesProvider::class]);\n");

		$check = $this->command('theme:check');

		$this->assertMatchesRegularExpression('#warning\s+template template\-gone\s+The "template\-gone" template \(Gone\) is declared for entries but has no template\-gone view in the chain, so the editor doesn\'t offer it\.#', $check->output);
		$this->assertStringNotContainsString('template single', $check->output);
	}

	public function testAnotherThemesComponentsAreLeftOut(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(providers: [Blush\\Tests\\Fixtures\\Component\\NovaProvider::class]);\n");
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');

		$missing = 'The "nova/badge" component (Blush\\Tests\\Fixtures\\Component\\Orphan) has no components/nova-badge template in the chain.';

		$this->assertStringNotContainsString('nova/badge', $this->command(['theme:check', 'blush/default'])->output);
		$this->assertStringNotContainsString('nova/badge', $this->command(['component:list', '--theme=blush/default'])->output);
		$this->assertStringContainsString($missing, $this->command(['theme:check', 'acme/nova'])->output);
		$this->assertStringContainsString('nova/badge', $this->command(['component:list', '--theme=acme/nova'])->output);
	}

	public function testThemeCheckFlagsVariantProblems(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova", "variants": {"callout": ["bordered", "Bad"], "nova/nothing": ["wide"], "button": ["pill"], "image": ["polaroid", "Bad", "inline-left"]}}');
		$this->writeTemporaryFile('extensions/acme/nova/lang/en.json', '{"directives": {"callout": {"variants": {"bordered": {"label": "Bordered"}}}}}');

		$check = $this->command(['theme:check', 'acme/nova', '--strict'])->output;

		$this->assertMatchesRegularExpression('#warning\s+variants nova\/nothing\s+theme\.json lists variants for "nova\/nothing", which isn\'t a directive\.#', $check);
		$this->assertMatchesRegularExpression('#warning\s+variants core\/callout\s+theme\.json lists a variant of "core\/callout" that isn\'t valid#', $check);
		$this->assertMatchesRegularExpression('#notice\s+variants core\/button\s+The "pill" variant of "core\/button" has no label; add "directives\.button\.variants\.pill\.label" to the theme\'s lang\/ catalog\.#', $check);
		$this->assertStringNotContainsString('"bordered" variant', $check);
		$this->assertMatchesRegularExpression('#warning\s+variants image\s+theme\.json lists an image variant that isn\'t valid#', $check);
		$this->assertMatchesRegularExpression('#notice\s+variants image\s+The "polaroid" image variant has no label; add "images\.variants\.polaroid\.label" to the theme\'s lang\/ catalog\.#', $check);
		$this->assertMatchesRegularExpression('#notice\s+variants image\s+The "inline\-left" image variant has no label#', $check, 'The default theme isn\'t in the chain to label it (D-632).');
		$this->assertStringNotContainsString('"image", which isn\'t a directive', $check);
		$this->assertMatchesRegularExpression('#core/callout\s+Callout\s+Blush\\\\Directive\\\\Callout\s+info, tip, warning, danger, bordered\s+#', $this->command(['directive:list', '--theme=acme/nova'])->output);
	}

	public function testExplainsWhichViewWins(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova/views/single.php', '');
		$this->writeTemporaryFile('extensions/acme/kid/theme.json', '{"name": "acme/kid", "label": "Kid", "namespace": "kid", "parent": "acme/nova"}');
		$this->writeTemporaryFile('extensions/acme/kid/views/single.php', '');

		$result = $this->command(['theme:why', 'single', '--theme=acme/kid']);

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('#uses     extensions/acme/kid/views/single\.php\n  notice  shadows  extensions/acme/nova/views/single\.php\n  notice  shadows  .+resources/views/single\.php#', $result->output);

		$missing = $this->command(['theme:why', 'nope']);

		$this->assertSame(ExitCode::Failure, $missing->exitCode);
		$this->assertStringContainsString('resources/views/nope.php', $missing->output);
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
		$this->assertStringContainsString('acme/nova  2 copied, 0 already current, 0 removed', $result->output);
		$this->assertSame('nova', file_get_contents("{$public}/acme/nova/style.css"));
		$this->assertFileExists("{$public}/acme/nova/fonts/a.woff2");
		$this->assertFileDoesNotExist("{$public}/blush/default/style.css", 'The default theme isn\'t in the chain (D-632).');
		$this->assertFileDoesNotExist("{$public}/acme/nova/views/single.php");
		$this->assertFileDoesNotExist("{$public}/acme/nova/src/Provider.php");
		$this->assertFileDoesNotExist("{$public}/acme/nova/theme.json");
		$this->assertFileDoesNotExist("{$public}/acme/nova/.hidden/x.css");
		$this->assertDirectoryDoesNotExist("{$public}/acme/other");

		unlink($this->root() . '/extensions/acme/nova/fonts/a.woff2');

		$this->assertStringContainsString('acme/nova  0 copied, 1 already current, 1 removed', $this->command('theme:publish')->output);
		$this->assertFileDoesNotExist("{$public}/acme/nova/fonts/a.woff2");

		$this->command(['theme:publish', '--all']);

		$this->assertFileExists("{$public}/acme/other/o.css");
	}
}
