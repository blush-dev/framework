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
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\PeopleController;
use Blush\Admin\CountsController;
use Blush\Admin\ProfilesController;
use Blush\Auth\Accounts;

#[CoversClass(PeopleController::class)]
#[CoversClass(ProfilesController::class)]
#[CoversClass(CountsController::class)]
final class AdminPeopleTest extends TestCase
{
	use BootsAdmin;

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('reviewer', 'Reviewer', ['content.*.edit.others'])]);\n");
		$this->boot(roles: $roles);
		$this->app->container()->make(Accounts::class)->create('sam', 'another long password', ['author', 'reviewer'], name: 'Sam Smith', email: 'sam@example.test');
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
		$this->assertSame(['label' => 'Reviewer', 'description' => '', 'capabilities' => ['content.*.edit.others'], 'builtIn' => false, 'origin' => 'config', 'accounts' => [['username' => 'sam', 'displayName' => 'Sam Smith']], 'grantable' => true, 'editable' => false], array_diff_key($this->role($answer, 'reviewer'), ['name' => true]));
		$this->assertFalse($this->role($answer, 'administrator')['editable'] ?? null, 'The administrator always has everything.');
		$this->assertTrue($this->role($answer, 'editor')['editable'] ?? null);
		$this->assertNotSame('', $this->role($answer, 'editor')['description'] ?? '');
		$capabilities = is_array($answer['capabilities'] ?? null) ? $answer['capabilities'] : [];

		$this->assertContains('content.*.edit', array_column($capabilities, 'name'));
		$this->assertContains(['name' => 'content.page.edit.others', 'label' => 'Pages: Edit anyone\'s', 'group' => 'Pages', 'type' => 'page', 'action' => 'edit.others'], $capabilities, 'Each type has its own (D-359).');
		$this->assertContains(['name' => 'menus.edit', 'label' => 'Edit menus', 'group' => 'Structure'], $capabilities);
		$this->assertContains(['name' => 'page', 'label' => 'Pages', 'kind' => 'pages', 'icon' => null], is_array($answer['types'] ?? null) ? $answer['types'] : []);
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
	 * Writes two profiles, one a draft, and a post crediting them and a
	 * profile with no file.
	 */
	private function profiles(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane Author\nsubtitle: Food editor\n---\nWrites.\n");
		$this->writeTemporaryFile('user/content/profiles/gwen.md', "---\ntitle: Gwen Guest\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/_posts/one.md', "---\ntitle: One\nauthors: [jane, gwen, ghost]\n---\n");
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	public function testCountsTheSectionPanelsLists(): void
	{
		$this->profiles();
		$this->site();

		$counts = self::json($this->send('GET', '/counts'));
		$types  = is_array($counts['types'] ?? null) ? $counts['types'] : [];

		$this->assertSame(2, $types['profile'] ?? null, 'Each type\'s entries, as its list counts them (D-371).');
		$this->assertSame(1, $types['post'] ?? null);
		$this->assertSame(2, $counts['accounts'] ?? null);
		$this->assertIsInt($counts['roles'] ?? null);
		$this->assertIsInt($counts['contentTypes'] ?? null);
		$this->assertArrayHasKey('plugins', $counts);
		$this->assertSame(0, $counts['media'] ?? null, 'The library\'s files (D-372).');
		$this->assertGreaterThanOrEqual(1, $counts['themes'] ?? null, 'The default theme, at least.');
	}

	public function testCountsOnlyWhatTheAccountMaySee(): void
	{
		$this->profiles();
		$this->site(['contributor']);

		$counts = self::json($this->send('GET', '/counts'));

		$this->assertArrayNotHasKey('accounts', $counts);
		$this->assertArrayNotHasKey('contentTypes', $counts);
		$this->assertArrayNotHasKey('themes', $counts);
		$this->assertArrayNotHasKey('media', $counts, 'A contributor can\'t upload.');
		$this->assertSame(0, is_array($counts['types'] ?? null) ? $counts['types']['post'] ?? null : null, 'A contributor edits only their own.');
	}

	public function testAccountsCarryTheirProfiles(): void
	{
		$this->profiles();
		$this->site();

		$accounts = self::json($this->send('GET', '/accounts'))['accounts'] ?? null;

		$this->assertIsArray($accounts);
		[$jane, $sam] = array_map(static fn (mixed $account): array => is_array($account) ? $account : [], $accounts);

		$this->assertSame(['id' => 'profiles/jane.md', 'handle' => 'profile/jane', 'slug' => 'jane', 'title' => 'Jane Author', 'status' => 'published', 'url' => '/profiles/jane', 'uses' => 1], $jane['profile'] ?? null);
		$this->assertSame('Jane Author', $jane['displayName'] ?? null, 'One name: the profile\'s title.');
		$this->assertArrayHasKey('profile', $sam);
		$this->assertNull($sam['profile'], 'Not linked, so no profile (D-353).');
		$this->assertSame(404, $this->send('GET', '/people')->getStatusCode(), 'Accounts and profiles are two lists now.');
	}

	public function testTheProfilesListSaysWhoIsLinked(): void
	{
		$this->profiles();
		$this->site();

		$entries = self::json($this->send('GET', '/entries?type=profile&sort=title'))['entries'] ?? null;

		$this->assertIsArray($entries);
		$this->assertSame(['Gwen Guest', 'Jane Author'], array_column($entries, 'title'));
		$this->assertSame([false, true], array_column($entries, 'linked'));
		$this->assertSame([null, ['username' => 'jane', 'displayName' => 'Jane Author']], array_column($entries, 'account'));
		$this->assertSame([1, 1], array_column($entries, 'uses'), 'Bylines: the published entries crediting each.');

		$linked = self::json($this->send('GET', '/entries?type=profile&account=linked'))['entries'] ?? null;
		$guests = self::json($this->send('GET', '/entries?type=profile&account=guest'))['entries'] ?? null;

		$this->assertSame(['Jane Author'], array_column(is_array($linked) ? $linked : [], 'title'), 'Linked to an account (D-369).');
		$this->assertSame(['Gwen Guest'], array_column(is_array($guests) ? $guests : [], 'title'), 'Guests.');
		$this->assertSame(400, $this->send('GET', '/entries?type=profile&account=other')->getStatusCode());
	}

	public function testDescribesAProfileAndWhereItAppears(): void
	{
		$this->profiles();
		$this->site();

		$answer = self::json($this->send('GET', '/profiles/jane'));

		$this->assertSame(['slug' => 'jane', 'title' => 'Jane Author', 'subtitle' => 'Food editor', 'avatar' => null, 'status' => 'published', 'virtual' => false, 'id' => 'profiles/jane.md', 'handle' => 'profile/jane', 'url' => '/profiles/jane', 'uses' => 1], $answer['profile'] ?? null);
		$this->assertSame([['type' => 'post', 'typeLabel' => 'Posts', 'field' => 'authors', 'label' => 'Authors', 'entries' => 1, 'archive' => '/posts/authors/jane', 'page' => null]], $answer['appears'] ?? null);
		$this->assertTrue($answer['linked'] ?? null);
		$this->assertSame('jane', is_array($answer['account'] ?? null) ? $answer['account']['username'] : null);

		$ghost = self::json($this->send('GET', '/profiles/ghost'));

		$this->assertIsArray($ghost['profile'] ?? null);
		$this->assertSame([true, null, null], [$ghost['profile']['virtual'], $ghost['profile']['status'], $ghost['profile']['id']], 'Credited without a file.');
		$this->assertFalse($ghost['linked'] ?? null);
		$this->assertSame(404, $this->send('GET', '/profiles/nobody')->getStatusCode());
	}

	public function testAuthorsSeeOnlyTheirOwnProfile(): void
	{
		$this->profiles();
		$this->site(['author']);

		$own = self::json($this->send('GET', '/profiles/jane'));

		$this->assertTrue($own['linked'] ?? null);
		$this->assertArrayHasKey('account', $own);
		$this->assertNull($own['account'], 'Which account takes accounts.view.');
		$this->assertSame(403, $this->send('GET', '/profiles/gwen')->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/profiles/ghost')->getStatusCode(), 'A profile with no file is everyone\'s.');
	}

	public function testWritesAndRemovesAnArchivesPage(): void
	{
		$this->profiles();
		$this->site();

		$written = $this->write('POST', '/profiles/jane/pages', ['type' => 'post', 'field' => 'authors']);

		$this->assertSame(201, $written->getStatusCode(), (string) $written->getBody());
		$this->assertSame('_posts/_authors/jane.md', self::json($written)['id'] ?? null);
		$this->assertStringContainsString("title: \"Jane Author\"\nstatus: draft\n", (string) file_get_contents($this->temporaryDirectory() . '/user/content/_posts/_authors/jane.md'));
		$this->assertSame(409, $this->write('POST', '/profiles/jane/pages', ['type' => 'post', 'field' => 'authors'])->getStatusCode(), 'Once.');
		$this->assertSame(422, $this->write('POST', '/profiles/jane/pages', ['type' => 'post', 'field' => 'cooks'])->getStatusCode());

		$appears = $this->firstAppearance();

		$this->assertSame(['id' => '_posts/_authors/jane.md', 'handle' => 'post/_authors/jane', 'title' => 'Jane Author', 'status' => 'draft'], $appears['page'] ?? null);
		$this->assertNotContains('Jane Author', array_column(is_array($list = self::json($this->send('GET', '/entries?type=post'))['entries'] ?? null) ? $list : [], 'title'), 'It isn\'t one of the posts.');

		$editor = self::json($this->send('GET', '/entries/_posts/_authors/jane.md'));

		$this->assertSame(['field' => 'authors', 'label' => 'Authors', 'profile' => 'jane', 'profileTitle' => 'Jane Author'], $editor['peoplePage'] ?? null);
		$can = $editor['can'] ?? null;

		$this->assertIsArray($can);
		$this->assertSame([false, false, false], [$can['delete'] ?? null, $can['duplicate'] ?? null, $can['rename'] ?? null]);

		$this->assertSame(200, $this->write('DELETE', '/profiles/jane/pages/post/authors')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/_authors/jane.md');
		$again = $this->firstAppearance();

		$this->assertArrayHasKey('page', $again);
		$this->assertNull($again['page'], 'The archive uses the bio again.');
		$this->assertSame(404, $this->write('DELETE', '/profiles/jane/pages/post/authors')->getStatusCode());
	}

	public function testALinkedProfileKeepsItsSlug(): void
	{
		$this->profiles();
		$this->site();

		$jane = self::json($this->send('GET', '/entries/profiles/jane.md'));
		$gwen = self::json($this->send('GET', '/entries/profiles/gwen.md'));

		$this->assertFalse(is_array($jane['can'] ?? null) ? $jane['can']['rename'] ?? null : null, 'An account is linked by it (D-355).');
		$this->assertTrue(is_array($gwen['can'] ?? null) ? $gwen['can']['rename'] ?? null : null, 'A guest profile can be renamed.');

		$renamed = $this->write('PATCH', '/entries/profiles/jane.md', ['revision' => $jane['revision'] ?? '', 'slug' => 'jane-doe']);

		$this->assertSame(422, $renamed->getStatusCode());
		$this->assertStringContainsString('linked to this profile by its slug', (string) $renamed->getBody());
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/profiles/jane.md');
	}

	public function testAProfileBelongsToOneAccount(): void
	{
		$this->profiles();
		$this->site();

		$profiles = self::json($this->send('GET', '/profiles'))['profiles'] ?? null;

		$this->assertSame([
			['slug' => 'ghost', 'title' => 'ghost', 'status' => null, 'account' => null],
			['slug' => 'gwen', 'title' => 'Gwen Guest', 'status' => 'draft', 'account' => null],
			['slug' => 'jane', 'title' => 'Jane Author', 'status' => 'published', 'account' => ['username' => 'jane', 'displayName' => 'Jane Author']]
		], $profiles, 'Every profile by name, with the account linked to it (D-356).');

		$taken = $this->write('PATCH', '/accounts/sam', ['author' => 'jane']);

		$this->assertSame(422, $taken->getStatusCode());
		$this->assertSame(['error' => 'The "jane" profile is Jane Author\'s already; a profile belongs to one account.', 'field' => 'author'], self::json($taken));
		$this->assertSame(422, $this->write('POST', '/accounts', ['username' => 'lee', 'email' => 'lee@example.test', 'roles' => ['author'], 'author' => 'jane'])->getStatusCode());
		$this->assertSame(200, $this->write('PATCH', '/accounts/sam', ['author' => 'gwen'])->getStatusCode(), 'A guest profile is free.');
		$this->assertSame(200, $this->write('PATCH', '/accounts/sam', ['author' => 'gwen'])->getStatusCode(), 'Its own stays its own.');
	}

	/**
	 * Returns the first place Jane's profile appears.
	 *
	 * @return array<mixed>
	 */
	private function firstAppearance(): array
	{
		$appears = self::json($this->send('GET', '/profiles/jane'))['appears'] ?? null;
		$this->assertIsArray($appears);
		$first = $appears[0] ?? null;
		$this->assertIsArray($first);

		return $first;
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
		$this->assertSame('Sam Smith', $sam['displayName'] ?? null);
		$this->assertSame(['username', 'email', 'name', 'displayName', 'roles', 'author', 'profile', 'created', 'lastLogin', 'status', 'link', 'manages'], array_keys($sam), 'No password hash or preferences.');
		$this->assertSame('active', $sam['status'] ?? null);
		$this->assertTrue($sam['manages'] ?? null);
		$this->assertFalse(is_array($accounts[0] ?? null) ? $accounts[0]['manages'] ?? null : null, 'Not your own account.');
	}

	public function testNeedsToSeeAccounts(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/roles')->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/accounts')->getStatusCode());
	}
}
