<?php

/**
 * Admin editor pickers' API tests.
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
use Blush\Admin\IconsController;
use Blush\Admin\MediaListController;

#[CoversClass(IconsController::class)]
#[CoversClass(MediaListController::class)]
final class AdminPickersTest extends TestCase
{
	use BootsAdmin;

	/**
	 * A 1×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$png = (string) base64_decode(self::PNG, true);

		touch($this->writeTemporaryFile('user/media/2020/old.png', $png), 1_600_000_000);
		$this->writeTemporaryFile('user/media/2026/new-photo.png', $png);
		$this->writeTemporaryFile('user/media/notes.txt', 'not media');
		$this->writeTemporaryFile('user/media/.hidden.png', $png);
		$this->writeTemporaryFile('user/content/trip/index.md', "---\ntitle: Trip\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/trip/beach.png', $png);
		$this->writeTemporaryFile('user/content/other.md', "---\ntitle: Other\nauthors: sam\n---\n");
		$this->writeTemporaryFile('resources/icons/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>');

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * @return array<mixed>
	 */
	private function media(string $query = ''): array
	{
		return self::json($this->send('GET', "/media{$query}"));
	}

	public function testListsTheLibraryNewestFirst(): void
	{
		$this->site();

		$list = $this->media();

		$this->assertSame(2, $list['total'] ?? null, 'Only allowed, visible media files.');
		$this->assertSame(['new-photo.png', 'old.png'], array_column(is_array($list['files'] ?? null) ? $list['files'] : [], 'name'));

		$first = is_array($list['files'] ?? null) ? $list['files'][0] ?? null : null;

		$this->assertIsArray($first);
		$this->assertSame(['/media/2026/new-photo.png', '2026', 'image', 1, 1], [$first['reference'], $first['folder'], $first['kind'], $first['width'], $first['height']]);
		$this->assertArrayHasKey('beside', $list);
		$this->assertNull($list['beside'], 'Only a bundle has files beside it.');
	}

	public function testSearchesPagesAndFilters(): void
	{
		$this->site();

		$this->assertSame(['old.png'], array_column((array) ($this->media('?search=OLD')['files'] ?? []), 'name'));
		$this->assertSame(['old.png'], array_column((array) ($this->media('?per=1&page=2')['files'] ?? []), 'name'));
		$this->assertSame(0, $this->media('?kind=video')['total'] ?? null);
		$this->assertSame(0, $this->media('?kind=file')['total'] ?? null, 'A file is any kind but images, video, and audio.');

		foreach (['?kind=text', '?page=0', '?per=101', '?search[]=x'] as $query) {
			$this->assertSame(400, $this->send('GET', "/media{$query}")->getStatusCode(), $query);
		}
	}

	public function testListsABundlesOwnFiles(): void
	{
		$this->site();

		$beside = $this->media('?entry=trip/index.md')['beside'] ?? null;

		$this->assertIsArray($beside);
		$this->assertSame(['beach.png'], array_column($beside, 'reference'), 'A bundle file is referred to by name.');
		$this->assertSame(404, $this->send('GET', '/media?entry=missing.md')->getStatusCode());
	}

	public function testChecksWhoMayUseMedia(): void
	{
		$this->site(['author']);

		$this->assertSame(404, $this->send('GET', '/media?entry=other.md')->getStatusCode(), 'An author may not look beside someone else\'s entry.');
		$this->assertSame(200, $this->send('GET', '/media?entry=trip/index.md')->getStatusCode());
	}

	public function testDescribesOneLibraryFile(): void
	{
		$this->site();

		$file = self::json($this->send('GET', '/media/2026/new-photo.png'));

		$this->assertSame(['/media/2026/new-photo.png', 'new-photo.png', 'image/png'], [$file['reference'] ?? null, $file['name'] ?? null, $file['mime'] ?? null]);

		foreach (['notes.txt', '.hidden.png', '2026/missing.png', '../user/content/trip/beach.png'] as $path) {
			$this->assertSame(404, $this->send('GET', "/media/{$path}")->getStatusCode(), $path);
		}
	}

	public function testDescribesTheIcons(): void
	{
		$this->site();

		$icons = self::json($this->send('GET', '/icons'))['icons'] ?? null;

		$this->assertIsArray($icons);

		$house = array_find($icons, static fn (mixed $icon): bool => is_array($icon) && ($icon['name'] ?? null) === 'house');

		$this->assertIsArray($house, 'Core icons use their short names.');
		$this->assertSame('Home', $house['label'] ?? null, 'Labels come from the catalog.');
		$this->assertContains('home', is_array($house['keywords'] ?? null) ? $house['keywords'] : []);
		$this->assertStringContainsString('<svg', is_string($house['svg'] ?? null) ? $house['svg'] : '');
		$this->assertSame(['places', null], [$house['category'] ?? null, $house['source'] ?? null], 'A core icon has a category and no source.');

		$uncategorized = array_filter($icons, static fn (mixed $icon): bool => is_array($icon) && is_string($icon['name'] ?? null) && ! str_contains($icon['name'], '/') && ($icon['category'] ?? null) === null);

		$this->assertSame([], array_column($uncategorized, 'name'), 'Every core icon has a category in categories.json.');

		$logo = array_find($icons, static fn (mixed $icon): bool => is_array($icon) && ($icon['name'] ?? null) === 'app/logo');

		$this->assertIsArray($logo, 'The site\'s icons are named in full.');
		$this->assertSame([null, ['kind' => 'site', 'label' => 'This site']], [$logo['category'] ?? null, $logo['source'] ?? null], 'The rest are grouped by where they come from.');
	}
}
