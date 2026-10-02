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
use Blush\Admin\IconPacksController;
use Blush\Admin\Provenance;

#[CoversClass(IconPacksController::class)]
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
	 * and a broken one.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('user/icons/brands/icons.json', '{"name": "acme/brands", "label": "Brand Logos", "namespace": "brands", "version": "2.0.0", "description": "Logos."}');
		$this->writeTemporaryFile('user/icons/brands/lang/en.json', '{"icons": {"github": {"label": "GitHub"}}}');

		foreach (['github', 'mastodon'] as $icon) {
			$this->writeTemporaryFile("user/icons/brands/{$icon}.svg", self::SVG);
		}

		$this->writeTemporaryFile('user/icons/arrows/icons.json', '{"name": "acme/arrows", "label": "Arrows", "namespace": "arrows", "folder": "svg"}');

		foreach (range(1, 14) as $index) {
			$this->writeTemporaryFile(sprintf('user/icons/arrows/svg/arrow-%02d.svg', $index), self::SVG);
		}

		$this->writeTemporaryFile('user/icons/broken/icons.json', '{"name": "broken"}');

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
			'source'      => 'local',
			'path'        => 'user/icons/brands',
			'count'       => 2,
			'icons'       => ['brands/github', 'brands/mastodon']
		], $packs[1] ?? null);

		$arrows = $packs[0] ?? null;
		$this->assertIsArray($arrows);
		$this->assertSame(14, $arrows['count'] ?? null);
		$this->assertCount(12, is_array($arrows['icons'] ?? null) ? $arrows['icons'] : [], 'Only the first of them are listed.');
		$this->assertSame(['user/icons/broken'], array_column(is_array($answer['invalid'] ?? null) ? $answer['invalid'] : [], 'where'));

		$this->assertSame(2, self::json($this->send('GET', '/counts'))['iconPacks'] ?? null);
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

	public function testNeedsSiteSettings(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/icon-packs')->getStatusCode());
	}
}
