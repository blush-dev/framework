<?php

/**
 * Account tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthConfig;
use Blush\Auth\AuthException;
use Blush\Auth\FileAccountStore;
use Blush\Auth\Passwords;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Core\Application;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Account::class)]
#[CoversClass(Accounts::class)]
#[CoversClass(FileAccountStore::class)]
#[CoversClass(Passwords::class)]
#[CoversClass(Roles::class)]
#[CoversClass(Role::class)]
#[CoversClass(AuthConfig::class)]
final class AccountsTest extends TestCase
{
	use BootsScratchSite;

	private ?Application $app = null;

	private function app(): Application
	{
		if ($this->app === null) {
			$this->app = $this->scratchApplication(['APP_ENV' => 'development']);
			$this->app->boot();
		}

		return $this->app;
	}

	private function accounts(): Accounts
	{
		return $this->app()->container()->make(Accounts::class);
	}

	private function store(): AccountStore
	{
		return $this->app()->container()->make(AccountStore::class);
	}

	public function testCreatesAnAccountFile(): void
	{
		$account = $this->accounts()->create('jane', 'a long enough password', ['editor', 'editor'], 'jane-doe', email: 'jane@example.test');
		$file    = $this->temporaryDirectory() . '/storage/accounts/jane.json';

		$this->assertSame(['editor'], $account->roles);
		$this->assertFileExists($file);
		$this->assertSame('0660', substr(sprintf('%o', fileperms($file)), -4));
		$this->assertStringNotContainsString('a long enough password', (string) file_get_contents($file));
		$this->assertEquals($account, $this->store()->find('jane'));
		$this->assertTrue(new Passwords()->verify('a long enough password', $account->passwordHash));
		$this->assertFalse($this->store()->isEmpty());
	}

	public function testRefusesBadAccounts(): void
	{
		$this->accounts()->create('jane', 'a long enough password', ['editor'], email: 'jane@example.test');

		$cases = [
			['jane', 'a long enough password', ['editor'], 'There\'s already an account named "jane".'],
			['Jane!', 'a long enough password', ['editor'], 'can\'t be a username'],
			['sam', 'short', ['editor'], 'at least 12 characters'],
			['sam', 'a long enough password', ['boss'], 'There\'s no "boss" role']
		];

		foreach ($cases as [$username, $password, $roles, $message]) {
			try {
				$this->accounts()->create($username, $password, $roles, email: "{$username}@example.test");
				$this->fail("Created {$username}.");
			} catch (AuthException $e) {
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}
	}

	public function testNoRolesIsTheMember(): void
	{
		$sam = $this->accounts()->create('sam', 'a long enough password', [], email: 'sam@example.test');

		$this->assertSame(['member'], $sam->roles, 'No roles is the member\'s (D-365).');
		$this->assertSame(['editor'], $this->accounts()->setRoles($sam, ['member', 'editor'])->roles, 'The member only when there\'s nothing else.');
		$this->assertSame(['member'], $this->accounts()->setRoles($sam, [])->roles);
		$this->assertSame(['author', 'editor'], Accounts::settle(['author', 'editor', 'author']));
	}

	public function testChangesAccounts(): void
	{
		$account = $this->accounts()->create('jane', 'a long enough password', ['author'], email: 'jane@example.test');
		$account = $this->accounts()->setPassword($account, 'another long password');
		$account = $this->accounts()->setRoles($account, ['editor', 'contributor']);
		$account = $this->accounts()->setAuthor($account, 'jane');

		$stored = $this->store()->find('jane');

		$this->assertTrue(new Passwords()->verify('another long password', $stored->passwordHash ?? ''));
		$this->assertSame(['editor', 'contributor'], $stored?->roles);
		$this->assertSame('jane', $stored->author);

		$this->accounts()->setAuthor($account, null);
		$this->assertNull($this->store()->find('jane')?->author);
	}

	public function testNamesAccounts(): void
	{
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane Author\n---\n");

		$account = $this->accounts()->create('jane', 'a long enough password', ['author'], 'jane', "  Jane\t\n  Doe ", email: 'jane@example.test');

		$this->assertSame('Jane Doe', $account->name, 'Spaces and line breaks are tidied.');
		$this->assertSame('Jane Doe', $this->accounts()->displayName($account), 'An account\'s own name comes first (D-370).');
		$this->assertSame('Jane Doe', $this->accounts()->displayName($account->withAuthor(null)));
		$this->assertStringContainsString('"name": "Jane Doe"', (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/jane.json'));
		$this->assertEquals($account, $this->store()->find('jane'));

		$account = $this->accounts()->setName($account, 'José Ñúñez 李');
		$this->assertSame('José Ñúñez 李', $this->store()->find('jane')?->name);
		$this->assertSame(Account::NAME_LENGTH, mb_strlen($this->accounts()->setName($account, str_repeat('é', Account::NAME_LENGTH))->name ?? ''));

		$account = $this->accounts()->setName($account, '   ');
		$this->assertNull($account->name);
		$this->assertSame('Jane Author', $this->accounts()->displayName($account), 'Without one, its profile\'s title.');
		$this->assertSame('jane', $this->accounts()->displayName($account->withAuthor(null)), 'Then the username.');

		foreach ([str_repeat('a', Account::NAME_LENGTH + 1), "Jane\u{0007}"] as $bad) {
			try {
				$this->accounts()->setName($account, $bad);
				$this->fail('Named the account ' . json_encode($bad));
			} catch (AuthException $e) {
				$this->assertStringContainsString('A name is up to', $e->getMessage());
			}
		}

		$this->assertFalse(Account::isValidName(' Jane'));
		$this->assertFalse(Account::isValidName("Jane\u{2028}Doe"));
		$this->assertFalse(Account::isValidName(''));
	}

	public function testEveryAccountNeedsAnEmailAddress(): void
	{
		$account = $this->accounts()->create('jane', 'a long enough password', ['author'], email: ' jane@example.test ');

		$this->assertSame('jane@example.test', $account->email, 'Trimmed (D-370).');
		$this->assertStringContainsString('"email": "jane@example.test"', (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/jane.json'));

		foreach (['' => 'needs an email address', 'not an email' => 'isn\'t an email address', 'JANE@example.test' => 'already has that email address'] as $email => $problem) {
			try {
				$this->accounts()->create('sam', 'a long enough password', ['author'], email: $email);
				$this->fail('Made an account with ' . json_encode($email));
			} catch (AuthException $e) {
				$this->assertStringContainsString($problem, $e->getMessage());
			}
		}

		$this->assertSame('jane@new.example', $this->accounts()->setEmail($account, 'jane@new.example')->email);
		$this->assertSame('jane@new.example', $this->store()->find('jane')?->email);

		$this->writeTemporaryFile('storage/accounts/old.json', (string) json_encode(['username' => 'old', 'passwordHash' => 'x', 'roles' => ['author']]));
		$this->assertNull($this->store()->find('old')?->email, 'One saved before emails has none until it\'s given one.');
	}

	public function testListsAndDeletesAccounts(): void
	{
		$this->accounts()->create('sam', 'a long enough password', ['editor'], email: 'sam@example.test');
		$this->accounts()->create('jane', 'a long enough password', ['editor'], email: 'jane@example.test');

		$this->assertSame(['jane', 'sam'], array_map(static fn (Account $account): string => $account->username, $this->store()->all()));

		$this->store()->delete('sam');
		$this->store()->delete('../escape');

		$this->assertNull($this->store()->find('sam'));
		$this->assertNull($this->store()->find('../jane'));
	}

	public function testReportsADamagedAccountFile(): void
	{
		$this->writeTemporaryFile('storage/accounts/jane.json', '{"username": "sam", "passwordHash": "x", "roles": []}');

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('holds the account "sam"');

		$this->store()->find('jane');
	}

	public function testChecksAuthors(): void
	{
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane\n---\n");
		$this->writeTemporaryFile('user/content/_posts/credited.md', "---\ntitle: Credited\nauthors: lee\n---\n");
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");

		$this->assertTrue($this->accounts()->hasAuthor('jane'));
		$this->assertTrue($this->accounts()->hasAuthor('lee'), 'Entries credit a virtual author.');
		$this->assertFalse($this->accounts()->hasAuthor('sam'));
	}

	public function testLinksAProfileToOneAccount(): void
	{
		$accounts = $this->accounts();
		$jane     = $accounts->create('jane', 'a long enough password', ['author'], 'jane', email: 'jane@example.test');
		$sam      = $accounts->create('sam', 'a long enough password', ['author'], email: 'sam@example.test');

		$this->assertSame('jane', $accounts->linkedTo('jane')?->username);
		$this->assertNull($accounts->linkedTo('jane', except: 'jane'));
		$this->assertSame('jane', $accounts->setAuthor($jane, 'jane')->author, 'Its own stays its own.');

		foreach ([static fn () => $accounts->setAuthor($sam, 'jane'), static fn () => $accounts->create('lee', 'a long enough password', ['author'], 'jane', email: 'lee@example.test')] as $link) {
			try {
				$link();
				$this->fail('A second account.');
			} catch (AuthException $e) {
				$this->assertStringContainsString('a profile belongs to one account', $e->getMessage());
			}
		}

		$accounts->setAuthor($jane, null);
		$this->assertSame('jane', $accounts->setAuthor($sam, 'jane')->author, 'Free once unlinked (D-356).');
	}

	public function testSiteRolesReplaceAndAddToTheBuiltIns(): void
	{
		$roles = new Roles(AuthConfig::fromArray(new AuthConfig(roles: [
			new Role('editor', 'Copy editor', ['content.*.edit.others']),
			new Role('reviewer', 'Reviewer', ['content.*.edit']),
			new Role('member', 'Member', ['site.settings'])
		])->toArray()), new MemoryRoleStore());

		$this->assertSame(['administrator', 'editor', 'author', 'contributor', 'member', 'reviewer'], array_keys($roles->all()));
		$this->assertSame('Copy editor', $roles->get('editor')?->label);
		$this->assertFalse($roles->get('editor')->allows('site.publish'));
		$this->assertTrue($roles->get('administrator')?->allows('anything.at.all'));
		$this->assertSame([], $roles->get('member')?->capabilities, 'Config can\'t give the member anything (D-365).');
	}

	public function testRejectsBadRoles(): void
	{
		$this->expectException(AuthException::class);

		new Role('reviewer', 'Reviewer', ['Not A Capability']);
	}
}
