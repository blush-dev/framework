<?php

/**
 * Settings tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Settings;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Clock\FrozenClock;
use Blush\Config\ConfigRepository;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Feed\FeedConfig;
use Blush\Routing\RouteConfig;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\SettingGroups;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\File\FileTransactions;
use Blush\Support\Filesystem;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Setting::class)]
#[CoversClass(Settings::class)]
#[CoversClass(SettingsStore::class)]
#[CoversClass(SettingGroups::class)]
final class SettingsTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function settingGroups(): SettingGroups
	{
		$paths   = Paths::fromRoot($this->temporaryDirectory());
		$layouts = new FileLayouts($paths);
		$layouts->register(SettingGroups::TABLE, SettingGroups::layout($paths));

		return new SettingGroups(
			new FileRecordStore($paths, $layouts, new FileTransactions($paths, new Filesystem()), new Filesystem(), static fn (): never => throw new LogicException('Settings keep no content.')),
			new FrozenClock('2026-10-09')
		);
	}

	private function file(): SettingsStore
	{
		return new SettingsStore($this->settingGroups());
	}

	private function path(string $group): string
	{
		return $this->temporaryDirectory() . "/user/data/settings/{$group}.json";
	}

	/**
	 * A group's file, without its id.
	 *
	 * @return array<array-key, mixed>
	 */
	private function group(string $group): array
	{
		$data = json_decode((string) file_get_contents($this->path($group)), true);

		return is_array($data) ? array_diff_key($data, ['id' => true]) : [];
	}

	public function testLaysSettingsOverTheirConfigObjects(): void
	{
		$config = new ConfigRepository(new AppConfig(name: 'Configured', url: 'https://example.test', debug: true), new FeedConfig(), new RouteConfig());
		$applied = Settings::fromArray(['app' => ['name' => 'Saved'], 'feed' => ['limit' => 5], 'routes' => ['trailingSlash' => true]])->apply($config);

		$app = $applied->get(AppConfig::class);
		$this->assertSame('Saved', $app->name);
		$this->assertSame('https://example.test', $app->url, 'Keys the settings don\'t name are kept.');
		$this->assertTrue($app->debug);
		$this->assertSame(5, $applied->get(FeedConfig::class)->limit);
		$this->assertTrue($applied->get(RouteConfig::class)->trailingSlash);
		$this->assertSame($config, Settings::none()->apply($config), 'Nothing saved changes nothing.');
	}

	public function testKeepsTheEnumsOrder(): void
	{
		$settings = Settings::fromArray(['feed' => ['limit' => 5], 'app' => ['name' => 'A']])->with(['app.locale' => 'fr']);

		$this->assertSame(['app' => ['name' => 'A', 'locale' => 'fr'], 'feed' => ['limit' => 5]], $settings->toArray());
		$this->assertSame(['app.name' => 'A', 'feed.limit' => 5], $settings->without(Setting::Locale)->values());
		$this->assertSame(Setting::FeedLimit, Setting::find('feed', 'limit'));
		$this->assertSame('config/feed.php', Setting::FeedLimit->file());
	}

	public function testReadsAndWritesAGroupAFile(): void
	{
		$file = $this->file();

		$this->assertTrue($file->read()->isEmpty(), 'No groups is no settings.');

		$file->update(static fn (Settings $settings): Settings => $settings->with(['app.name' => 'Café', 'sitemap.disallow' => ['/a/']]));

		$this->assertSame(['name' => 'Café'], $this->group('app'), 'A group a file (D-673).');
		$this->assertSame(['disallow' => ['/a/']], $this->group('sitemap'));
		$this->assertStringContainsString('"Café"', (string) file_get_contents($this->path('app')), 'Text is written as is.');
		$this->assertStringNotContainsString('"group"', (string) file_get_contents($this->path('app')), 'Its name is its file\'s.');
		$this->assertSame('Café', $file->read()->get(Setting::Name));
		$this->assertSame('user/data/settings/app.json', $file->location('app'));

		$modified = filemtime($this->path('app'));
		touch($this->path('app'), 1);
		$file->update(static fn (Settings $settings): Settings => $settings->with(['sitemap.disallow' => ['/b/']]));

		$this->assertSame(1, filemtime($this->path('app')), 'Only the groups that changed are written.');
		$this->assertNotFalse($modified);

		$file->update(static fn (Settings $settings): Settings => $settings->without(Setting::SitemapDisallow));

		$this->assertFileDoesNotExist($this->path('sitemap'), 'A group left empty is removed.');
	}

	public function testKeepsAnEditorsSchemaKey(): void
	{
		$this->writeTemporaryFile('user/data/settings/app.json', '{"$schema": "settings.schema.json", "name": "Blog"}');
		$file = $this->file();

		$this->assertSame('Blog', $file->read()->get(Setting::Name), 'It isn\'t a setting (D-491).');

		$file->update(static fn (Settings $settings): Settings => $settings->with(['app.name' => 'Notes']));

		$this->assertSame(['$schema' => 'settings.schema.json', 'name' => 'Notes'], $this->group('app'), 'Kept first.');
	}

	public function testRefusesABrokenGroup(): void
	{
		$cases = ['app' => ['{"name": ', '["name"]', '{"url": "https://x.test"}'], 'feed' => ['{"limit": 0}']];

		foreach ($cases as $group => $files) {
			foreach ($files as $json) {
				$this->removeTemporaryDirectory();
				$this->writeTemporaryFile("user/data/settings/{$group}.json", $json);

				try {
					$this->file()->read();
					$this->fail("Read {$json}.");
				} catch (InvalidSetting $error) {
					$this->assertStringContainsString("user/data/settings/{$group}.json", $error->getMessage(), $json);
				}
			}
		}
	}

	public function testExtensionsKeepGroupsOfTheirOwn(): void
	{
		$groups = $this->settingGroups();

		$groups->save('acme/gallery', ['columns' => 3]);

		$this->assertFileExists($this->path('acme__gallery'), 'Its / is __ (D-670).');
		$this->assertSame(['columns' => 3], $this->settingGroups()->get('acme/gallery'));
		$this->assertSame(['columns' => 3], $this->settingGroups()->all()['acme/gallery'] ?? null);
		$this->assertTrue($this->file()->read()->isEmpty(), 'An extension\'s group isn\'t a core setting.');
		$this->assertSame(['columns' => 4], $groups->update('acme/gallery', static fn (array $values): array => ['columns' => 4]));

		$groups->save('acme/gallery', []);

		$this->assertFileDoesNotExist($this->path('acme__gallery'), 'Saving nothing removes it.');
		$this->assertFalse(SettingGroups::isName('Acme Gallery'));
		$this->assertSame('acme/gallery', SettingGroups::name(SettingGroups::key('acme/gallery')));
	}

	public function testReadsOnlyTheBootGroupsAtBoot(): void
	{
		$this->writeTemporaryFile('user/data/settings/app.json', '{"name": "Notes"}');
		$this->writeTemporaryFile('user/data/settings/feed.json', '{"limit": 7}');

		$app = $this->scratchApplication();

		$this->assertSame('Notes', $app->container()->make(AppConfig::class)->name, 'A boot group is laid over at boot.');
		$this->assertSame(7, $app->container()->make(FeedConfig::class)->limit, 'Another when its config is first asked for (D-673).');
	}
}
