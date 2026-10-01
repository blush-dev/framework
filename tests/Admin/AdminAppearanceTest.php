<?php

/**
 * Admin Appearance screen's API tests.
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
use Blush\Admin\AppearanceController;

#[CoversClass(AppearanceController::class)]
final class AdminAppearanceTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site on a child theme of `notebook`, with a third theme installed
	 * and one broken.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('user/themes/notebook/theme.json', '{"name": "Notebook", "version": "1.2.0", "description": "Lined paper."}');
		$this->writeTemporaryFile('user/themes/pocket/theme.json', '{"name": "Pocket", "parent": "notebook"}');
		$this->writeTemporaryFile('user/themes/plate/theme.json', '{"name": "Plate"}');
		$this->writeTemporaryFile('user/themes/broken/theme.json', '{"name": 5}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'pocket');\n");
		$this->boot(roles: $roles);
		$this->login();
	}

	public function testDescribesThemesAndTheActiveChain(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/appearance'));

		$this->assertSame('pocket', $answer['active'] ?? null);
		$this->assertSame(['pocket', 'notebook', 'default'], $answer['chain'] ?? null);
		$this->assertTrue($answer['config'] ?? null);
		$this->assertTrue($answer['preview'] ?? null, 'Previews work in development.');

		$themes = is_array($answer['themes'] ?? null) ? $answer['themes'] : [];
		$first  = $themes[0] ?? null;
		$this->assertIsArray($first);
		$this->assertSame('pocket', $first['slug'] ?? null, 'The active theme comes first.');
		$this->assertTrue($first['active'] ?? null);
		$this->assertSame('notebook', $first['parent'] ?? null);

		$notebook = array_find($themes, static fn (mixed $theme): bool => is_array($theme) && ($theme['slug'] ?? null) === 'notebook');
		$this->assertSame(['slug' => 'notebook', 'name' => 'Notebook', 'version' => '1.2.0', 'description' => 'Lined paper.', 'parent' => null, 'source' => 'local', 'active' => false], $notebook);
		$this->assertSame(['broken'], array_column(is_array($answer['invalid'] ?? null) ? $answer['invalid'] : [], 'slug'));
	}

	public function testNeedsSiteSettings(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/appearance')->getStatusCode());
	}
}
