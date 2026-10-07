<?php

/**
 * Admin Icon Packs screen's API tests.
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
use Blush\Admin\IconPackEditController;
use Blush\Admin\IconPacksController;
use Blush\Admin\Provenance;

#[CoversClass(IconPacksController::class)]
#[CoversClass(IconPackEditController::class)]
#[CoversClass(Provenance::class)]
final class AdminIconPacksTest extends TestCase
{
	use BootsAdmin;

	private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h22"/></svg>';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site with two packs, one with more icons than the screen lists,
	 * and a broken one; config turns both on unless told not to.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], bool $enabled = true): void
	{
		if ($enabled) {
			$this->writeTemporaryFile('config/icons.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Icon\\IconConfig(enabled: ['acme/brands', 'acme/arrows']);\n");
		}

		$this->writeTemporaryFile('extensions/acme/brands/icons.json', '{"name": "acme/brands", "label": "Brand Logos", "namespace": "brands", "version": "2.0.0", "description": "Logos."}');
		$this->writeTemporaryFile('extensions/acme/brands/composer.json', '{"authors": [{"name": "Acme", "role": "Drawing"}], "license": "CC-BY-4.0", "homepage": "https://acme.test", "support": {"email": "help@acme.test", "issues": "https://acme.test/issues", "irc": "irc://irc.libera.chat/acme", "forum": "not a url"}, "funding": [{"type": "github", "url": "https://github.com/sponsors/acme"}, {"url": "ftp://nope"}]}');
		$this->writeTemporaryFile('extensions/acme/brands/lang/en.json', '{"icons": {"github": {"label": "GitHub"}}}');

		foreach (['github', 'mastodon'] as $icon) {
			$this->writeTemporaryFile("extensions/acme/brands/{$icon}.svg", self::SVG);
		}

		$this->writeTemporaryFile('extensions/acme/arrows/icons.json', '{"name": "acme/arrows", "label": "Arrows", "namespace": "arrows", "folder": "svg"}');

		foreach (range(1, 14) as $index) {
			$this->writeTemporaryFile(sprintf('extensions/acme/arrows/svg/arrow-%02d.svg', $index), self::SVG);
		}

		$this->writeTemporaryFile('extensions/acme/broken/icons.json', '{"name": "broken"}');

		$this->boot(roles: $roles);
		$this->login();
	}

	public function testListsPacksByLabel(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/icon-packs'));
		$packs  = is_array($answer['packs'] ?? null) ? $answer['packs'] : [];

		$this->assertSame(['acme/arrows', 'acme/brands'], array_column($packs, 'name'));
		$this->assertSame([
			'name'        => 'acme/brands',
			'label'       => 'Brand Logos',
			'namespace'   => 'brands',
			'version'     => '2.0.0',
			'description' => 'Logos.',
			'authors'     => [['name' => 'Acme', 'role' => 'Drawing']],
			'license'     => 'CC-BY-4.0',
			'licenses'    => [['text' => 'CC-BY-4.0', 'url' => 'https://spdx.org/licenses/CC-BY-4.0.html', 'operator' => false]],
			'links'       => [['kind' => 'homepage', 'url' => 'https://acme.test'], ['kind' => 'issues', 'url' => 'https://acme.test/issues'], ['kind' => 'irc', 'url' => 'irc://irc.libera.chat/acme'], ['kind' => 'email', 'url' => 'mailto:help@acme.test']],
			'funding'     => [['type' => 'github', 'url' => 'https://github.com/sponsors/acme']],
			'keywords'    => [],
			'source'      => 'local',
			'path'        => 'extensions/acme/brands',
			'folder'      => 'extensions/acme/brands',
			'enabled'      => true,
			'running'      => true,
			'requirements' => [],
			'conflicts'    => [],
			'replaces'     => [],
			'provides'     => [],
			'blocked'      => null,
			'requiredBy'   => [],
			'abandoned'    => false,
			'replacement'  => null,
			'suggests'     => [],
			'conflictedBy' => [],
			'replacedBy'   => [],
			'providedBy'   => [],
			'stops'        => [],
			'deletable'   => true,
			'backup'      => null,
			'count'       => 2,
			'icons'       => [['name' => 'brands/github', 'svg' => self::SVG], ['name' => 'brands/mastodon', 'svg' => self::SVG]]
		], $packs[1] ?? null);

		$arrows = $packs[0] ?? null;
		$this->assertIsArray($arrows);
		$this->assertSame(14, $arrows['count'] ?? null);
		$this->assertCount(12, is_array($arrows['icons'] ?? null) ? $arrows['icons'] : [], 'Only the first of them are sent.');
		$this->assertSame([['where' => 'extensions/acme/broken', 'reason' => 'The icon pack in ' . $this->temporaryDirectory() . '/extensions/acme/broken needs a "name": vendor/name, such as "acme/brands".', 'deletable' => true]], $answer['invalid'] ?? null);
		$this->assertFalse($answer['saved'] ?? null);

		$core = $answer['core'] ?? null;
		$this->assertIsArray($core);
		$this->assertSame('Core', $core['label'] ?? null);
		$this->assertGreaterThan(12, $core['count'] ?? 0);
		$icons = is_array($core['icons'] ?? null) ? $core['icons'] : [];
		$first = is_array($icons[0] ?? null) ? $icons[0] : [];
		$this->assertCount(12, $icons);
		$this->assertStringNotContainsString('/', is_string($first['name'] ?? null) ? $first['name'] : '/', 'Core icons go by their names alone.');

		$this->assertSame(4, self::json($this->send('GET', '/counts'))['iconPacks'] ?? null, 'Two packs, the broken one, and the core set.');
	}

	public function testShowsEveryIconOfAPack(): void
	{
		$this->site();

		$pack = self::json($this->send('GET', '/icon-packs/acme/arrows'))['pack'] ?? null;
		$this->assertIsArray($pack);
		$this->assertCount(14, is_array($pack['icons'] ?? null) ? $pack['icons'] : []);

		$core = self::json($this->send('GET', '/icon-packs/core'))['core'] ?? null;
		$this->assertIsArray($core);
		$this->assertSame($core['count'] ?? null, count(is_array($core['icons'] ?? null) ? $core['icons'] : []));

		$this->assertSame(404, $this->send('GET', '/icon-packs/acme/missing')->getStatusCode());
	}

	public function testTurnsPacksOnAndOff(): void
	{
		$this->site();

		$response = $this->write('PUT', '/icon-packs/acme/brands', ['enabled' => false]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['icons' => ['enabled' => ['acme/arrows']]], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/settings.json'), true));

		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$answer = self::json($this->send('GET', '/icon-packs'));
		$this->assertTrue($answer['saved'] ?? null);
		$this->assertSame([true, false], array_column(is_array($answer['packs'] ?? null) ? $answer['packs'] : [], 'enabled'));

		$icons = self::json($this->send('GET', '/icons'))['icons'] ?? null;
		$this->assertIsArray($icons);
		$this->assertNull(array_find($icons, static fn (mixed $icon): bool => is_array($icon) && ($icon['name'] ?? null) === 'brands/github'), 'A pack that\'s off adds no icons.');

		$this->assertSame(200, $this->write('PUT', '/icon-packs/acme/brands', ['enabled' => true])->getStatusCode());
		$this->assertSame(['icons' => ['enabled' => ['acme/arrows', 'acme/brands']]], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/settings.json'), true));
		$this->assertSame(404, $this->write('PUT', '/icon-packs/acme/missing', ['enabled' => true])->getStatusCode());
	}

	public function testEnforcesAPacksRequirements(): void
	{
		$this->writeTemporaryFile('extensions/acme/future/icons.json', '{"name": "acme/future", "label": "Future", "require": {"blush-dev/framework": "^9.0"}}');
		$this->site();

		$response = $this->write('PUT', '/icon-packs/acme/future', ['enabled' => true]);
		$this->assertSame(422, $response->getStatusCode());
		$this->assertStringContainsString('Future can\'t be turned on. Needs Blush ^9.0 (this site runs', (string) $response->getBody());

		$future = array_find(is_array($packs = self::json($this->send('GET', '/icon-packs'))['packs'] ?? null) ? $packs : [], static fn (mixed $pack): bool => is_array($pack) && ($pack['name'] ?? null) === 'acme/future');
		$this->assertIsArray($future);
		$this->assertFalse($future['running'] ?? null);
		$this->assertStringStartsWith('Needs Blush ^9.0', is_string($future['blocked'] ?? null) ? $future['blocked'] : '');
	}

	public function testSaysWhatStopsWithAPack(): void
	{
		$this->writeTemporaryFile('extensions/acme/logo-block/plugin.json', '{"name": "acme/logo-block", "label": "Logo Block", "require": {"acme/brands": "^2.0"}}');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/logo-block']);\n");
		$this->site();

		$brands = array_find(is_array($packs = self::json($this->send('GET', '/icon-packs'))['packs'] ?? null) ? $packs : [], static fn (mixed $pack): bool => is_array($pack) && ($pack['name'] ?? null) === 'acme/brands');
		$this->assertIsArray($brands);
		$this->assertSame([['name' => 'acme/logo-block', 'label' => 'Logo Block', 'kind' => 'plugin']], $brands['requiredBy'] ?? null);

		$answer = self::json($this->write('PUT', '/icon-packs/acme/brands', ['enabled' => false]));
		$this->assertSame(['enabled' => false, 'started' => [], 'stopped' => ['Logo Block'], 'refresh' => true], $answer);
	}

	public function testNothingLocalIsOnUntilNamed(): void
	{
		$this->site(enabled: false);

		$packs = self::json($this->send('GET', '/icon-packs'))['packs'] ?? null;

		$this->assertSame([false, false], array_column(is_array($packs) ? $packs : [], 'enabled'), 'D-390.');
	}

	public function testDeletesPackFolders(): void
	{
		$this->site();

		$response = $this->write('DELETE', '/icon-packs/acme/brands');
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['deleted' => 'extensions/acme/brands'], self::json($response));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/extensions/acme/brands');

		$this->assertSame(200, $this->write('DELETE', '/icon-packs/acme/broken')->getStatusCode(), 'A broken pack can be deleted.');
		$this->assertSame(404, $this->write('DELETE', '/icon-packs/acme/missing')->getStatusCode());
	}

	public function testThePickerNamesAPacksIconsByIt(): void
	{
		$this->site();

		$icons  = self::json($this->send('GET', '/icons'))['icons'] ?? null;
		$this->assertIsArray($icons);

		$github = array_find($icons, static fn (mixed $icon): bool => is_array($icon) && ($icon['name'] ?? null) === 'brands/github');

		$this->assertIsArray($github);
		$this->assertSame('GitHub', $github['label'] ?? null);
		$this->assertSame(['kind' => 'icon-pack', 'label' => 'Brand Logos'], $github['source'] ?? null);
	}

	public function testNeedsItsCapabilities(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/icon-packs')->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/icon-packs/core')->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/icon-packs/acme/brands', ['enabled' => false])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/icon-packs/acme/brands')->getStatusCode());
	}

	public function testSeeingIsItsOwn(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('looker', 'Looker', ['extensions.icon-packs.view'])]);\n");
		$this->site(['looker']);

		$this->assertSame(200, $this->send('GET', '/icon-packs')->getStatusCode());
		$this->assertSame(200, $this->send('GET', '/icon-packs/core')->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/icon-packs/acme/brands', ['enabled' => false])->getStatusCode(), 'Turning on and off is its own (D-389).');
		$this->assertSame(403, $this->write('DELETE', '/icon-packs/acme/brands')->getStatusCode());
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}
}
