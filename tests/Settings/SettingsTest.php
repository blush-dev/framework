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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\ConfigRepository;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Feed\FeedConfig;
use Blush\Routing\RouteConfig;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Setting::class)]
#[CoversClass(Settings::class)]
#[CoversClass(SettingsFile::class)]
final class SettingsTest extends TestCase
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function file(): SettingsFile
	{
		return new SettingsFile(Paths::fromRoot($this->temporaryDirectory()));
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

	public function testReadsAndWritesTheFile(): void
	{
		$file = $this->file();

		$this->assertTrue($file->read()->isEmpty(), 'A missing file is no settings.');

		$file->update(static fn (Settings $settings): Settings => $settings->with(['app.name' => 'Café', 'sitemap.disallow' => ['/a/']]));

		$this->assertSame(['app' => ['name' => 'Café'], 'sitemap' => ['disallow' => ['/a/']]], json_decode((string) file_get_contents($file->path()), true));
		$this->assertStringContainsString('"Café"', (string) file_get_contents($file->path()), 'Text is written as is.');
		$this->assertSame('Café', $file->read()->get(Setting::Name));
	}

	public function testKeepsAnEditorsSchemaKey(): void
	{
		$this->writeTemporaryFile('user/data/settings.json', '{"$schema": "settings.schema.json", "app": {"name": "Blog"}}');
		$file = $this->file();

		$this->assertSame('Blog', $file->read()->get(Setting::Name), 'It isn\'t a setting (D-491).');

		$file->update(static fn (Settings $settings): Settings => $settings->with(['app.name' => 'Notes']));

		$this->assertSame(['$schema' => 'settings.schema.json', 'app' => ['name' => 'Notes']], json_decode((string) file_get_contents($file->path()), true), 'Kept first.');
	}

	public function testRefusesABrokenFile(): void
	{
		$cases = ['{"app": ', '["app"]', '{"app": "Name"}', '{"app": {"url": "https://x.test"}}', '{"colour": {"red": true}}', '{"feed": {"limit": 0}}'];

		foreach ($cases as $json) {
			$this->writeTemporaryFile('user/data/settings.json', $json);

			try {
				$this->file()->read();
				$this->fail("Read {$json}.");
			} catch (InvalidSetting $error) {
				$this->assertStringContainsString('user/data/settings.json', $error->getMessage(), $json);
			}
		}
	}
}
