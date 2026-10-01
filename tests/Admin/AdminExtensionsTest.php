<?php

/**
 * Admin Extensions screen's API tests.
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
use Blush\Admin\ExtensionsController;

#[CoversClass(ExtensionsController::class)]
final class AdminExtensionsTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site with an extension that adds one of everything and one that's
	 * turned off.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], string $content = ''): void
	{
		$this->writeTemporaryFile('user/extensions/recipes/extension.json', json_encode([
			'name'        => 'fixture/recipes',
			'version'     => '1.4.0',
			'description' => 'Recipes and cuisines.',
			'provider'    => 'Blush\\Tests\\Fixtures\\Admin\\Recipes\\RecipesProvider',
			'requires'    => ['blush' => '^2.0']
		]) ?: '');
		$this->writeTemporaryFile('user/extensions/off/extension.json', '{"name": "off", "provider": "Off\\\\OffProvider"}');
		$this->writeTemporaryFile('config/extensions.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Extension\\ExtensionConfig(disabled: ['off']);\n");

		if ($content !== '') {
			$this->writeTemporaryFile('config/content.php', $content);
		}

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Returns an extension from `GET extensions`, by name.
	 *
	 * @param  array<mixed> $answer
	 * @return array<mixed>
	 */
	private function extension(array $answer, string $name): array
	{
		$extension = array_find(is_array($answer['extensions'] ?? null) ? $answer['extensions'] : [], static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $name);
		$this->assertIsArray($extension, $name);

		return $extension;
	}

	public function testListsExtensionsAndWhatEachAdds(): void
	{
		$this->site();

		$answer  = self::json($this->send('GET', '/extensions'));
		$recipes = $this->extension($answer, 'fixture/recipes');

		$this->assertTrue($answer['config'] ?? null);
		$this->assertSame(['fixture/recipes', 'off'], array_column(is_array($answer['extensions'] ?? null) ? $answer['extensions'] : [], 'name'));
		$this->assertSame('1.4.0', $recipes['version'] ?? null);
		$this->assertSame('local', $recipes['source'] ?? null);
		$this->assertSame('user/extensions/recipes', $recipes['path'] ?? null);
		$this->assertSame(['blush' => '^2.0'], $recipes['requires'] ?? null);
		$this->assertTrue($recipes['enabled'] ?? null);
		$this->assertSame([
			'types'      => [
				['name' => 'recipe', 'label' => 'Recipes', 'overridden' => false],
				['name' => 'cuisine', 'label' => 'Cuisines', 'overridden' => false]
			],
			'components' => ['fixture/recipe-card'],
			'icons'      => ['fixture'],
			'actions'    => ['import-recipes'],
			'commands'   => ['recipes:import']
		], $recipes['adds'] ?? null);

		$off = $this->extension($answer, 'off');
		$this->assertFalse($off['enabled'] ?? null);
		$this->assertSame(['types' => [], 'components' => [], 'icons' => [], 'actions' => [], 'commands' => []], $off['adds'] ?? null, 'An extension that\'s off adds nothing.');
	}

	public function testMarksTypesTheSiteRedefines(): void
	{
		$this->site(content: "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['recipe' => ['path' => 'dishes']]]);\n");

		$adds = $this->extension(self::json($this->send('GET', '/extensions')), 'fixture/recipes')['adds'] ?? null;
		$this->assertIsArray($adds);
		$types = $adds['types'] ?? null;
		$this->assertIsArray($types);
		$this->assertSame([true, false], array_column($types, 'overridden'));
	}

	public function testNeedsSiteSettings(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/extensions')->getStatusCode());
	}
}
