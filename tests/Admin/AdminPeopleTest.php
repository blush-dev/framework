<?php

/**
 * Admin people screens' API tests.
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
use Blush\Admin\PeopleController;
use Blush\Auth\Accounts;

#[CoversClass(PeopleController::class)]
final class AdminPeopleTest extends TestCase
{
	use BootsAdmin;

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('reviewer', 'Reviewer', ['content.edit.others'])]);\n");
		$this->boot(roles: $roles);
		$this->app->container()->make(Accounts::class)->create('sam', 'another long password', ['author', 'reviewer'], name: 'Sam Smith');
		$this->login();
	}

	public function testListsRolesWithWhoHoldsThem(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/roles'));

		$this->assertSame(['*'], $this->role($answer, 'administrator')['capabilities'] ?? null);
		$this->assertSame([['username' => 'jane', 'displayName' => 'jane']], $this->role($answer, 'administrator')['accounts'] ?? null);
		$this->assertSame([['username' => 'sam', 'displayName' => 'Sam Smith']], $this->role($answer, 'author')['accounts'] ?? null);
		$this->assertTrue($this->role($answer, 'author')['builtIn'] ?? null);
		$this->assertSame(['label' => 'Reviewer', 'description' => '', 'capabilities' => ['content.edit.others'], 'builtIn' => false, 'origin' => 'config', 'accounts' => [['username' => 'sam', 'displayName' => 'Sam Smith']], 'grantable' => true, 'editable' => false], array_diff_key($this->role($answer, 'reviewer'), ['name' => true]));
		$this->assertFalse($this->role($answer, 'administrator')['editable'] ?? null, 'The administrator always has everything.');
		$this->assertTrue($this->role($answer, 'editor')['editable'] ?? null);
		$this->assertNotSame('', $this->role($answer, 'editor')['description'] ?? '');
		$this->assertContains('content.edit', array_column(is_array($answer['capabilities'] ?? null) ? $answer['capabilities'] : [], 'name'));
	}

	/**
	 * Returns a role from `GET roles`, by name.
	 *
	 * @param  array<mixed> $answer
	 * @return array<mixed>
	 */
	private function role(array $answer, string $name): array
	{
		$role = array_find(is_array($answer['roles'] ?? null) ? $answer['roles'] : [], static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $name);
		$this->assertIsArray($role, $name);

		return $role;
	}

	public function testListsAccountsWithoutSecrets(): void
	{
		$this->site();

		$accounts = self::json($this->send('GET', '/accounts'))['accounts'] ?? null;

		$this->assertIsArray($accounts);
		$this->assertSame(['jane', 'sam'], array_column($accounts, 'username'));
		$sam = $accounts[1] ?? null;
		$this->assertIsArray($sam);
		$this->assertSame(['author', 'reviewer'], $sam['roles'] ?? null);
		$this->assertSame('Sam Smith', $sam['name'] ?? null);
		$this->assertSame(['username', 'name', 'displayName', 'roles', 'author', 'created', 'lastLogin', 'status', 'link', 'manages'], array_keys($sam), 'No password hash or preferences.');
		$this->assertSame('active', $sam['status'] ?? null);
		$this->assertTrue($sam['manages'] ?? null);
		$this->assertFalse(is_array($accounts[0] ?? null) ? $accounts[0]['manages'] ?? null : null, 'Not your own account.');
	}

	public function testNeedsAccountsManage(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/roles')->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/accounts')->getStatusCode());
	}
}
