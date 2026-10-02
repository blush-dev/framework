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
		$this->writeTemporaryFile('user/themes/notebook/theme.json', '{"name": "acme/notebook", "label": "Notebook", "namespace": "notebook", "version": "1.2.0", "description": "Lined paper."}');
		$this->writeTemporaryFile('user/themes/pocket/theme.json', '{"name": "acme/pocket", "label": "Pocket", "namespace": "pocket", "parent": "acme/notebook"}');
		$this->writeTemporaryFile('user/themes/plate/theme.json', '{"name": "acme/plate", "label": "Plate", "namespace": "plate"}');
		$this->writeTemporaryFile('user/themes/broken/theme.json', '{"name": 5}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/pocket');\n");
		$this->boot(roles: $roles);
		$this->login();
	}

	public function testDescribesThemesAndTheActiveChain(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/appearance'));

		$this->assertSame('acme/pocket', $answer['active'] ?? null);
		$this->assertSame(['acme/pocket', 'acme/notebook', 'blush/default'], $answer['chain'] ?? null);
		$this->assertTrue($answer['config'] ?? null);
		$this->assertTrue($answer['preview'] ?? null, 'Previews work in development.');

		$themes = is_array($answer['themes'] ?? null) ? $answer['themes'] : [];
		$first  = $themes[0] ?? null;
		$this->assertIsArray($first);
		$this->assertSame('acme/pocket', $first['name'] ?? null, 'The active theme comes first.');
		$this->assertTrue($first['active'] ?? null);
		$this->assertSame('acme/notebook', $first['parent'] ?? null);
		$this->assertSame(['acme/pocket', 'blush/default', 'acme/notebook', 'acme/plate'], array_column($themes, 'name'), 'Then by label.');

		$notebook = array_find($themes, static fn (mixed $theme): bool => is_array($theme) && ($theme['name'] ?? null) === 'acme/notebook');
		$this->assertSame(['name' => 'acme/notebook', 'label' => 'Notebook', 'namespace' => 'notebook', 'version' => '1.2.0', 'description' => 'Lined paper.', 'parent' => null, 'source' => 'local', 'active' => false], $notebook);
		$this->assertSame(['user/themes/broken'], array_column(is_array($answer['invalid'] ?? null) ? $answer['invalid'] : [], 'where'));
	}

	public function testNeedsSiteSettings(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/appearance')->getStatusCode());
	}
}
