<?php

/**
 * Plugin requirements tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginManifest;
use Blush\Extension\Requirements;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Extension\Requirement;
use Blush\Extension\RequirementKind;
use Blush\Extension\ExtensionException;
use Blush\Icon\IconPack;
use Blush\Theme\ThemeManifest;

#[CoversClass(Requirements::class)]
#[CoversClass(Requirement::class)]
#[CoversClass(RequirementKind::class)]
#[CoversClass(Plugins::class)]
final class RequirementsTest extends TestCase
{
	/**
	 * A site on Blush 2.1.0 and PHP 8.5.1, with only the `intl` extension.
	 */
	private static function requirements(): Requirements
	{
		return new Requirements('2.1.0', '8.5.1', static fn (string $name): string|false => $name === 'intl' ? '8.5.1' : false);
	}

	/**
	 * @param array<string, string> $requires
	 */
	private static function plugin(string $name, array $requires = [], string $version = '1.0.0'): PluginManifest
	{
		$short = substr($name, (int) strpos($name, '/') + 1);

		return new PluginManifest(
			name: $name,
			label: ucfirst($short),
			namespace: $short,
			provider: 'Acme\\' . ucfirst($short) . '\\Provider',
			source: PluginSource::Local,
			path: "/site/extensions/{$short}",
			version: $version,
			require: $requires
		);
	}

	public function testChecksTheSite(): void
	{
		$plugin = self::plugin('acme/one', ['blush-dev/framework' => '^2.0', 'php' => '>=8.6', 'ext-intl' => '*', 'ext-redis' => '*', 'lib-curl' => '*', 'acme/two' => 'soon']);
		$checks = self::requirements()->check($plugin, [], []);

		$this->assertSame([true, false, true, false, false, false], array_map(static fn (Requirement $check): bool => $check->met, $checks));
		$this->assertSame(['blush', 'php', 'extension', 'extension', 'unknown', 'missing'], array_map(static fn (Requirement $check): string => $check->kind->value, $checks));
		$this->assertSame('PHP >=8.6 (this site runs 8.5.1)', $checks[1]->describe());
		$this->assertSame('the PHP extension redis (isn\'t loaded)', $checks[3]->describe());
		$this->assertSame('isn\'t a version constraint Blush understands', $checks[5]->note);
	}

	public function testReadsAnExtensionVersionAsComposerDoes(): void
	{
		$versions     = ['apcu' => '5.1.24 (Build 3)', 'odd' => 'unknown'];
		$requirements = new Requirements('2.1.0', '8.5.1', static fn (string $name): string|false => $versions[$name] ?? false);
		$checks       = $requirements->check(self::plugin('acme/one', ['ext-apcu' => '^5.1', 'ext-odd' => '>=0']), [], []);

		$this->assertSame([true, true], array_map(static fn (Requirement $check): bool => $check->met, $checks));
		$this->assertFalse($requirements->check(self::plugin('acme/one', ['ext-odd' => '>=1']), [], [])[0]->met);
	}

	/**
	 * Checks a reports plugin's requirement of a shop at 2.3.0.
	 *
	 * @param array<string, true> $running
	 */
	private static function shop(string $constraint, array $running): Requirement
	{
		$installed = ['acme/shop' => self::plugin('acme/shop', version: '2.3.0')];

		return self::requirements()->check(self::plugin('acme/reports', ['acme/shop' => $constraint]), $installed, $running)[0];
	}

	public function testChecksOtherPlugins(): void
	{
		$this->assertTrue(self::shop('^2.0', ['acme/shop' => true])->met);
		$this->assertSame('Shop ^2.0 (is turned off)', self::shop('^2.0', [])->describe());
		$this->assertSame('version 2.3.0 is installed', self::shop('^3.0', ['acme/shop' => true])->note);
		$this->assertSame('acme/crm ^1.0 (isn\'t installed)', self::requirements()->check(self::plugin('acme/sync', ['acme/crm' => '^1.0']), [], [])[0]->describe());
	}

	public function testRunsOnlyPluginsWhoseRequirementsAreMet(): void
	{
		$discovered = [
			self::plugin('acme/shop'),
			self::plugin('acme/reports', ['acme/shop' => '^1.0']),
			self::plugin('acme/charts', ['acme/reports' => '*']),
			self::plugin('acme/future', ['blush-dev/framework' => '^9.0'])
		];

		$plugins = Plugins::enabled($discovered, new PluginConfig(['acme/shop', 'acme/reports', 'acme/charts', 'acme/future']), self::requirements());
		$this->assertSame(['acme/charts', 'acme/reports', 'acme/shop'], array_map(static fn (PluginManifest $plugin): string => $plugin->name, $plugins->all()));
		$this->assertSame(['acme/future'], array_keys($plugins->unmet()));
		$this->assertCount(4, $plugins->installed());
		$this->assertSame(
			['Acme\\Shop\\Provider', 'Acme\\Reports\\Provider', 'Acme\\Charts\\Provider'],
			$plugins->providers(),
			'A plugin\'s requirements register before it.'
		);

		// Turning the shop off stops what needs it, all the way down.
		$plugins = Plugins::enabled($discovered, new PluginConfig(['acme/reports', 'acme/charts', 'acme/future']), self::requirements());
		$this->assertSame([], array_map(static fn (PluginManifest $plugin): string => $plugin->name, $plugins->all()));
		$this->assertSame(['acme/charts', 'acme/future', 'acme/reports'], array_keys($plugins->unmet()));
		$this->assertSame('Needs Reports (can\'t run).', Requirements::reason($plugins->unmet()['acme/charts'] ?? []));
		$this->assertSame('Needs Shop ^1.0 (is turned off).', Requirements::reason($plugins->unmet()['acme/reports'] ?? []));
	}

	public function testPluginsThatRequireEachOtherBothRun(): void
	{
		$plugins = Plugins::enabled([
			self::plugin('acme/one', ['acme/two' => '*']),
			self::plugin('acme/two', ['acme/one' => '*'])
		], new PluginConfig(['acme/one', 'acme/two']), self::requirements());

		$this->assertSame([], $plugins->unmet());
		$this->assertCount(2, $plugins->providers());
	}

	public function testEveryKindReadsConflictAsRequireIsRead(): void
	{
		$plugin = PluginManifest::fromArray(['name' => 'acme/hello', 'source' => 'local', 'path' => '/site/extensions/acme/hello', 'conflict' => ['acme/old' => '<2.0']]);

		$this->assertSame(['acme/old' => '<2.0'], $plugin->conflict);
		$this->assertEquals($plugin, PluginManifest::fromArray($plugin->toArray()), 'It round-trips through a cached manifest.');
		$this->assertSame(['php' => '<8.0'], IconPack::fromArray('/site/extensions/acme/brands', ['name' => 'acme/brands', 'conflict' => ['php' => '<8.0']])->conflict);
		$this->assertSame(['acme/old' => '*'], ThemeManifest::fromArray('/site/extensions/acme/nova', ['name' => 'acme/nova', 'conflict' => ['acme/old' => '*']])->conflict);

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('"conflict" must map names to version constraints.');

		PluginManifest::fromArray(['name' => 'acme/hello', 'source' => 'local', 'path' => '/site/extensions/acme/hello', 'conflict' => ['acme/old' => 2]]);
	}

	public function testAConflictIsCheckedAfterRequirementsAgainstWhatsOn(): void
	{
		$plugin  = new PluginManifest(name: 'acme/hello', label: 'Hello', namespace: 'hello', source: PluginSource::Local, path: '/site/extensions/acme/hello', require: ['ext-intl' => '*'], conflict: ['ext-intl' => '<8.0', 'acme/hello' => '*', 'acme/old' => '*']);
		$old     = new PluginManifest(name: 'acme/old', label: 'Old', namespace: 'old', source: PluginSource::Local, path: '/site/extensions/acme/old');
		$checked = self::requirements()->check($plugin, ['acme/old' => $old], [], [], ['acme/old' => true]);

		$this->assertSame([false, true, true], array_map(static fn (Requirement $requirement): bool => $requirement->conflict, $checked), 'Its own name is passed over.');
		$this->assertSame(['', 'version 8.5.1 is loaded', 'version 0.0.0 is on'], array_map(static fn (Requirement $requirement): string => $requirement->note, $checked));
		$this->assertSame('Conflicts with Old (version 0.0.0 is on).', Requirements::reason($checked));
	}
}
