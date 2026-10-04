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
			['requirements' => [['name' => 'acme/brands', 'constraint' => '^1.0', 'kind' => 'icon-pack', 'met' => true, 'note' => '', 'label' => 'Brands', 'metBy' => '']], 'conflicts' => [], 'replaces' => [], 'provides' => [], 'blocked' => null, 'requiredBy' => [], 'abandoned' => false, 'replacement' => null, 'suggests' => []],
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

	/**
	 * @param array<string, string> $conflict
	 * @param array<string, string> $require
	 */
	private static function conflicting(string $name, array $conflict, array $require = []): PluginManifest
	{
		$plugin = self::plugin($name, $require);

		return new PluginManifest(name: $plugin->name, label: $plugin->label, namespace: $plugin->namespace, source: PluginSource::Local, path: $plugin->path, version: '1.0.0', require: $require, conflict: $conflict);
	}

	public function testTheOneDeclaringAConflictStops(): void
	{
		$plugins = [self::conflicting('acme/new-seo', ['acme/old-seo' => '<2.0']), self::plugin('acme/old-seo'), self::plugin('acme/sitemap', ['acme/new-seo' => '*'])];

		$state = self::settle($plugins, ['acme/new-seo', 'acme/old-seo', 'acme/sitemap'], [], Themes::DEFAULT);

		$this->assertFalse($state->runs('acme/new-seo'));
		$this->assertTrue($state->runs('acme/old-seo'), 'The one it names runs: it asked for nothing.');
		$this->assertFalse($state->runs('acme/sitemap'), 'What needs the one that stops stops too.');
		$this->assertSame('Conflicts with Old-seo <2.0 (version 1.0.0 is on).', Requirements::reason($state->plugins->unmet()['acme/new-seo'] ?? []));
		$this->assertSame(
			[['name' => 'acme/old-seo', 'constraint' => '<2.0', 'kind' => 'plugin', 'met' => false, 'note' => 'version 1.0.0 is on', 'label' => 'Old-seo', 'metBy' => '']],
			$state->report($plugins[0])['conflicts']
		);
		$this->assertSame([], $state->report($plugins[0])['requirements'], 'Conflicts are listed apart from requirements.');
	}

	public function testAConflictOutsideItsVersionsOrTurnedOffIsMet(): void
	{
		$plugins = [self::conflicting('acme/new-seo', ['acme/old-seo' => '<1.0', 'acme/other' => '*']), self::plugin('acme/old-seo'), self::plugin('acme/other')];

		$state = self::settle($plugins, ['acme/new-seo', 'acme/old-seo'], [], Themes::DEFAULT);

		$this->assertTrue($state->runs('acme/new-seo'));
		$this->assertSame(['version 1.0.0 is installed', 'is turned off'], array_column($state->report($plugins[0])['conflicts'], 'note'));
	}

	public function testAConflictIsJudgedAgainstWhatsOnNotWhatRuns(): void
	{
		$plugins = [self::conflicting('acme/new-seo', ['acme/old-seo' => '*']), self::plugin('acme/old-seo', ['blush-dev/framework' => '^3.0'])];

		$state = self::settle($plugins, ['acme/new-seo', 'acme/old-seo'], [], Themes::DEFAULT);

		$this->assertFalse($state->runs('acme/old-seo'));
		$this->assertFalse($state->runs('acme/new-seo'), 'The one it names is on, though it can\'t run, so settling never goes back and forth.');
	}

	public function testConflictingBothWaysStopsBoth(): void
	{
		$plugins = [self::conflicting('acme/one', ['acme/two' => '*']), self::conflicting('acme/two', ['acme/one' => '*'])];

		$state = self::settle($plugins, ['acme/one', 'acme/two'], [], Themes::DEFAULT);

		$this->assertFalse($state->runs('acme/one'));
		$this->assertFalse($state->runs('acme/two'));
	}

	public function testAChainThatConflictsFallsBackAndPlatformConflictsCount(): void
	{
		$theme   = new ThemeManifest(name: 'acme/nova', path: '/site/extensions/acme/nova', label: 'Nova', namespace: 'nova', version: '1.0.0', source: ThemeSource::Local, conflict: ['acme/old-seo' => '*']);
		$plugins = [self::plugin('acme/old-seo'), self::conflicting('acme/modern', ['php' => '<8.5']), self::conflicting('acme/legacy', ['php' => '>=8.5', 'ext-none' => '*', 'acme/missing' => '*'])];

		$state = self::settle($plugins, ['acme/old-seo', 'acme/modern', 'acme/legacy'], [$theme], 'acme/nova');

		$this->assertSame(Themes::DEFAULT, $state->themes->running('acme/nova'));
		$this->assertTrue($state->runs('acme/modern'), 'It conflicts only with PHP below 8.5.');
		$this->assertFalse($state->runs('acme/legacy'));
		$this->assertSame('Conflicts with PHP >=8.5 (this site runs 8.5.1).', Requirements::reason($state->plugins->unmet()['acme/legacy'] ?? []));
		$this->assertSame(['isn\'t loaded', 'isn\'t installed'], array_slice(array_column($state->report($plugins[2])['conflicts'], 'note'), 1), 'An extension that isn\'t loaded or installed conflicts with nothing.');
	}

	public function testAnInvalidConstraintIsAConflict(): void
	{
		$plugins = [self::conflicting('acme/odd', ['acme/other' => 'not a constraint']), self::plugin('acme/other')];

		$state = self::settle($plugins, ['acme/odd'], [], Themes::DEFAULT);

		$this->assertFalse($state->runs('acme/odd'));
		$this->assertSame('Needs Blush ^3.0 (this site runs 2.1.0). Conflicts with Other not a constraint (isn\'t a version constraint Blush understands).', Requirements::reason([
			new Requirement('blush-dev/framework', '^3.0', RequirementKind::Blush, false, 'this site runs 2.1.0'),
			...$state->plugins->unmet()['acme/odd'] ?? []
		]), 'Needs, then conflicts.');
	}

	/**
	 * @param array<string, string> $replace
	 */
	private static function replacing(string $name, array $replace, string $version = '2.0.0'): PluginManifest
	{
		$plugin = self::plugin($name);

		return new PluginManifest(name: $plugin->name, label: $plugin->label, namespace: $plugin->namespace, source: PluginSource::Local, path: $plugin->path, version: $version, replace: $replace);
	}

	public function testARequirementIsMetByWhatReplacesIt(): void
	{
		$plugins = [self::replacing('acme/seo-pro', ['acme/seo' => 'self.version']), self::plugin('acme/sitemap', ['acme/seo' => '^2.0']), self::plugin('acme/legacy-map', ['acme/seo' => '^1.0'])];

		$state = self::settle($plugins, ['acme/seo-pro', 'acme/sitemap', 'acme/legacy-map'], [], Themes::DEFAULT);

		$this->assertTrue($state->runs('acme/sitemap'));
		$this->assertSame(
			[['name' => 'acme/seo', 'constraint' => '^2.0', 'kind' => 'plugin', 'met' => true, 'note' => 'Seo-pro 2.0.0 replaces it', 'label' => '', 'metBy' => 'acme/seo-pro']],
			$state->report($plugins[1])['requirements']
		);
		$this->assertFalse($state->runs('acme/legacy-map'), 'It replaces 2.0.0 only (self.version), which ^1.0 doesn\'t match.');
		$this->assertSame(['acme/legacy-map', 'acme/sitemap'], array_map(static fn ($other): string => $other->name, $state->requiredBy('acme/seo-pro')), 'What requires what it replaces requires it.');

		$off = $state->with(new PluginConfig(enabled: ['acme/sitemap']));

		$this->assertFalse($off->runs('acme/sitemap'), 'Turned off, it meets nothing.');
	}

	public function testAReplacerDoesntRunWhileWhatItReplacesIsOn(): void
	{
		$plugins = [self::replacing('acme/seo-pro', ['acme/seo' => '*']), self::plugin('acme/seo'), self::plugin('acme/sitemap', ['acme/seo' => '^1.0'])];

		$both = self::settle($plugins, ['acme/seo-pro', 'acme/seo', 'acme/sitemap'], [], Themes::DEFAULT);

		$this->assertFalse($both->runs('acme/seo-pro'), 'The one declaring it stops, as for a conflict.');
		$this->assertTrue($both->runs('acme/seo'));
		$this->assertTrue($both->runs('acme/sitemap'), 'Met by the one it names.');
		$this->assertSame('Replaces Seo (is on).', Requirements::reason($both->plugins->unmet()['acme/seo-pro'] ?? []));
		$this->assertSame([], $both->report($plugins[0])['conflicts'], 'Listed as what it replaces, not as a conflict.');

		$swapped = self::settle($plugins, ['acme/seo-pro', 'acme/sitemap'], [], Themes::DEFAULT);

		$this->assertTrue($swapped->runs('acme/seo-pro'), 'It runs once the one it replaces is off, installed or not.');
		$this->assertTrue($swapped->runs('acme/sitemap'));
		$this->assertSame('acme/seo-pro', $swapped->check($plugins[2])[0]->metBy ?? null);
		$this->assertSame(
			[['name' => 'acme/seo', 'constraint' => '*', 'kind' => 'plugin', 'met' => true, 'note' => 'is turned off', 'label' => 'Seo', 'metBy' => '']],
			$swapped->report($plugins[0])['replaces']
		);
	}

	/**
	 * @param array<string, string> $provide
	 * @param array<string, string> $conflict
	 */
	private static function providing(string $name, array $provide, string $version = '1.2.0', array $conflict = []): PluginManifest
	{
		$plugin = self::plugin($name);

		return new PluginManifest(name: $plugin->name, label: $plugin->label, namespace: $plugin->namespace, source: PluginSource::Local, path: $plugin->path, version: $version, conflict: $conflict, provide: $provide);
	}

	public function testARequirementIsMetByAnythingThatProvidesIt(): void
	{
		$plugins = [
			self::providing('acme/openai', ['acme/ai-provider' => 'self.version']),
			self::providing('acme/claude', ['acme/ai-provider' => '^1.0']),
			self::providing('acme/next-ai', ['acme/ai-provider' => '2.0.0']),
			self::plugin('acme/writer', ['acme/ai-provider' => '^1.0'])
		];

		$one = self::settle($plugins, ['acme/openai', 'acme/writer'], [], Themes::DEFAULT);

		$this->assertTrue($one->runs('acme/writer'));
		$this->assertSame(['Openai 1.2.0 provides it', 'acme/openai'], [$one->check($plugins[3])[0]->note, $one->check($plugins[3])[0]->metBy]);
		$this->assertSame([['name' => 'acme/ai-provider', 'constraint' => '1.2.0']], $one->report($plugins[0])['provides'], 'self.version is its own.');
		$this->assertSame(['acme/writer'], array_map(static fn ($other): string => $other->name, $one->requiredBy('acme/claude')), 'What requires what it provides requires it.');

		$both = self::settle($plugins, ['acme/openai', 'acme/claude', 'acme/writer'], [], Themes::DEFAULT);

		$this->assertTrue($both->runs('acme/openai') && $both->runs('acme/claude') && $both->runs('acme/writer'), 'Any number may provide one, with no conflict.');

		$wrong = self::settle($plugins, ['acme/next-ai', 'acme/writer'], [], Themes::DEFAULT);

		$this->assertFalse($wrong->runs('acme/writer'), 'It provides 2.0.0, which ^1.0 doesn\'t match.');
		$this->assertSame('Needs acme/ai-provider ^1.0 (isn\'t installed).', Requirements::reason($wrong->plugins->unmet()['acme/writer'] ?? []));
	}

	public function testAConflictHitsWhatProvidesOrReplacesWhatItNames(): void
	{
		$plugins = [
			self::providing('acme/openai', ['acme/ai-provider' => 'self.version']),
			self::providing('acme/offline', [], conflict: ['acme/ai-provider' => '*', 'acme/seo' => '*']),
			self::replacing('acme/seo-pro', ['acme/seo' => 'self.version'])
		];

		$state = self::settle($plugins, ['acme/openai', 'acme/offline', 'acme/seo-pro'], [], Themes::DEFAULT);

		$this->assertFalse($state->runs('acme/offline'));
		$this->assertSame('Conflicts with acme/ai-provider (Openai 1.2.0 provides it, and is on), and acme/seo (Seo-pro 2.0.0 replaces it, and is on).', Requirements::reason($state->plugins->unmet()['acme/offline'] ?? []));
		$this->assertSame(['acme/openai', 'acme/seo-pro'], array_column($state->report($plugins[1])['conflicts'], 'metBy'));
		$this->assertTrue(self::settle($plugins, ['acme/offline'], [], Themes::DEFAULT)->runs('acme/offline'), 'Neither is on.');
	}

	public function testSaysWhatConflictsWithReplacesAndProvidesAnExtension(): void
	{
		$old     = new PluginManifest(name: 'acme/old-seo', label: 'Old-seo', namespace: 'old-seo', source: PluginSource::Local, path: '/site/extensions/acme/old-seo', version: '1.0.0', provide: ['acme/seo-api' => 'self.version']);
		$plugins = [
			$old,
			self::conflicting('acme/new-seo', ['acme/old-seo' => '<2.0']),
			self::conflicting('acme/later', ['acme/old-seo' => '>=2.0']),
			self::conflicting('acme/strict', ['acme/seo-api' => '*']),
			self::replacing('acme/seo-pro', ['acme/old-seo' => '*']),
			self::providing('acme/seo-lite', ['acme/old-seo' => '1.0'])
		];

		$state = self::settle($plugins, [], [], Themes::DEFAULT);
		$names = static fn (array $list): array => array_column($list, 'name');

		$opposite = $state->opposite($old);

		$this->assertSame(['acme/new-seo', 'acme/strict'], $names($opposite['conflictedBy']), 'At its version, or through what it provides.');
		$this->assertSame(['acme/seo-pro'], $names($opposite['replacedBy']));
		$this->assertSame(['acme/seo-lite'], $names($opposite['providedBy']));
	}

	public function testSaysWhatTurningOnOrActivatingStops(): void
	{
		$packs   = [self::pack('acme/brands')];
		$theme   = self::theme('acme/nova');
		$plugins = [
			self::plugin('acme/old-seo'),
			self::conflicting('acme/new-seo', ['acme/old-seo' => '*', 'acme/nova' => '*']),
			self::plugin('acme/sitemap', ['acme/new-seo' => '*']),
			self::replacing('acme/logos', ['acme/brands' => '*'])
		];

		$state = self::settle($plugins, ['acme/new-seo', 'acme/sitemap', 'acme/logos'], [$theme], Themes::DEFAULT, $packs);
		$names = static fn (array $list): array => array_column($list, 'name');

		$this->assertSame(['acme/new-seo', 'acme/sitemap'], $names($state->stops($plugins[0])), 'What declares a conflict with it, and what needs that.');
		$this->assertSame([], $state->stops($plugins[1]), 'Nothing for one that runs.');
		$this->assertSame(['acme/logos'], $names($state->stops($packs[0])), 'What replaces a pack stops when the pack is on.');
		$this->assertSame(['acme/new-seo', 'acme/sitemap'], $names($state->stops($theme)), 'The themes it takes the place of aren\'t listed.');
	}

	public function testReportsWhatItSuggests(): void
	{
		$packs   = [self::pack('acme/brands')];
		$plugins = [new PluginManifest(name: 'acme/hello', label: 'Hello', namespace: 'hello', source: PluginSource::Local, path: '/site/extensions/acme/hello', suggest: ['acme/brands' => 'For logos.', 'ext-json' => 'Faster.', 'ext-blush-none' => '', 'guzzlehttp/guzzle' => 'For feeds.'])];

		$state = self::settle($plugins, [], [self::theme('acme/nova')], Themes::DEFAULT, $packs, []);

		$this->assertSame([
			['name' => 'acme/brands', 'reason' => 'For logos.', 'extension' => ['name' => 'acme/brands', 'label' => 'Brands', 'kind' => 'icon-pack'], 'loaded' => null],
			['name' => 'ext-json', 'reason' => 'Faster.', 'extension' => null, 'loaded' => true],
			['name' => 'ext-blush-none', 'reason' => '', 'extension' => null, 'loaded' => false],
			['name' => 'guzzlehttp/guzzle', 'reason' => 'For feeds.', 'extension' => null, 'loaded' => null]
		], $state->report($plugins[0])['suggests'], 'An installed extension is described even when it\'s off; anything else is only named.');
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

	public function testTurningOnWhatAConflictNamesStopsTheOneDeclaringIt(): void
	{
		$plugins = [self::conflicting('acme/new-seo', ['acme/old-seo' => '*']), self::plugin('acme/old-seo')];

		$before = self::settle($plugins, ['acme/new-seo'], [], Themes::DEFAULT);
		$after  = $before->with(new PluginConfig(enabled: ['acme/new-seo', 'acme/old-seo']));

		$this->assertTrue($before->runs('acme/new-seo'));
		$this->assertTrue($after->runs('acme/old-seo'), 'Turning it on isn\'t refused: it declares nothing.');
		$this->assertSame(['acme/new-seo'], array_map(static fn ($other): string => $other->name, $before->runningNotIn($after, 'acme/old-seo')), 'So the answer says what stopped.');
	}

	public function testDescribesEachKind(): void
	{
		$this->assertSame('Nova ^1.0 (isn\'t active)', new Requirement('acme/nova', '^1.0', RequirementKind::Theme, false, 'isn\'t active', 'Nova')->describe());
		$this->assertSame('acme/crm (isn\'t installed)', new Requirement('acme/crm', '*', RequirementKind::Missing, false, 'isn\'t installed')->describe());
	}
}
