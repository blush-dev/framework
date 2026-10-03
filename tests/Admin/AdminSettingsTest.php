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
use Blush\Settings\SiteSettings;
use Blush\Settings\SettingsTargets;

#[CoversClass(SettingsController::class)]
#[CoversClass(SettingsEditController::class)]
#[CoversClass(SiteSettings::class)]
#[CoversClass(SettingsTargets::class)]
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
		$ai      = self::json($this->send('GET', '/settings/ai'));
		$system  = self::json($this->send('GET', '/settings/system'));

		$this->assertSame(['site', 'dates', 'environment'], array_column(is_array($general['groups'] ?? null) ? $general['groups'] : [], 'key'));
		$this->assertSame(['home', 'feeds'], array_column(is_array($reading['groups'] ?? null) ? $reading['groups'] : [], 'key'));
		$this->assertSame(['addresses', 'search'], array_column(is_array($search['groups'] ?? null) ? $search['groups'] : [], 'key'));
		$this->assertSame(['markdown', 'crawlers'], array_column(is_array($ai['groups'] ?? null) ? $ai['groups'] : [], 'key'));
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
		$field = is_array($home['field'] ?? null) ? $home['field'] : [];
		$this->assertSame('select', $field['control'] ?? null, 'Edited as a field (D-343).');
		$this->assertSame(['post'], $field['options'] ?? null);
		$this->assertSame(['post' => 'The latest posts'], $field['choices'] ?? null);
		$this->assertSame('The page at user/content/index.md', $field['caption'] ?? null);
		$this->assertSame('post', $home['input'] ?? null);
		$this->assertFalse($home['saved'] ?? null);
		$this->assertSame('config/content.php', $home['file'] ?? null);
		$this->assertSame(['rss', 'atom', 'json'], $this->setting($reading, 'feeds', 'formats')['input'] ?? null);
		$formats = $this->setting($reading, 'feeds', 'formats')['field'] ?? null;
		$this->assertSame('checks', is_array($formats) ? $formats['control'] ?? null : null);
		$this->assertSame(1, is_array($this->setting($reading, 'feeds', 'limit')['field'] ?? null) ? $this->setting($reading, 'feeds', 'limit')['field']['min'] ?? null : null);

		$url = $this->setting($general, 'site', 'url');
		$this->assertArrayNotHasKey('setting', $url, 'The address stays in config, beside the name.');
		$this->assertSame('config/app.php', $url['file'] ?? null);
	}

	public function testShowsAndSavesTheAiScreen(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['post' => ['path' => 'posts', 'llms' => false], 'note' => ['path' => 'notes'], 'tag' => ['path' => 'tags', 'taxonomy' => true]]]);\n");
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\n");
		$this->writeTemporaryFile('user/content/notes/one.md', "---\ntitle: One\n---\n");
		$this->boot(roles: ['administrator'], environment: ['APP_ENV' => 'production']);
		$this->login();

		$ai = self::json($this->send('GET', '/settings/ai'));

		$llms = $this->setting($ai, 'markdown', 'llms');
		$this->assertSame(['llms.enabled', true, true, 'config/llms.php'], [$llms['setting'] ?? null, $llms['value'] ?? null, $llms['input'] ?? null, $llms['file'] ?? null]);
		$this->assertSame('Lists 2 pages', $this->setting($ai, 'markdown', 'llmsTxt')['value'] ?? null);
		$full = $this->setting($ai, 'markdown', 'full');
		$this->assertSame(['llms.full', false, true, null], [$full['setting'] ?? null, $full['value'] ?? null, $full['default'] ?? null, $full['link'] ?? null]);
		$this->assertSame(['setting' => 'llms.enabled', 'note' => 'It needs the Markdown copies on.'], $full['requires'] ?? null, 'Locked while the copies are off (D-402).');
		$this->assertSame(['label' => 'View llms.txt', 'href' => 'https://example.test/llms.txt'], $this->setting($ai, 'markdown', 'llmsTxt')['link'] ?? null);
		$this->assertSame(['Pages', 'Notes'], $this->setting($ai, 'markdown', 'types')['value'] ?? null, 'Types whose llms option is on; never taxonomies.');
		$this->assertSame('None', $this->setting($ai, 'markdown', 'description')['value'] ?? null);

		$block = $this->setting($ai, 'crawlers', 'blockAi');
		$this->assertSame(['sitemap.blockAi', [], [], null], [$block['setting'] ?? null, $block['value'] ?? null, $block['input'] ?? null, $block['warning'] ?? null]);
		$field = is_array($block['field'] ?? null) ? $block['field'] : [];
		$this->assertSame('checks', $field['control'] ?? null);
		$item = is_array($field['item'] ?? null) ? $field['item'] : [];
		$this->assertSame('Training crawlers: GPTBot, ClaudeBot, CCBot, Google-Extended, Applebot-Extended, Bytespider, meta-externalagent', is_array($item['choices'] ?? null) ? $item['choices']['training'] ?? null : null);

		$response = $this->write('PATCH', '/settings', ['set' => ['app.description' => ' Notes on the web. ', 'sitemap.blockAi' => ['search', 'training'], 'llms.enabled' => false, 'llms.full' => true]]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertTrue(self::json($response)['refresh'] ?? null, 'Markdown copies change the routes.');
		$this->assertSame(['app' => ['description' => 'Notes on the web.'], 'llms' => ['enabled' => false, 'full' => true], 'sitemap' => ['blockAi' => ['training', 'search']]], json_decode($this->file('user/data/settings.json'), true));

		// Settings are read at boot; the account is already there.
		$this->app = $this->scratchApplication(['APP_ENV' => 'production', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();
		$this->login();
		$ai = self::json($this->send('GET', '/settings/ai'));

		$this->assertSame(['Training crawlers', 'AI search crawlers'], $this->setting($ai, 'crawlers', 'blockAi')['value'] ?? null);
		$this->assertSame('Off, with the Markdown copies', $this->setting($ai, 'markdown', 'llmsTxt')['value'] ?? null);
		$this->assertNull($this->setting($ai, 'markdown', 'full')['link'] ?? null, 'Not served without the copies.');
		$this->assertSame('Notes on the web.', $this->setting($ai, 'markdown', 'description')['value'] ?? null);
		$this->assertSame('Notes on the web.', $this->setting(self::json($this->send('GET', '/settings/general')), 'site', 'description')['value'] ?? null);

		foreach ([['app.description' => "Two\nlines"], ['app.description' => str_repeat('x', 301)], ['sitemap.blockAi' => ['robots']], ['sitemap.blockAi' => 'training']] as $set) {
			$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => $set])->getStatusCode(), (string) json_encode($set));
		}
	}

	public function testGivesTheFullFilesSize(): void
	{
		$this->writeTemporaryFile('config/llms.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Llms\\LlmsConfig(full: true);\n");
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\nHello.\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$full = $this->setting(self::json($this->send('GET', '/settings/ai')), 'markdown', 'full');
		$link = is_array($full['link'] ?? null) ? $full['link'] : [];

		$this->assertMatchesRegularExpression('/^View llms-full\.txt \(\d+ bytes\)$/', is_string($link['label'] ?? null) ? $link['label'] : '');
		$this->assertNull($full['warning'] ?? null, 'Small enough for the page cache.');
	}

	public function testWarnsWhenAiCrawlerChoicesArentUsed(): void
	{
		$this->boot(roles: ['administrator']);
		$this->login();

		$warning = $this->setting(self::json($this->send('GET', '/settings/ai')), 'crawlers', 'blockAi')['warning'] ?? null;

		$this->assertIsString($warning);
		$this->assertStringContainsString('Outside production', $warning);
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

	public function testFieldSetsAddSettings(): void
	{
		$this->writeTemporaryFile('user/data/fields/brand.yaml', "label: Brand\ndescription: How the site presents itself.\ntargets: [settings:general]\nfields:\n  tagline:\n    required: true\n  accent:\n    type: enum\n    options: [red, blue]\n    default: blue\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$general = self::json($this->send('GET', '/settings/general'));
		$groups  = is_array($general['groups'] ?? null) ? $general['groups'] : [];

		$this->assertSame(['site', 'dates', 'environment', 'set-brand'], array_column($groups, 'key'), 'A set\'s group after the screen\'s own.');
		$tagline = $this->setting($general, 'set-brand', 'site-tagline');
		$this->assertSame('site.tagline', $tagline['setting'] ?? null);
		$this->assertFalse($tagline['saved'] ?? null);
		$this->assertNull($tagline['file'] ?? null);
		$this->assertSame('blue', $this->setting($general, 'set-brand', 'site-accent')['input'] ?? null, 'Its default until it\'s saved.');
		$this->assertSame('How the site presents itself.', array_column($groups, 'hint', 'key')['set-brand'] ?? null);

		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['site.accent' => 'green']])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['site.tagline' => '']])->getStatusCode(), 'Required.');
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['site.nope' => 'x']])->getStatusCode());

		$saved = self::json($this->write('PATCH', '/settings', ['set' => ['site.tagline' => 'Notes from the field', 'app.name' => 'Field Notes']]));
		$this->assertSame(['app' => ['name' => 'Field Notes'], 'site' => ['tagline' => 'Notes from the field']], $saved['saved'] ?? null);
		$this->assertFalse($saved['refresh'] ?? null);

		$site = $this->scratchApplication()->container()->make(SiteSettings::class);
		$this->assertSame('Notes from the field', $site->get('tagline'));
		$this->assertSame('blue', $site->get('accent'), 'A field\'s default.');
		$this->assertSame('fallback', $site->get('missing', 'fallback'));

		$this->assertSame(['app' => ['name' => 'Field Notes']], self::json($this->write('PATCH', '/settings', ['unset' => ['site.tagline']]))['saved'] ?? null);
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
