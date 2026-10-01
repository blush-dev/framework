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

	/**
	 * Writes two author pages, one a draft, and a post crediting them and
	 * an author with no page.
	 */
	private function authors(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");
		$this->writeTemporaryFile('user/content/authors/jane.md', "---\ntitle: Jane Author\n---\nWrites.\n");
		$this->writeTemporaryFile('user/content/authors/gwen.md', "---\ntitle: Gwen Guest\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/_posts/one.md', "---\ntitle: One\nauthors: [jane, gwen, ghost]\n---\n");
	}

	public function testListsAccountsAndAuthorsAsOne(): void
	{
		$this->authors();
		$this->site();

		$people = self::json($this->send('GET', '/people'))['people'] ?? null;

		$this->assertIsArray($people);
		$this->assertSame(['ghost', 'Gwen Guest', 'Jane Author', 'Sam Smith'], array_column($people, 'name'), 'By name (D-329).');

		[$ghost, $gwen, $jane, $sam] = array_map(static fn (mixed $person): array => is_array($person) ? $person : [], $people);

		$this->assertSame([null, true, 1], [$ghost['entry'], $ghost['virtual'], $ghost['uses']], 'Credited without a page.');
		$this->assertSame([null, 'draft', false], [$gwen['account'], is_array($gwen['entry']) ? $gwen['entry']['status'] : null, $gwen['virtual']], 'A guest: a page, no account.');
		$this->assertSame(['jane', 'Jane Author', 'author/jane'], [is_array($jane['account']) ? $jane['account']['username'] : null, is_array($jane['account']) ? $jane['account']['displayName'] : null, is_array($jane['entry']) ? $jane['entry']['handle'] : null], 'One name: the author page\'s title.');
		$this->assertSame([null, null, 0], [$sam['author'], $sam['entry'], $sam['uses']], 'An account with no author.');
	}

	public function testAuthorsSeeOnlyThemselves(): void
	{
		$this->authors();
		$this->site(['author']);

		$people = self::json($this->send('GET', '/people'))['people'] ?? null;

		$this->assertIsArray($people);
		$this->assertSame(['Jane Author'], array_column($people, 'name'), 'Their own page, and no accounts or pageless authors.');
		$this->assertIsArray($people[0] ?? null);
		$this->assertNull($people[0]['account'] ?? null);
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
		$this->assertSame(['username', 'name', 'displayName', 'roles', 'author', 'authorPage', 'created', 'lastLogin', 'status', 'link', 'manages'], array_keys($sam), 'No password hash or preferences.');
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
