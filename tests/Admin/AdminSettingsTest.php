<?php

/**
 * Admin Settings screen's API tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\SettingsController;
use Blush\Admin\SettingsEditController;
use Blush\Content\Type\ContentConfig;
use Blush\Core\AppConfig;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Feed\FeedConfig;

#[CoversClass(SettingsController::class)]
#[CoversClass(SettingsEditController::class)]
final class AdminSettingsTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Returns a setting from `GET settings`, by group and key.
	 *
	 * @param  array<mixed> $answer
	 * @return array<mixed>
	 */
	private function setting(array $answer, string $group, string $key): array
	{
		$found = array_find(is_array($answer['groups'] ?? null) ? $answer['groups'] : [], static fn (mixed $item): bool => is_array($item) && ($item['key'] ?? null) === $group);
		$this->assertIsArray($found, $group);
		$item = array_find(is_array($found['items'] ?? null) ? $found['items'] : [], static fn (mixed $item): bool => is_array($item) && ($item['key'] ?? null) === $key);
		$this->assertIsArray($item, "{$group}.{$key}");

		return $item;
	}

	public function testShowsTheSettingsAndWhichAreDefaults(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['post' => ['path' => 'posts']], 'home' => 'post']);\n");
		$this->writeTemporaryFile('config/feed.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Feed\\FeedConfig(content: false);\n");
		$this->boot(roles: ['administrator'], environment: ['APP_NAME' => 'Notes', 'APP_TIMEZONE' => 'America/Chicago', 'PUBLISH_SECRET' => str_repeat('p', 40)]);
		$this->login();

		$general = self::json($this->send('GET', '/settings/general'));
		$reading = self::json($this->send('GET', '/settings/reading'));
		$search  = self::json($this->send('GET', '/settings/search'));
		$system  = self::json($this->send('GET', '/settings/system'));

		$this->assertSame(['site', 'dates', 'environment'], array_column(is_array($general['groups'] ?? null) ? $general['groups'] : [], 'key'));
		$this->assertSame(['home', 'feeds'], array_column(is_array($reading['groups'] ?? null) ? $reading['groups'] : [], 'key'));
		$this->assertSame(['addresses', 'search'], array_column(is_array($search['groups'] ?? null) ? $search['groups'] : [], 'key'));
		$this->assertSame(['types', 'caching', 'publishing'], array_column(is_array($system['groups'] ?? null) ? $system['groups'] : [], 'key'));
		$this->assertSame(404, $this->send('GET', '/settings/permalinks')->getStatusCode());

		$name = $this->setting($general, 'site', 'name');
		$this->assertSame('Notes', $name['value'] ?? null);
		$this->assertFalse($name['default'] ?? null);
		$this->assertSame('America/Chicago', $this->setting($general, 'dates', 'timezone')['value'] ?? null);
		$this->assertSame('The latest posts', $this->setting($reading, 'home', 'home')['value'] ?? null);
		$this->assertFalse($this->setting($search, 'addresses', 'trailingSlash')['value'] ?? null);
		$this->assertTrue($this->setting($search, 'addresses', 'trailingSlash')['default'] ?? null);
		$this->assertFalse($this->setting($reading, 'feeds', 'content')['value'] ?? null);
		$this->assertFalse($this->setting($reading, 'feeds', 'content')['default'] ?? null);
		$this->assertSame(['RSS', 'Atom', 'JSON Feed'], $this->setting($reading, 'feeds', 'formats')['value'] ?? null);
		$this->assertFalse($this->setting($system, 'caching', 'enabled')['value'] ?? null, 'Caching is off in development.');
		$this->assertTrue($this->setting($system, 'publishing', 'webhook')['value'] ?? null);
		$this->assertStringNotContainsString(str_repeat('p', 40), json_encode($system) ?: '', 'Secrets are never sent.');

		$home = $this->setting($reading, 'home', 'home');
		$this->assertSame('content.home', $home['setting'] ?? null);
		$this->assertSame('select', $home['control'] ?? null);
		$this->assertSame('post', $home['input'] ?? null);
		$this->assertSame(['', 'post'], array_column(is_array($home['options'] ?? null) ? $home['options'] : [], 'value'));
		$this->assertFalse($home['saved'] ?? null);
		$this->assertSame('config/content.php', $home['file'] ?? null);
		$this->assertSame(['rss', 'atom', 'json'], $this->setting($reading, 'feeds', 'formats')['input'] ?? null);

		$url = $this->setting($general, 'site', 'url');
		$this->assertArrayNotHasKey('setting', $url, 'The address stays in config, beside the name.');
		$this->assertSame('config/app.php', $url['file'] ?? null);
	}

	public function testWarnsAboutDetailedErrorsOnALiveSite(): void
	{
		$this->boot(roles: ['administrator'], environment: ['APP_ENV' => 'production', 'APP_DEBUG' => 'true']);
		$this->login();

		$this->assertNotNull($this->setting(self::json($this->send('GET', '/settings/general')), 'environment', 'debug')['warning'] ?? null);
	}

	public function testNeedsSiteSettings(): void
	{
		$this->boot(roles: ['editor']);
		$this->login();

		$this->assertSame(403, $this->send('GET', '/settings/general')->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/settings', ['set' => ['app.name' => 'Mine']])->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/settings/refresh')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/settings.json');
	}

	public function testSavesSettingsOverTheConfig(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: posts\n");
		$this->boot(roles: ['administrator'], environment: ['APP_NAME' => 'Notes']);
		$this->login();

		$response = $this->write('PATCH', '/settings', ['set' => ['app.name' => '  Field Notes ', 'app.timezone' => 'Europe/Brussels', 'content.home' => 'post', 'feed.formats' => ['json', 'rss', 'json'], 'feed.limit' => 20, 'sitemap.disallow' => ['/drafts/', '', '/drafts/']]]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertTrue(self::json($response)['refresh'] ?? null, 'The home page and time zone need a refresh.');
		$this->assertSame(
			[
				'app'     => ['name' => 'Field Notes', 'timezone' => 'Europe/Brussels'],
				'content' => ['home' => 'post'],
				'feed'    => ['formats' => ['rss', 'json'], 'limit' => 20],
				'sitemap' => ['disallow' => ['/drafts/']]
			],
			json_decode($this->file('user/data/settings.json'), true)
		);

		$this->assertSame(200, $this->write('POST', '/settings/refresh')->getStatusCode());

		$container = $this->scratchApplication(['APP_NAME' => 'Notes'])->container();
		$this->assertSame('Field Notes', $container->make(AppConfig::class)->name, 'A saved setting wins over .env.');
		$this->assertSame('Europe/Brussels', $container->make(AppConfig::class)->timezone);
		$this->assertSame('post', $container->make(ContentConfig::class)->home);
		$this->assertSame(20, $container->make(FeedConfig::class)->limit);

		$answer = self::json($this->write('PATCH', '/settings', ['set' => ['feed.content' => false], 'unset' => ['app.name', 'content.home', 'app.timezone', 'feed.formats', 'feed.limit', 'sitemap.disallow']]));
		$this->assertSame(['feed' => ['content' => false]], $answer['saved'] ?? null);
		$this->assertTrue($answer['refresh'] ?? null, 'Unsetting the home page needs a refresh too.');
		$this->assertSame('Notes', $this->scratchApplication(['APP_NAME' => 'Notes'])->container()->make(AppConfig::class)->name, 'Unset, the config is used again.');

		$this->assertFalse(self::json($this->write('PATCH', '/settings', ['unset' => ['feed.content']]))['refresh'] ?? null);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/settings.json', 'Nothing saved removes the file.');
	}

	public function testRefusesValuesThatDontFit(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.yaml', "kind: taxonomy\nfolder: topics\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$refused = [
			['app.name' => '   '],
			['app.name' => "Two\nlines"],
			['app.locale' => 'English'],
			['app.timezone' => 'Mars/Olympus'],
			['content.home' => 'topic'],
			['content.home' => 'missing'],
			['feed.formats' => ['rss', 'gopher']],
			['feed.limit' => 0],
			['feed.limit' => '10'],
			['routes.trailingSlash' => 'yes'],
			['sitemap.disallow' => ['drafts']],
			['app.url' => 'https://elsewhere.test'],
			['name' => 'Flat']
		];

		foreach ($refused as $set) {
			$response = $this->write('PATCH', '/settings', ['set' => ['app.name' => 'Fine', ...$set]]);
			$this->assertSame(422, $response->getStatusCode(), (string) json_encode($set));
			$this->assertNotSame('', self::json($response)['error'] ?? '');
		}

		$this->assertSame(422, $this->write('PATCH', '/settings', ['unset' => ['app.environment']])->getStatusCode());
		$this->assertSame(400, $this->write('PATCH', '/settings', ['set' => ['a', 'b']])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/settings.json', 'A refusal writes nothing.');
	}

	public function testCompilingLeavesTheSettingsOut(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: posts\n");
		$this->writeTemporaryFile('user/data/settings.json', '{"app": {"name": "Saved"}}');
		$bootstrap = new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production', 'APP_URL' => 'https://example.test', 'APP_NAME' => 'Configured']);
		$bootstrap->compile();

		$this->assertStringNotContainsString('Saved', $this->file(substr($bootstrap->compiledPath(CompiledCache::Config), strlen($this->temporaryDirectory()) + 1)));
		$this->assertSame('Saved', $bootstrap->createApplication()->container()->make(AppConfig::class)->name, 'A compiled site still reads the settings.');
	}

	public function testRefreshCompilesWhatASaveCleared(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: posts\n");
		$this->boot(roles: ['administrator'], environment: ['APP_ENV' => 'production']);
		$this->login();
		$bootstrap = $this->app->container()->make(Bootstrap::class);
		$bootstrap->compile();
		$routes = $bootstrap->compiledPath(CompiledCache::Routes);

		$this->assertFalse(self::json($this->write('PATCH', '/settings', ['set' => ['app.name' => 'Renamed']]))['refresh'] ?? null);
		$this->assertFileExists($routes, 'A name needs no refresh.');

		$this->assertTrue(self::json($this->write('PATCH', '/settings', ['set' => ['content.home' => 'post']]))['refresh'] ?? null);
		$this->assertFileDoesNotExist($routes, 'The compiled routes go until the refresh.');
		$this->assertFileDoesNotExist($bootstrap->compiledPath(CompiledCache::ContentTypes));

		$this->assertTrue(self::json($this->write('POST', '/settings/refresh'))['compiled'] ?? null);
		$this->assertFileExists($routes);
		$this->assertFileExists($bootstrap->compiledPath(CompiledCache::ContentTypes));
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	private function file(string $relative): string
	{
		return (string) @file_get_contents($this->temporaryDirectory() . "/{$relative}");
	}
}
