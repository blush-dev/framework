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
use Blush\Admin\SettingsController;

#[CoversClass(SettingsController::class)]
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

		$answer = self::json($this->send('GET', '/settings'));

		$this->assertSame(['general', 'dates', 'content', 'addresses', 'feeds', 'search', 'caching', 'publishing'], array_column(is_array($answer['groups'] ?? null) ? $answer['groups'] : [], 'key'));

		$name = $this->setting($answer, 'general', 'name');
		$this->assertSame('Notes', $name['value'] ?? null);
		$this->assertFalse($name['default'] ?? null);
		$this->assertSame('America/Chicago', $this->setting($answer, 'dates', 'timezone')['value'] ?? null);
		$this->assertSame('The latest posts', $this->setting($answer, 'content', 'home')['value'] ?? null);
		$this->assertFalse($this->setting($answer, 'addresses', 'trailingSlash')['value'] ?? null);
		$this->assertTrue($this->setting($answer, 'addresses', 'trailingSlash')['default'] ?? null);
		$this->assertFalse($this->setting($answer, 'feeds', 'content')['value'] ?? null);
		$this->assertFalse($this->setting($answer, 'feeds', 'content')['default'] ?? null);
		$this->assertSame(['RSS', 'Atom', 'JSON Feed'], $this->setting($answer, 'feeds', 'formats')['value'] ?? null);
		$this->assertFalse($this->setting($answer, 'caching', 'enabled')['value'] ?? null, 'Caching is off in development.');
		$this->assertTrue($this->setting($answer, 'publishing', 'webhook')['value'] ?? null);
		$this->assertStringNotContainsString(str_repeat('p', 40), json_encode($answer) ?: '', 'Secrets are never sent.');
	}

	public function testWarnsAboutDetailedErrorsOnALiveSite(): void
	{
		$this->boot(roles: ['administrator'], environment: ['APP_ENV' => 'production', 'APP_DEBUG' => 'true']);
		$this->login();

		$this->assertNotNull($this->setting(self::json($this->send('GET', '/settings')), 'general', 'debug')['warning'] ?? null);
	}

	public function testNeedsSiteSettings(): void
	{
		$this->boot(roles: ['editor']);
		$this->login();

		$this->assertSame(403, $this->send('GET', '/settings')->getStatusCode());
	}
}
