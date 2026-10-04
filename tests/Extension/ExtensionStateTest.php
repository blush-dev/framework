<?php

/**
 * Extension state tests.
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
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirement;
use Blush\Extension\RequirementKind;
use Blush\Extension\Requirements;
use Blush\Icon\IconConfig;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\PluginSource;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;
use Blush\Theme\ThemeSource;

#[CoversClass(ExtensionState::class)]
#[CoversClass(Requirements::class)]
#[CoversClass(Themes::class)]
#[CoversClass(IconPacks::class)]
final class ExtensionStateTest extends TestCase
{
	/**
	 * @param array<string, string> $require
	 */
	private static function plugin(string $name, array $require = [], string $version = '1.0.0'): PluginManifest
	{
		$short = substr($name, (int) strpos($name, '/') + 1);

		return new PluginManifest(
			name: $name,
			label: ucfirst($short),
			namespace: $short,
			provider: null,
			source: PluginSource::Local,
			path: "/site/extensions/{$name}",
			version: $version,
			require: $require
		);
	}

	/**
	 * @param array<string, string> $require
	 */
	private static function theme(string $name, ?string $parent = null, array $require = []): ThemeManifest
	{
		$short = substr($name, (int) strpos($name, '/') + 1);

		return new ThemeManifest(
			name: $name,
			path: "/site/extensions/{$name}",
			label: ucfirst($short),
			namespace: $short,
			version: '1.0.0',
			parent: $parent,
			source: $name === Themes::DEFAULT ? ThemeSource::Framework : ThemeSource::Local,
			require: $require
		);
	}

	/**
	 * @param array<string, string> $require
	 */
	private static function pack(string $name, array $require = []): IconPack
	{
		$short = substr($name, (int) strpos($name, '/') + 1);

		return new IconPack(name: $name, label: ucfirst($short), namespace: $short, path: "/site/extensions/{$name}", version: '1.0.0', require: $require);
	}

	/**
	 * Settles a site on Blush 2.1.0 and PHP 8.5.1.
	 *
	 * @param list<PluginManifest> $plugins
	 * @param list<string>         $on      The plugins turned on.
	 * @param list<ThemeManifest>  $themes
	 * @param list<IconPack>       $packs
	 * @param list<string>         $packsOn The packs turned on.
	 */
	private static function settle(array $plugins, array $on, array $themes, string $active, array $packs = [], array $packsOn = []): ExtensionState
	{
		$installed = [Themes::DEFAULT => self::theme(Themes::DEFAULT)];

		foreach ($themes as $theme) {
			$installed[$theme->name] = $theme;
		}

		$keyed = [];

		foreach ($packs as $pack) {
			$keyed[$pack->name] = $pack;
		}

		return ExtensionState::settle(
			$plugins,
			[],
			new PluginConfig(enabled: $on),
			new Themes($installed),
			$active,
			new IconPacks($keyed, config: new IconConfig(enabled: $packsOn)),
			new Requirements('2.1.0', '8.5.1', static fn (string $name): false => false)
		);
	}

	public function testAPluginMayRequireAnIconPack(): void
	{
		$plugins = [self::plugin('acme/brands-block', ['acme/brands' => '^1.0'])];
		$packs   = [self::pack('acme/brands')];

		$on = self::settle($plugins, ['acme/brands-block'], [], Themes::DEFAULT, $packs, ['acme/brands']);
		$this->assertTrue($on->runs('acme/brands-block'));
		$this->assertTrue($on->runs('acme/brands'));

		$off = self::settle($plugins, ['acme/brands-block'], [], Themes::DEFAULT, $packs);
		$this->assertFalse($off->runs('acme/brands-block'), 'The pack it needs is off.');
		$this->assertSame('Needs Brands ^1.0 (is turned off).', Requirements::reason($off->plugins->unmet()['acme/brands-block'] ?? []));
		$this->assertSame(RequirementKind::IconPack, (($off->plugins->unmet()['acme/brands-block'] ?? [])[0] ?? null)?->kind);
	}

	public function testAPackWhoseRequirementsArentMetDoesntLoad(): void
	{
		$packs = [self::pack('acme/brands', ['blush-dev/framework' => '^3.0']), self::pack('acme/weather')];

		$state = self::settle([], [], [], Themes::DEFAULT, $packs, ['acme/brands', 'acme/weather']);

		$this->assertSame(['acme/brands', 'acme/weather'], array_keys($state->packs->on()));
		$this->assertSame(['acme/weather'], array_keys($state->packs->enabled()), 'Only a pack that loads adds icons.');
		$this->assertSame(['acme/brands'], array_keys($state->packs->unmet()));
		$this->assertTrue($state->packs->isEnabled('acme/brands'), 'It\'s still turned on.');
	}

	public function testAnActiveThemeWhoseRequirementsArentMetFallsBackToTheDefault(): void
	{
		$themes = [self::theme('acme/shop-theme', require: ['acme/shop' => '^2.0'])];
		$shop   = [self::plugin('acme/shop', version: '2.3.0')];

		$runs = self::settle($shop, ['acme/shop'], $themes, 'acme/shop-theme');
		$this->assertSame('acme/shop-theme', $runs->themes->running('acme/shop-theme'));
		$this->assertTrue($runs->runs('acme/shop-theme'));

		$falls = self::settle($shop, [], $themes, 'acme/shop-theme');
		$this->assertSame(Themes::DEFAULT, $falls->themes->running('acme/shop-theme'));
		$this->assertFalse($falls->runs('acme/shop-theme'));
		$this->assertTrue($falls->runs(Themes::DEFAULT), 'The default theme runs in its place.');
		$this->assertSame('Needs Shop ^2.0 (is turned off).', Requirements::reason($falls->themes->unmet()['acme/shop-theme'] ?? []));
	}

	public function testAChainRunsWholeOrNotAtAll(): void
	{
		$themes = [
			self::theme('acme/base', require: ['php' => '>=9.0']),
			self::theme('acme/child', parent: 'acme/base')
		];

		$state = self::settle([], [], $themes, 'acme/child');

		$this->assertSame(Themes::DEFAULT, $state->themes->running('acme/child'), 'Its parent can\'t run, so it falls back.');
		$this->assertSame([], $state->themes->unmet()['acme/child'] ?? null, 'It has no requirements of its own that aren\'t met.');
		$this->assertSame('Needs PHP >=9.0 (this site runs 8.5.1).', Requirements::reason($state->themes->unmet()['acme/base'] ?? []));
	}

	public function testAThemeRequirementIsMetByTheActiveChain(): void
	{
		$themes  = [self::theme('acme/nova'), self::theme('acme/other')];
		$plugins = [self::plugin('acme/nova-blocks', ['acme/nova' => '*'])];

		$active = self::settle($plugins, ['acme/nova-blocks'], $themes, 'acme/nova');
		$this->assertTrue($active->runs('acme/nova-blocks'));

		$inactive = self::settle($plugins, ['acme/nova-blocks'], $themes, 'acme/other');
		$this->assertFalse($inactive->runs('acme/nova-blocks'));
		$this->assertSame('Needs Nova (isn\'t active).', Requirements::reason($inactive->plugins->unmet()['acme/nova-blocks'] ?? []));
	}

	public function testAThemeAndThePluginItRequiresStopTogether(): void
	{
		$themes  = [self::theme('acme/nova', require: ['acme/blocks' => '^1.0'])];
		$plugins = [self::plugin('acme/blocks', ['blush-dev/framework' => '^3.0'])];

		$state = self::settle($plugins, ['acme/blocks'], $themes, 'acme/nova');

		$this->assertFalse($state->runs('acme/blocks'));
		$this->assertSame(Themes::DEFAULT, $state->themes->running('acme/nova'));
		$this->assertSame('Needs Blocks ^1.0 (can\'t run).', Requirements::reason($state->themes->unmet()['acme/nova'] ?? []));
	}

	public function testChecksAsIfOnAndSaysWhatRequiresAnExtension(): void
	{
		$packs   = [self::pack('acme/brands')];
		$plugins = [self::plugin('acme/brands-block', ['acme/brands' => '^1.0'])];
		$themes  = [self::theme('acme/nova', require: ['acme/brands' => '*'])];

		$state = self::settle($plugins, [], $themes, Themes::DEFAULT, $packs, ['acme/brands']);

		$this->assertTrue(Requirements::met($state->check($plugins[0])), 'Checked as if it were on.');
		$this->assertSame(['acme/brands-block', 'acme/nova'], array_map(static fn ($other): string => $other->name, $state->requiredBy('acme/brands')));
		$this->assertSame(
			['requirements' => [['name' => 'acme/brands', 'constraint' => '^1.0', 'kind' => 'icon-pack', 'met' => true, 'note' => '', 'label' => 'Brands']], 'blocked' => null, 'requiredBy' => [], 'abandoned' => false, 'replacement' => null],
			$state->report($plugins[0])
		);
	}

	public function testNamesAnInstalledReplacement(): void
	{
		$packs   = [self::pack('acme/brands'), new IconPack(name: 'acme/logos', label: 'Logos', namespace: 'logos', path: '/site/extensions/acme/logos', abandoned: 'acme/brands')];
		$plugins = [new PluginManifest(name: 'acme/old', label: 'Old', namespace: 'old', source: PluginSource::Local, path: '/site/extensions/acme/old', abandoned: 'acme/elsewhere'), self::plugin('acme/kept')];
		$themes  = [self::theme('acme/nova')];

		$state = self::settle($plugins, [], $themes, Themes::DEFAULT, $packs, []);

		$this->assertSame(['abandoned' => 'acme/brands', 'replacement' => ['name' => 'acme/brands', 'label' => 'Brands', 'kind' => 'icon-pack']], array_intersect_key($state->report($packs[1]), ['abandoned' => true, 'replacement' => true]), 'Of any kind.');
		$this->assertSame(['abandoned' => 'acme/elsewhere', 'replacement' => null], array_intersect_key($state->report($plugins[0]), ['abandoned' => true, 'replacement' => true]), 'A package that isn\'t installed is only named.');
		$this->assertFalse($state->report($plugins[1])['abandoned']);
	}

	public function testSaysWhatStartsAndStopsWithAChange(): void
	{
		$packs   = [self::pack('acme/brands')];
		$plugins = [self::plugin('acme/brands-block', ['acme/brands' => '^1.0'])];

		$before = self::settle($plugins, ['acme/brands-block'], [], Themes::DEFAULT, $packs, ['acme/brands']);
		$after  = $before->with(icons: new IconConfig(enabled: []));

		$this->assertSame(['acme/brands-block'], array_map(static fn ($other): string => $other->name, $before->runningNotIn($after, 'acme/brands')));
		$this->assertSame([], $after->runningNotIn($before));
	}

	public function testDescribesEachKind(): void
	{
		$this->assertSame('Nova ^1.0 (isn\'t active)', new Requirement('acme/nova', '^1.0', RequirementKind::Theme, false, 'isn\'t active', 'Nova')->describe());
		$this->assertSame('acme/crm (isn\'t installed)', new Requirement('acme/crm', '*', RequirementKind::Missing, false, 'isn\'t installed')->describe());
	}
}
