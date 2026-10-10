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
use Blush\Admin\MediaUploadController;
use Blush\Admin\SettingsController;
use Blush\Admin\SettingsEditController;
use Blush\Content\ContentConfig;
use Blush\Core\AppConfig;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Feed\FeedConfig;
use Blush\Settings\SiteSettings;
use Blush\Settings\SettingsTargets;
use Blush\Tests\WritesContentConfig;
use Blush\Tests\SavedSettings;

#[CoversClass(SettingsController::class)]
#[CoversClass(SettingsEditController::class)]
#[CoversClass(SiteSettings::class)]
#[CoversClass(SettingsTargets::class)]
final class AdminSettingsTest extends TestCase
{
	use BootsAdmin;
	use SavedSettings;
	use WritesContentConfig;

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
		$this->contentConfig(['types' => ['post' => ['urls' => ['prefix' => 'posts']]], 'home' => 'post']);
		$this->writeTemporaryFile('config/feed.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Feed\\FeedConfig(content: false);\n");
		$this->boot(roles: ['administrator'], environment: ['APP_NAME' => 'Notes', 'APP_TIMEZONE' => 'America/Chicago', 'PUBLISH_SECRET' => str_repeat('p', 40)]);
		$this->login();

		$general = self::json($this->send('GET', '/settings/general'));
		$reading = self::json($this->send('GET', '/settings/reading'));
		$search  = self::json($this->send('GET', '/settings/search'));
		$ai      = self::json($this->send('GET', '/settings/ai'));
		$system  = self::json($this->send('GET', '/settings/system'));

		$this->assertSame(['site', 'dates', 'accounts', 'environment'], array_column(is_array($general['groups'] ?? null) ? $general['groups'] : [], 'key'));
		$this->assertSame(['home', 'feeds'], array_column(is_array($reading['groups'] ?? null) ? $reading['groups'] : [], 'key'));
		$this->assertSame(['addresses', 'search'], array_column(is_array($search['groups'] ?? null) ? $search['groups'] : [], 'key'));
		$this->assertSame(['markdown', 'crawlers'], array_column(is_array($ai['groups'] ?? null) ? $ai['groups'] : [], 'key'));
		$this->assertSame(['types', 'caching', 'publishing'], array_column(is_array($system['groups'] ?? null) ? $system['groups'] : [], 'key'));
		$this->assertSame(404, $this->send('GET', '/settings/permalinks')->getStatusCode());

		$name = $this->setting($general, 'site', 'name');
		$this->assertSame('Notes', $name['value'] ?? null);
		$this->assertFalse($name['default'] ?? null);
		$this->assertSame('America/Chicago', $this->setting($general, 'dates', 'timezone')['value'] ?? null);
		$locales = $this->setting($general, 'site', 'locale')['locales'] ?? null;
		$this->assertIsArray($locales, 'The language is picked from a menu (D-441).');
		$this->assertContains(['value' => 'fr_CA', 'label' => 'Français (Canada)', 'hint' => 'French (Canada)', 'depth' => 1], $locales);
		$zones = $this->setting($general, 'dates', 'timezone')['menu'] ?? null;
		$this->assertIsArray($zones, 'The time zone is a searchable menu (D-444).');
		$this->assertContains('America/Chicago', array_column($zones, 'value'));
		$dateFormat = $this->setting($general, 'dates', 'dateFormat');
		$this->assertSame(['app.dateFormat', 'long', true], [$dateFormat['setting'] ?? null, $dateFormat['input'] ?? null, $dateFormat['default'] ?? null]);
		$this->assertSame('https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax', is_array($dateFormat['link'] ?? null) ? $dateFormat['link']['href'] ?? null : null, 'It links to the pattern letters (D-446).');
		$this->assertContains('long', array_column(is_array($dateFormat['formats'] ?? null) ? $dateFormat['formats'] : [], 'value'), 'The date format is a menu of how each reads (D-445).');
		$this->assertContains('HH:mm', array_column(is_array($this->setting($general, 'dates', 'timeFormat')['formats'] ?? null) ? $this->setting($general, 'dates', 'timeFormat')['formats'] : [], 'value'));
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

		$site = array_find(is_array($general['groups'] ?? null) ? $general['groups'] : [], static fn (mixed $group): bool => is_array($group) && ($group['key'] ?? null) === 'site');
		$this->assertNotContains('untranslated', array_column(is_array($site) && is_array($site['items'] ?? null) ? $site['items'] : [], 'key'), 'A site in one language has nothing to translate (D-468).');

		$url = $this->setting($general, 'site', 'url');
		$this->assertArrayNotHasKey('setting', $url, 'The address stays in config, beside the name.');
		$this->assertSame('config/app.php', $url['file'] ?? null);
	}

	public function testShowsAndSavesTheAiScreen(): void
	{
		$this->contentConfig(['types' => ['post' => ['urls' => ['prefix' => 'posts'], 'llms' => false], 'note' => ['urls' => ['prefix' => 'notes']], 'tag' => ['urls' => ['prefix' => 'tags'], 'order' => 'position', 'llms' => false]], 'relations' => ['tag' => ['kind' => 'classify', 'to' => ['tag']]]]);
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\n");
		$this->writeTemporaryFile('user/content/_note/one.md', "---\ntitle: One\n---\n");
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
		$this->assertSame(['Pages', 'Notes'], $this->setting($ai, 'markdown', 'types')['value'] ?? null, 'Types whose llms option is on.');
		$this->assertSame('None', $this->setting($ai, 'markdown', 'description')['value'] ?? null);

		$block = $this->setting($ai, 'crawlers', 'blockAi');
		$this->assertSame(['sitemap.blockAi', [], [], null], [$block['setting'] ?? null, $block['value'] ?? null, $block['input'] ?? null, $block['warning'] ?? null]);
		$field = is_array($block['field'] ?? null) ? $block['field'] : [];
		$this->assertSame('checks', $field['control'] ?? null);
		$item = is_array($field['item'] ?? null) ? $field['item'] : [];
		$this->assertSame('Training crawlers', is_array($item['choices'] ?? null) ? $item['choices']['training'] ?? null : null);
		$this->assertSame(['text' => 'Collect pages to train models.', 'code' => 'GPTBot · ClaudeBot · CCBot · Google-Extended · Applebot-Extended · Bytespider · meta-externalagent'], is_array($field['details'] ?? null) ? $field['details']['training'] ?? null : null);

		$response = $this->write('PATCH', '/settings', ['set' => ['app.description' => ' Notes on the web. ', 'sitemap.blockAi' => ['search', 'training'], 'llms.enabled' => false, 'llms.full' => true]]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertTrue(self::json($response)['refresh'] ?? null, 'Markdown copies change the routes.');
		$this->assertSame(['app' => ['description' => 'Notes on the web.'], 'llms' => ['enabled' => false, 'full' => true], 'sitemap' => ['blockAi' => ['training', 'search']]], $this->savedSettings());

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

	public function testShowsAndSavesTheWritingScreen(): void
	{
		$this->writeTemporaryFile('user/content/_profile/sam.md', "---\ntitle: Sam\n---\n");
		$this->writeTemporaryFile('user/content/hello.md', "---\ntitle: Hello\n---\nThanks, @sam and @nobody. \"Quoted\"\n\n## Part <b>one</b>\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$page = (string) $this->visit('GET', '/hello')->getBody();

		$this->assertStringContainsString('Thanks, <a class="mention" href="https://example.test/profiles/sam"><span class="mention__at">@</span><span class="mention__name">sam</span></a> and @nobody. “Quoted”', $page, 'A published profile is linked; nobody\'s name stays text.');
		$this->assertStringContainsString('<h2>Part <b>one</b><a id="part-one" href="#part-one" class="heading-anchor"', $page);

		$writing  = self::json($this->send('GET', '/settings/writing'));
		$mentions = $this->setting($writing, 'markdown', 'mentions');
		$this->assertSame(['markdown.mentions', true, true, 'config/markdown.php'], [$mentions['setting'] ?? null, $mentions['value'] ?? null, $mentions['default'] ?? null, $mentions['file'] ?? null]);
		$html = $this->setting($writing, 'html', 'html');
		$this->assertSame(['markdown.html', 'Allowed', 'allow', 'segmented'], [$html['setting'] ?? null, $html['value'] ?? null, $html['input'] ?? null, $html['kind'] ?? null]);
		$groups = is_array($writing['groups'] ?? null) ? $writing['groups'] : [];
		$this->assertSame(['config/markdown.php', 'config/markdown.php', null], array_map(static fn (mixed $group): mixed => is_array($group) ? $group['source'] ?? null : null, $groups), 'Markdown and HTML name their file once.');
		$field = is_array($html['field'] ?? null) ? $html['field'] : [];
		$this->assertSame(['allow' => 'Allowed', 'filter' => 'Filtered', 'escape' => 'Shown as Text'], $field['choices'] ?? null);

		$response = $this->write('PATCH', '/settings', ['set' => ['markdown.mentions' => false, 'markdown.smartPunctuation' => false, 'markdown.headingAnchors' => false, 'markdown.html' => 'escape']]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['markdown' => ['mentions' => false, 'smartPunctuation' => false, 'headingAnchors' => false, 'html' => 'escape']], $this->savedSettings());
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['markdown.html' => 'sometimes']])->getStatusCode());

		// Settings are read at boot; the account is already there.
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$page = (string) $this->visit('GET', '/hello')->getBody();

		$this->assertStringContainsString('Thanks, @sam and @nobody. &quot;Quoted&quot;', $page);
		$this->assertStringContainsString('<h2>Part &lt;b&gt;one&lt;/b&gt;</h2>', $page);
	}

	public function testTurnsEmbedProvidersOffOnTheWritingScreen(): void
	{
		$this->writeTemporaryFile('user/content/hello.md', "---\ntitle: Hello\n---\n::embed{url=\"https://www.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity\"}\n");
		$this->writeTemporaryFile('config/embed.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Embed\\EmbedConfig(fetch: false);\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$this->assertStringContainsString('src="https://embed.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity"', (string) $this->visit('GET', '/hello')->getBody());

		$off = $this->setting(self::json($this->send('GET', '/settings/writing')), 'embeds', 'off');
		$this->assertSame(['embed.off', 'embeds', [], true, 'config/embed.php'], [$off['setting'] ?? null, $off['kind'] ?? null, $off['input'] ?? null, $off['default'] ?? null, $off['file'] ?? null]);
		$providers = is_array($off['providers'] ?? null) ? $off['providers'] : [];
		$this->assertContains(['name' => 'youtube', 'label' => 'YouTube', 'hosts' => ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be']], $providers);
		$this->assertContains(['name' => 'ted', 'label' => 'TED', 'hosts' => ['ted.com', 'embed.ted.com']], $providers);

		$response = $this->write('PATCH', '/settings', ['set' => ['embed.off' => ['ted', 'codepen', 'ted']]]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['embed' => ['off' => ['codepen', 'ted']]], $this->savedSettings());
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['embed.off' => ['Not a name']]])->getStatusCode());

		// Settings are read at boot; the account is already there.
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$page = (string) $this->visit('GET', '/hello')->getBody();

		$this->assertStringNotContainsString('<iframe', $page);
		$this->assertStringContainsString('<a href="https://www.ted.com/talks/sir_ken_robinson_do_schools_kill_creativity">', $page);
	}

	public function testShowsAndSavesUntranslatedPagesOnAMultilingualSite(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Core\\AppConfig::fromArray(['environment' => 'development', 'languages' => ['fr' => 'fr_FR']]);\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$item  = $this->setting(self::json($this->send('GET', '/settings/general')), 'site', 'untranslated');
		$field = is_array($item['field'] ?? null) ? $item['field'] : [];

		$this->assertSame(['app.untranslated', 'redirect', true, 'Redirect to the original'], [$item['setting'] ?? null, $item['input'] ?? null, $item['default'] ?? null, $item['value'] ?? null]);
		$this->assertSame('radios', $field['control'] ?? null, 'Each choice is shown with what it does (D-468).');
		$this->assertSame(['hide', 'redirect', 'include'], $field['options'] ?? null);
		$this->assertSame('Redirect, and list originals too', is_array($field['choices'] ?? null) ? $field['choices']['include'] ?? null : null);
		$this->assertIsArray(is_array($field['details'] ?? null) ? $field['details']['hide'] ?? null : null);

		$response = $this->write('PATCH', '/settings', ['set' => ['app.untranslated' => 'include']]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertFalse(self::json($response)['refresh'] ?? null, 'It changes no addresses.');
		$this->assertSame(['app' => ['untranslated' => 'include']], $this->savedSettings());
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['app.untranslated' => 'fallback']])->getStatusCode());
	}

	public function testShowsAndSavesSignups(): void
	{
		$this->boot(roles: ['administrator']);
		$this->login();

		$general = self::json($this->send('GET', '/settings/general'));
		$signups = $this->setting($general, 'accounts', 'signups');
		$role    = $this->setting($general, 'accounts', 'signupRole');
		$field   = is_array($role['field'] ?? null) ? $role['field'] : [];

		$this->assertSame(['auth.signups', false, true, 'config/auth.php'], [$signups['setting'] ?? null, $signups['value'] ?? null, $signups['default'] ?? null, $signups['file'] ?? null], 'Off by default (D-518).');
		$this->assertSame(['auth.signupRole', 'member', 'Member'], [$role['setting'] ?? null, $role['input'] ?? null, $role['value'] ?? null]);
		$this->assertSame(['editor', 'author', 'contributor', 'member'], $field['options'] ?? null, 'Never the owner or an administrator.');
		$this->assertSame('Contributor', is_array($field['choices'] ?? null) ? $field['choices']['contributor'] ?? null : null);
		$this->assertSame('auth.signups', is_array($role['requires'] ?? null) ? $role['requires']['setting'] ?? null : null);

		$response = $this->write('PATCH', '/settings', ['set' => ['auth.signups' => true, 'auth.signupRole' => 'contributor']]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertFalse(self::json($response)['refresh'] ?? null, 'It changes no addresses.');
		$this->assertSame(['auth' => ['signups' => true, 'signupRole' => 'contributor']], $this->savedSettings());

		// Settings are read at boot; the account is already there.
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$general = self::json($this->send('GET', '/settings/general'));
		$this->assertSame([true, 'Contributor'], [$this->setting($general, 'accounts', 'signups')['value'] ?? null, $this->setting($general, 'accounts', 'signupRole')['value'] ?? null]);
	}

	public function testSavesAndPreviewsTheDateAndTimeFormats(): void
	{
		$this->boot(roles: ['administrator']);
		$this->login();

		$preview = self::json($this->send('GET', '/settings/date-format?format=' . rawurlencode("y 'year'") . '&kind=date'));
		$this->assertSame(1, preg_match('/^\d{4} year$/', is_string($preview['text'] ?? null) ? $preview['text'] : ''), (string) json_encode($preview));
		$this->assertSame(422, $this->send('GET', '/settings/date-format?format=' . rawurlencode('MMMM d at y'))->getStatusCode());

		$response = $this->write('PATCH', '/settings', ['set' => ['app.dateFormat' => ' d MMMM y ', 'app.timeFormat' => 'short']]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertFalse(self::json($response)['refresh'] ?? null, 'Formats change no addresses.');
		$this->assertSame(['app' => ['dateFormat' => 'd MMMM y', 'timeFormat' => 'short']], $this->savedSettings());

		foreach ([['app.dateFormat' => ''], ['app.dateFormat' => "d 'de MMMM"], ['app.timeFormat' => 'jj:mm'], ['app.timeFormat' => 5]] as $set) {
			$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => $set])->getStatusCode(), (string) json_encode($set));
		}
	}

	public function testShowsAndSavesTheUploadRules(): void
	{
		$this->boot(roles: ['administrator']);
		$this->login();

		$item = $this->setting(self::json($this->send('GET', '/settings/media')), 'uploads', 'uploads');
		$data = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];
		$kind = is_array($data['kinds'] ?? null) ? $data['kinds'] : [];

		$this->assertSame(['media.uploads', 'uploads', true, false], [$item['setting'] ?? null, $item['kind'] ?? null, $item['default'] ?? null, $item['saved'] ?? null]);
		$this->assertSame(['enabled' => true, 'maxSize' => null, 'path' => '{year}/{month}', 'kinds' => []], $item['input'] ?? null);
		$this->assertSame(['image', 'video', 'audio', 'document', 'file'], array_column($kind, 'key'));
		$this->assertSame(['pdf'], is_array($kind[3] ?? null) ? $kind[3]['extensions'] ?? null : null, 'Only the documents the site allows.');
		$this->assertSame(['year', 'month', 'day', 'kind'], $data['tokens'] ?? null);

		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['media.uploads' => ['path' => '../up']]])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['media.uploads' => ['kinds' => ['zip' => []]]]])->getStatusCode());

		$server = MediaUploadController::limit();

		if ($server !== null) {
			$over = intdiv($server, 1024 * 1024) + 1;

			$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['media.uploads' => ['kinds' => ['video' => ['maxSize' => $over]]]]])->getStatusCode(), 'More than the server takes never holds.');
		}

		$response = $this->write('PATCH', '/settings', ['set' => ['media.uploads' => ['maxSize' => 1, 'path' => 'uploads/', 'kinds' => ['file' => ['enabled' => false], 'image' => []]]]]);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertFalse(self::json($response)['refresh'] ?? null, 'Nothing to compile.');
		$this->assertSame(['media' => ['uploads' => ['enabled' => true, 'maxSize' => 1, 'path' => 'uploads', 'kinds' => ['file' => ['enabled' => false, 'maxSize' => null, 'path' => null]]]]], $this->savedSettings());

		// The settings are read at boot; the session carries over.
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$item = $this->setting(self::json($this->send('GET', '/settings/media')), 'uploads', 'uploads');

		$this->assertSame([true, false, 1], [$item['saved'] ?? null, $item['default'] ?? null, is_array($item['input'] ?? null) ? $item['input']['maxSize'] ?? null : null]);
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
		$this->assertSame([], $this->savedSettings());
	}

	public function testSavesSettingsOverTheConfig(): void
	{
		$this->writeTemporaryFile('user/data/types/post.json', '{"urls": {"prefix": "posts"}}');
		$this->boot(roles: ['administrator'], environment: ['APP_NAME' => 'Notes']);
		$this->login();

		$response = $this->write('PATCH', '/settings', ['set' => ['app.name' => '  Field Notes ', 'app.timezone' => 'Europe/Brussels', 'content.home' => 'post', 'feed.formats' => ['json', 'rss', 'json'], 'feed.limit' => 20, 'sitemap.disallow' => ['/drafts/', '', '/drafts/']]]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertTrue(self::json($response)['refresh'] ?? null, 'The homepage and time zone need a refresh.');
		$this->assertSame(
			[
				'app'     => ['name' => 'Field Notes', 'timezone' => 'Europe/Brussels'],
				'content' => ['home' => 'post'],
				'feed'    => ['formats' => ['rss', 'json'], 'limit' => 20],
				'sitemap' => ['disallow' => ['/drafts/']]
			],
			$this->savedSettings()
		);

		$this->assertSame(200, $this->write('POST', '/settings/refresh')->getStatusCode());

		$container = $this->scratchApplication(['APP_NAME' => 'Notes'])->container();
		$this->assertSame('Field Notes', $container->make(AppConfig::class)->name, 'A saved setting wins over .env.');
		$this->assertSame('Europe/Brussels', $container->make(AppConfig::class)->timezone);
		$this->assertSame('post', $container->make(ContentConfig::class)->home);
		$this->assertSame(20, $container->make(FeedConfig::class)->limit);

		$answer = self::json($this->write('PATCH', '/settings', ['set' => ['feed.fullContent' => false], 'unset' => ['app.name', 'content.home', 'app.timezone', 'feed.formats', 'feed.limit', 'sitemap.disallow']]));
		$this->assertSame(['feed' => ['fullContent' => false]], $answer['saved'] ?? null, 'Saved as fullContent, since content is a record\'s own (D-673).');
		$this->assertTrue($answer['refresh'] ?? null, 'Unsetting the homepage needs a refresh too.');
		$this->assertSame('Notes', $this->scratchApplication(['APP_NAME' => 'Notes'])->container()->make(AppConfig::class)->name, 'Unset, the config is used again.');

		$this->assertFalse(self::json($this->write('PATCH', '/settings', ['unset' => ['feed.fullContent']]))['refresh'] ?? null);
		$this->assertSame([], $this->savedSettings(), 'Nothing saved removes the file.');
	}

	public function testFieldSetsAddSettings(): void
	{
		$this->writeTemporaryFile('user/data/fields/brand.json', '{"label": "Brand", "description": "How the site presents itself.", "targets": ["settings:general"], "fields": {"tagline": {"required": true}, "accent": {"type": "enum", "options": ["red", "blue"], "default": "blue"}}}');
		$this->boot(roles: ['administrator']);
		$this->login();

		$general = self::json($this->send('GET', '/settings/general'));
		$groups  = is_array($general['groups'] ?? null) ? $general['groups'] : [];

		$this->assertSame(['site', 'dates', 'accounts', 'environment', 'set-brand'], array_column($groups, 'key'), 'A set\'s group after the screen\'s own.');
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
		$this->writeTemporaryFile('user/data/types/topic.json', '{"urls": false}');
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
			['auth.signups' => 'yes'],
			['auth.signupRole' => 'administrator'],
			['auth.signupRole' => 'owner'],
			['auth.signupRole' => 'missing'],
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
		$this->assertSame([], $this->savedSettings(), 'A refusal writes nothing.');
	}

	public function testCompilingLeavesTheSettingsOut(): void
	{
		$this->writeTemporaryFile('user/data/types/post.json', '{"urls": {"prefix": "posts"}}');
		$this->writeSettings('{"app": {"name": "Saved"}}');
		$bootstrap = new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production', 'APP_URL' => 'https://example.test', 'APP_NAME' => 'Configured']);
		$bootstrap->compile();

		$this->assertStringNotContainsString('Saved', $this->file(substr($bootstrap->compiledPath(CompiledCache::Config), strlen($this->temporaryDirectory()) + 1)));
		$this->assertSame('Saved', $bootstrap->createApplication()->container()->make(AppConfig::class)->name, 'A compiled site still reads the settings.');
	}

	public function testRefreshCompilesWhatASaveCleared(): void
	{
		$this->writeTemporaryFile('user/data/types/post.json', '{"urls": {"prefix": "posts"}}');
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
