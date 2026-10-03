<?php

/**
 * Admin account and role editing tests.
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
use Blush\Admin\AccountEditController;
use Blush\Admin\PeopleRules;
use Blush\Admin\RoleEditController;
use Blush\Admin\SetPasswordController;
use Blush\Auth\Accounts;
use Blush\Auth\AccountStore;
use Blush\Auth\PasswordLink;
use Blush\Auth\RoleEditor;

#[CoversClass(AccountEditController::class)]
#[CoversClass(RoleEditController::class)]
#[CoversClass(SetPasswordController::class)]
#[CoversClass(PeopleRules::class)]
#[CoversClass(RoleEditor::class)]
final class AdminPeopleEditTest extends TestCase
{
	use BootsAdmin;

	private const string OTHER = 'another long password';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Boots a site where `jane` has some roles, with `config/auth.php`'s
	 * `manager` role (accounts, and a contributor's capabilities), and
	 * signs her in.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('manager', 'Manager', ['accounts.view', 'accounts.create', 'accounts.edit', 'accounts.roles', 'accounts.suspend', 'accounts.delete', 'roles.manage', 'content.*.create', 'content.*.edit', 'content.*.delete', 'media.*.upload', 'media.edit']), new Blush\\Auth\\Role('viewer', 'Viewer', ['accounts.view', 'accounts.suspend', 'content.*.create', 'content.*.edit', 'content.*.delete', 'media.*.upload', 'media.edit']), new Blush\\Auth\\Role('creator', 'Creator', ['accounts.view', 'accounts.create'])]);\n");
		$this->boot(roles: $roles);
		$this->login();
	}

	private function accounts(): Accounts
	{
		return $this->app->container()->make(Accounts::class);
	}

	private function store(): AccountStore
	{
		return $this->app->container()->make(AccountStore::class);
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

	/**
	 * Signs in as someone in a new browser.
	 */
	private function signInAs(string $username, string $password = self::OTHER): ResponseInterface
	{
		$this->cookie = null;

		return $this->send('POST', '/login', json_encode(['username' => $username, 'password' => $password]) ?: '');
	}

	private static function error(ResponseInterface $response): string
	{
		$error = self::json($response)['error'] ?? '';

		return is_string($error) ? $error : '';
	}

	/**
	 * Returns the roles of the account an answer describes.
	 */
	private static function rolesOf(ResponseInterface $response): mixed
	{
		$account = self::json($response)['account'] ?? null;

		return is_array($account) ? $account['roles'] ?? null : null;
	}

	/**
	 * Returns the role an answer describes.
	 *
	 * @return array<mixed>
	 */
	private static function role(ResponseInterface $response): array
	{
		$role = self::json($response)['role'] ?? null;

		return is_array($role) ? $role : [];
	}

	private function file(string $relative): string
	{
		return (string) @file_get_contents($this->temporaryDirectory() . "/{$relative}");
	}

	/**
	 * Returns a link's account and token from its URL.
	 *
	 * @return array{account: string, token: string}
	 */
	private static function linkParts(ResponseInterface $response): array
	{
		$link = self::json($response)['link'] ?? null;
		$url  = is_array($link) && is_string($link['url'] ?? null) ? $link['url'] : '';

		parse_str((string) parse_url($url, PHP_URL_FRAGMENT), $parts);

		return [
			'account' => is_string($parts['account'] ?? null) ? $parts['account'] : '',
			'token'   => is_string($parts['token'] ?? null) ? $parts['token'] : ''
		];
	}

	public function testInvitesAnAccountWithALinkThatSetsItsPassword(): void
	{
		$this->site();

		$created = $this->write('POST', '/accounts', ['username' => 'Sam', 'email' => 'sam@example.test', 'roles' => ['author'], 'author' => 'sam']);

		$this->assertSame(201, $created->getStatusCode());
		$this->assertSame('invited', self::account($created)['status'] ?? null);
		$link = self::json($created)['link'] ?? null;
		$this->assertIsArray($link);
		$this->assertStringStartsWith('https://example.test/admin/set-password#account=sam&token=', is_string($link['url'] ?? null) ? $link['url'] : '');

		$parts = self::linkParts($created);
		$this->assertStringNotContainsString($parts['token'], $this->file('storage/accounts/sam.json'), 'Only a hash is kept.');
		$this->assertSame(401, $this->signInAs('sam', $parts['token'])->getStatusCode(), 'No password works yet.');

		$this->cookie = null;
		$short = $this->send('POST', '/set-password', json_encode([...$parts, 'password' => 'short']) ?: '');
		$this->assertSame(422, $short->getStatusCode());
		$this->assertSame('password', self::json($short)['field'] ?? null);

		$set = $this->send('POST', '/set-password', json_encode([...$parts, 'password' => self::OTHER]) ?: '');
		$this->assertSame(204, $set->getStatusCode());
		$this->assertSame('sam', self::account($this->send('GET', '/session'))['username'] ?? null, 'It signs in.');
		$this->assertNull($this->store()->find('sam')?->passwordLink);
		$this->assertSame('active', $this->store()->find('sam')?->status()->value);

		$this->cookie = null;
		$this->assertSame(410, $this->send('POST', '/set-password', json_encode([...$parts, 'password' => 'yet another long password']) ?: '')->getStatusCode(), 'A link works once.');
		$this->assertSame(200, $this->signInAs('sam')->getStatusCode());
	}

	public function testRefusesExpiredAndWrongLinksAndThrottlesThem(): void
	{
		$this->site();

		[$sam] = $this->accounts()->invite('sam', ['author'], email: 'sam@example.test');
		$this->store()->save($sam->withPasswordLink(new PasswordLink(hash('sha256', 'old'), 1)));

		$this->cookie = null;
		$this->assertSame(410, $this->send('POST', '/set-password', json_encode(['account' => 'sam', 'token' => 'old', 'password' => self::OTHER]) ?: '')->getStatusCode());

		for ($try = 0; $try < 4; $try++) {
			$this->send('POST', '/set-password', json_encode(['account' => 'sam', 'token' => 'wrong', 'password' => self::OTHER]) ?: '');
		}

		$this->assertSame(429, $this->send('POST', '/set-password', json_encode(['account' => 'sam', 'token' => 'wrong', 'password' => self::OTHER]) ?: '')->getStatusCode());
	}

	public function testANewLinkReplacesTheOldAndKeepsThePassword(): void
	{
		$this->site();
		$this->accounts()->create('sam', self::OTHER, ['author'], email: 'sam@example.test');

		$first  = self::linkParts($this->write('POST', '/accounts/sam/link'));
		$second = self::linkParts($this->write('POST', '/accounts/sam/link'));

		$this->assertSame(200, $this->signInAs('sam')->getStatusCode(), 'The password still works.');

		$this->cookie = null;
		$this->assertSame(410, $this->send('POST', '/set-password', json_encode([...$first, 'password' => 'a brand new password']) ?: '')->getStatusCode());
		$this->assertSame(204, $this->send('POST', '/set-password', json_encode([...$second, 'password' => 'a brand new password']) ?: '')->getStatusCode());
	}

	public function testChecksNewAccounts(): void
	{
		$this->site();

		$this->assertSame('username', self::json($this->write('POST', '/accounts', ['username' => 'bad name', 'email' => 'bad-name@example.test', 'roles' => ['author']]))['field'] ?? null);
		$this->assertSame('username', self::json($this->write('POST', '/accounts', ['username' => 'jane', 'email' => 'jane@example.test', 'roles' => ['author']]))['field'] ?? null);
		$this->assertSame('username', self::json($this->write('POST', '/accounts', ['username' => 'new', 'email' => 'new@example.test', 'roles' => ['author']]))['field'] ?? null, 'The New Account screen is accounts/new.');
		$this->assertSame('roles', self::json($this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['ghost']]))['field'] ?? null);
		$this->assertSame('email', self::json($this->write('POST', '/accounts', ['username' => 'sam', 'roles' => ['author']]))['field'] ?? null, 'Every account needs an email address (D-370).');
		$this->assertSame('email', self::json($this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'nope', 'roles' => ['author']]))['field'] ?? null);
		$this->assertSame('email', self::json($this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'JANE@example.test', 'roles' => ['author']]))['field'] ?? null, 'Another account\'s.');
		$this->assertNull($this->store()->find('sam'));
		$this->assertSame(['member'], self::rolesOf($this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => []])), 'No roles is a member (D-365).');
	}

	public function testChangesAnAccountsRolesAuthorAndSuspension(): void
	{
		$this->site();
		$this->accounts()->create('sam', self::OTHER, ['author'], email: 'sam@example.test');

		$changed = $this->write('PATCH', '/accounts/sam', ['roles' => ['editor', 'author'], 'author' => 'sam']);

		$this->assertSame(200, $changed->getStatusCode(), self::error($changed));
		$sam = $this->store()->find('sam');
		$this->assertNotNull($sam);
		$this->assertSame(['editor', 'author'], $sam->roles);
		$this->assertSame('sam', $sam->author);

		$this->assertSame(200, $this->write('PATCH', '/accounts/sam', ['author' => null])->getStatusCode());
		$this->assertNull($this->store()->find('sam')?->author);
	}

	public function testSuspendingSignsOutAndBlocksSignIn(): void
	{
		$this->site();
		$this->accounts()->create('sam', self::OTHER, ['author'], email: 'sam@example.test');

		$this->signInAs('sam');
		$sam = $this->cookie;

		$this->cookie = null;
		$this->login();
		$this->assertSame('suspended', self::account($this->write('PATCH', '/accounts/sam', ['suspended' => true]))['status'] ?? null);
		$this->assertSame(422, $this->write('POST', '/accounts/sam/link')->getStatusCode(), 'No links while suspended.');

		$this->cookie = $sam;
		$this->assertNull(self::json($this->send('GET', '/session'))['account'] ?? null, 'Its session ends.');

		$refused = $this->signInAs('sam');
		$this->assertSame(403, $refused->getStatusCode());
		$this->assertStringContainsString('suspended', self::error($refused));

		$this->cookie = null;
		$this->login();
		$this->write('PATCH', '/accounts/sam', ['suspended' => false]);
		$this->assertSame(200, $this->signInAs('sam')->getStatusCode());
	}

	public function testRemovesAnAccount(): void
	{
		$this->site();
		$this->accounts()->create('sam', self::OTHER, ['author'], email: 'sam@example.test');

		$this->assertSame(204, $this->write('DELETE', '/accounts/sam')->getStatusCode());
		$this->assertNull($this->store()->find('sam'));
		$this->assertSame(404, $this->write('DELETE', '/accounts/sam')->getStatusCode());
	}

	public function testNeverChangesYourOwnAccount(): void
	{
		$this->site();

		$this->assertSame(403, $this->write('PATCH', '/accounts/jane', ['roles' => ['author']])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/accounts/jane', ['suspended' => true])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/accounts/jane', ['author' => null, 'roles' => ['author']])->getStatusCode(), 'Only the link, on your own.');
		$this->assertSame(200, $this->write('PATCH', '/accounts/jane', ['author' => null])->getStatusCode(), 'Your own profile link is yours to change (D-373).');
		$this->assertNull($this->store()->find('jane')?->author);
		$this->assertSame(200, $this->write('PATCH', '/accounts/jane', ['author' => 'jane'])->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/accounts/jane/link')->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/accounts/jane')->getStatusCode());
		$this->assertSame(['administrator'], $this->store()->find('jane')?->roles);
	}

	public function testNeverGrantsMoreThanYouHave(): void
	{
		$this->site(['manager']);
		$this->accounts()->create('sam', self::OTHER, ['contributor'], email: 'sam@example.test');
		$this->accounts()->create('ada', self::OTHER, ['administrator'], email: 'ada@example.test');

		$this->assertSame(403, $this->write('POST', '/accounts', ['username' => 'lee', 'email' => 'lee@example.test', 'roles' => ['editor']])->getStatusCode(), 'Editors publish; managers don\'t.');
		$this->assertSame(403, $this->write('PATCH', '/accounts/sam', ['roles' => ['administrator']])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/accounts/ada', ['suspended' => true])->getStatusCode(), 'An administrator can do more.');
		$this->assertSame(403, $this->write('POST', '/roles', ['name' => 'boss', 'label' => 'Boss', 'capabilities' => ['site.settings']])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/roles/editor', ['capabilities' => ['content.*.edit']])->getStatusCode(), 'Editors can do more.');

		$roles = self::json($this->send('GET', '/roles'))['roles'] ?? [];
		$this->assertIsArray($roles);
		$this->assertSame(['contributor', 'member', 'manager', 'viewer', 'creator'], array_column(array_filter($roles, static fn (mixed $role): bool => is_array($role) && ($role['grantable'] ?? false) === true), 'name'));

		$this->assertSame(201, $this->write('POST', '/accounts', ['username' => 'lee', 'email' => 'lee@example.test', 'roles' => ['contributor']])->getStatusCode());
	}

	public function testMakesChangesAndDeletesACustomRole(): void
	{
		$this->site();

		$created = $this->write('POST', '/roles', ['name' => 'reviewer', 'label' => ' Reviewer ', 'description' => 'Reads drafts.', 'capabilities' => ['content.*.edit', 'content.*.edit.others']]);

		$this->assertSame(201, $created->getStatusCode(), self::error($created));
		$this->assertSame(['roles' => [['name' => 'reviewer', 'label' => 'Reviewer', 'capabilities' => ['content.*.edit', 'content.*.edit.others'], 'description' => 'Reads drafts.']]], json_decode($this->file('storage/roles.json'), true));
		$this->assertSame('custom', self::role($created)['origin'] ?? null);
		$this->assertSame(422, $this->write('POST', '/roles', ['name' => 'manager', 'label' => 'Again'])->getStatusCode(), 'The name is in use.');

		$changed = $this->write('PATCH', '/roles/reviewer', ['label' => 'Proofreader', 'capabilities' => ['content.*.edit']]);
		$this->assertSame('Proofreader', self::role($changed)['label'] ?? null);
		$this->assertSame(['content.*.edit'], self::role($changed)['capabilities'] ?? null);

		$this->accounts()->create('sam', self::OTHER, ['reviewer'], email: 'sam@example.test');
		$held = $this->write('DELETE', '/roles/reviewer');
		$this->assertSame(422, $held->getStatusCode());
		$this->assertStringContainsString('sam', self::error($held));

		$this->write('PATCH', '/accounts/sam', ['roles' => ['author']]);
		$this->assertSame(200, $this->write('DELETE', '/roles/reviewer')->getStatusCode());
		$this->assertSame('{"roles": []}', str_replace(["\n", "\t", '    '], '', $this->file('storage/roles.json')));
	}

	public function testChangesAndResetsABuiltInRole(): void
	{
		$this->site();

		$role = self::role($this->write('PATCH', '/roles/editor', ['capabilities' => ['content.*.edit', 'content.*.edit.others']]));

		$this->assertSame('changed', $role['origin'] ?? null);
		$this->assertSame('Editor', $role['label'] ?? null);
		$this->assertContains('site.publish', is_array($role['defaults'] ?? null) ? $role['defaults'] : []);
		$this->assertSame(422, $this->write('PATCH', '/roles/editor', ['label' => 'Boss'])->getStatusCode(), 'A built-in keeps its name.');

		$reset = self::role($this->write('DELETE', '/roles/editor'));
		$this->assertSame('built-in', $reset['origin'] ?? null);
		$this->assertContains('site.publish', is_array($reset['capabilities'] ?? null) ? $reset['capabilities'] : []);
		$this->assertSame(422, $this->write('DELETE', '/roles/editor')->getStatusCode(), 'Nothing to reset.');
	}

	public function testLeavesTheAdministratorAndConfigRolesAlone(): void
	{
		$this->site();

		$this->assertSame(422, $this->write('PATCH', '/roles/administrator', ['capabilities' => []])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/roles/manager', ['capabilities' => []])->getStatusCode());
		$this->assertSame(422, $this->write('DELETE', '/roles/manager')->getStatusCode());
		$this->assertSame(400, $this->write('POST', '/roles', ['name' => 'all', 'label' => 'All', 'capabilities' => ['*']])->getStatusCode());
		$this->assertSame(422, $this->write('POST', '/roles', ['name' => 'odd', 'label' => 'Odd', 'capabilities' => ['no.such.thing']])->getStatusCode());
		$this->assertSame(422, $this->write('POST', '/roles', ['name' => 'new', 'label' => 'New', 'capabilities' => []])->getStatusCode(), 'The New Role screen is roles/new.');
		$this->assertSame('', $this->file('storage/roles.json'));
	}

	public function testSomeoneCanAlwaysManageAccounts(): void
	{
		$this->site();
		$this->write('POST', '/roles', ['name' => 'boss', 'label' => 'Boss', 'capabilities' => ['accounts.view', 'accounts.create', 'accounts.edit', 'accounts.roles', 'accounts.suspend', 'accounts.delete', 'roles.manage', 'content.*.edit']]);
		$this->accounts()->create('sam', self::OTHER, ['boss'], email: 'sam@example.test');
		$jane = $this->store()->find('jane');
		$this->assertNotNull($jane);
		$this->accounts()->setRoles($jane, ['boss']);

		$before  = $this->file('storage/roles.json');
		$refused = $this->write('PATCH', '/roles/boss', ['capabilities' => ['content.*.edit']]);

		$this->assertSame(422, $refused->getStatusCode(), self::error($refused));
		$this->assertSame($before, $this->file('storage/roles.json'), 'The roles are put back.');
	}

	public function testEachChangeNeedsItsCapability(): void
	{
		$this->site(['viewer']);
		$this->accounts()->create('sam', self::OTHER, ['contributor'], email: 'sam@example.test');
		$this->accounts()->create('ada', self::OTHER, ['administrator'], email: 'ada@example.test');

		$this->assertSame(200, $this->send('GET', '/accounts')->getStatusCode(), 'Seeing accounts (D-362).');
		$this->assertSame(200, $this->send('GET', '/roles')->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/accounts', ['username' => 'lee', 'email' => 'lee@example.test', 'roles' => ['contributor']])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/accounts/sam', ['roles' => ['author']])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/accounts/sam', ['name' => 'Sam'])->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/accounts/sam/link')->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/accounts/sam')->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/roles', ['name' => 'boss', 'label' => 'Boss'])->getStatusCode());
		$this->assertSame(200, $this->write('PATCH', '/accounts/sam', ['suspended' => true])->getStatusCode(), 'Suspending is its own.');
		$roles = self::json($this->send('GET', '/roles'))['roles'] ?? [];
		$this->assertIsArray($roles);
		$this->assertFalse(array_find($roles, static fn (mixed $role): bool => is_array($role) && ($role['name'] ?? null) === 'contributor')['editable'] ?? null, 'Changing roles needs roles.manage.');
	}

	public function testNeedsToSeeAccounts(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author']])->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/roles', ['name' => 'boss', 'label' => 'Boss'])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/roles/editor')->getStatusCode());
	}

	public function testCreatingWithoutGivingRolesMakesMembers(): void
	{
		$this->site(['creator']);

		$this->assertSame(403, $this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['contributor']])->getStatusCode(), 'Giving roles needs accounts.roles (D-365).');
		$this->assertSame(['member'], self::rolesOf($this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['member']])));
	}

	public function testMembersHaveNothing(): void
	{
		$this->site();

		$this->assertSame(422, $this->write('PATCH', '/roles/member', ['capabilities' => ['menus.edit']])->getStatusCode());
		$this->assertSame(422, $this->write('DELETE', '/roles/member')->getStatusCode());

		$this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author']]);
		$this->assertSame(['member'], self::rolesOf($this->write('PATCH', '/accounts/sam', ['roles' => []])), 'Taking the last role leaves the member.');
		$this->assertSame(['editor'], self::rolesOf($this->write('PATCH', '/accounts/sam', ['roles' => ['member', 'editor']])));
	}

	public function testNamesAccounts(): void
	{
		$this->site();

		$created = $this->write('POST', '/accounts', ['username' => 'sam', 'email' => 'sam@example.test', 'roles' => ['author'], 'name' => ' Sam  Smith ']);
		$account = self::json($created)['account'] ?? null;

		$this->assertSame(201, $created->getStatusCode());
		$this->assertIsArray($account);
		$this->assertSame('Sam Smith', $account['name'] ?? null);
		$this->assertSame('Sam Smith', $account['displayName'] ?? null);

		$tooLong = $this->write('POST', '/accounts', ['username' => 'lee', 'email' => 'lee@example.test', 'roles' => ['author'], 'name' => str_repeat('a', 101)]);

		$this->assertSame(422, $tooLong->getStatusCode());
		$this->assertSame('name', self::json($tooLong)['field'] ?? null);
		$this->assertNull($this->store()->find('lee'), 'Nothing is made.');

		$this->assertSame(200, $this->write('PATCH', '/accounts/sam', ['name' => 'Samuel Smith'])->getStatusCode());
		$this->assertSame('Samuel Smith', $this->store()->find('sam')?->name);
		$this->assertSame(422, $this->write('PATCH', '/accounts/sam', ['name' => str_repeat('a', 101)])->getStatusCode());
		$this->assertSame(400, $this->write('PATCH', '/accounts/sam', ['name' => 5])->getStatusCode());

		$cleared = self::json($this->write('PATCH', '/accounts/sam', ['name' => null]))['account'] ?? null;

		$this->assertIsArray($cleared);
		$this->assertArrayHasKey('name', $cleared);
		$this->assertNull($cleared['name']);
		$this->assertSame('sam', $cleared['displayName'] ?? null, 'Without a name or author page, the username.');
		$this->assertSame(['author'], $this->store()->find('sam')?->roles, 'Other fields stay.');
	}
}
