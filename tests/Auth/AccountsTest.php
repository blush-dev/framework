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
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\AuthConfig;
use Blush\Auth\AuthException;
use Blush\Auth\Passwords;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Clock\SystemClock;
use Blush\Content\Entries;
use Blush\Core\Application;
use Blush\Storage\Record\ArrayRecordStore;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Account::class)]
#[CoversClass(AccountProfiles::class)]
#[CoversClass(Accounts::class)]
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

	private function store(): Accounts
	{
		return $this->app()->container()->make(Accounts::class);
	}

	private function profiles(): AccountProfiles
	{
		return $this->app()->container()->make(AccountProfiles::class);
	}

	private function writeJane(string $extra = ''): void
	{
		$this->writeTemporaryFile('user/content/_profile/jane.md', "---\nid: 04e1cf46-8734-1fc4-7399-c1e7571e878e\ntitle: Jane Author\n{$extra}---\n");
	}

	public function testCreatesAnAccountFile(): void
	{
		$account = $this->accounts()->create('jane', 'a long enough password', ['editor', 'editor'], email: 'jane@example.test');
		$file    = $this->temporaryDirectory() . '/storage/accounts/jane.json';

		$this->assertSame(['editor'], $account->roles);
		$this->assertFileExists($file);
		$this->assertNotSame('', $account->id, 'A saved account has its id (D-669).');
		$this->assertStringContainsString("\"id\": \"{$account->id}\"", (string) file_get_contents($file));
		$this->assertEquals($account, $this->store()->findById($account->id));
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
		$this->writeJane();
		$account = $this->profiles()->link($account, 'jane');

		$stored = $this->store()->find('jane');

		$this->assertTrue(new Passwords()->verify('another long password', $stored->passwordHash ?? ''));
		$this->assertSame(['editor', 'contributor'], $stored?->roles);
		$this->assertSame('04e1cf46-8734-1fc4-7399-c1e7571e878e', $stored->profile, 'Linked by the profile\'s id (D-668).');
		$this->assertSame('jane', $this->profiles()->slug($stored));

		$this->profiles()->link($account, null);
		$this->assertNull($this->store()->find('jane')?->profile);
	}

	public function testNamesAccounts(): void
	{
		$this->writeJane();

		$account = $this->accounts()->create('jane', 'a long enough password', ['author'], '04e1cf46-8734-1fc4-7399-c1e7571e878e', "  Jane\t\n  Doe ", email: 'jane@example.test');

		$this->assertSame('Jane Doe', $account->name, 'Spaces and line breaks are tidied.');
		$this->assertSame('Jane Doe', $this->profiles()->displayName($account), 'An account\'s own name comes first (D-370).');
		$this->assertSame('Jane Doe', $this->profiles()->displayName($account->withProfile(null)));
		$this->assertStringContainsString('"name": "Jane Doe"', (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/jane.json'));
		$this->assertEquals($account, $this->store()->find('jane'));

		$account = $this->accounts()->setName($account, 'José Ñúñez 李');
		$this->assertSame('José Ñúñez 李', $this->store()->find('jane')?->name);
		$this->assertSame(Account::NAME_LENGTH, mb_strlen($this->accounts()->setName($account, str_repeat('é', Account::NAME_LENGTH))->name ?? ''));

		$account = $this->accounts()->setName($account, '   ');
		$this->assertNull($account->name);
		$this->assertSame('Jane Author', $this->profiles()->displayName($account), 'Without one, its profile\'s title.');
		$this->assertSame('jane', $this->profiles()->displayName($account->withProfile(null)), 'Then the username.');

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

		$old = $this->store()->find('old');
		$this->assertNotNull($old);

		$this->assertNull($old->email, 'One saved before emails has none until it\'s given one.');
		$this->assertNotSame('', $old->id, 'A file without an id has a steady one (D-669).');
		$this->assertSame($old->id, $this->store()->find('old')?->id);
		$this->assertSame($old->id, $this->accounts()->setEmail($old, 'old@example.test')->id, 'Its next save writes it.');
		$this->assertStringContainsString($old->id, (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/old.json'));
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
		$this->writeTemporaryFile('storage/accounts/jane.json', '{"username": "jane", "passwordHash": ');

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('isn\'t valid JSON');

		$this->store()->find('jane');
	}

	public function testFindsProfiles(): void
	{
		$this->writeJane();
		$this->writeTemporaryFile('user/content/_post/credited.md', "---\nid: 43432b5f-3e36-5bde-fad8-73fc3316fd8e\ntitle: Credited\nauthors: lee\n---\n");
		$this->writeTemporaryFile('user/data/types/post.json', '{"urls": {"prefix": "posts"}}');

		$this->assertSame('04e1cf46-8734-1fc4-7399-c1e7571e878e', $this->profiles()->find('jane')?->id);
		$this->assertNull($this->profiles()->find('lee'), 'Credited with no file isn\'t a profile (D-584).');
		$this->assertTrue($this->profiles()->wouldCreate('lee'));
		$this->assertFalse($this->profiles()->wouldCreate('jane'));
	}

	public function testLinkingToANewSlugMakesADraftProfile(): void
	{
		$sam = $this->profiles()->link($this->accounts()->create('sam', 'a long enough password', ['author'], email: 'sam@example.test'), 'sam-smith', 'Sam Smith');
		$new = $this->profiles()->entry($sam);

		$this->assertSame('sam-smith', $new?->key, 'An account is never linked to nothing (D-668).');
		$this->assertSame('Sam Smith', $new->title);
		$this->assertSame('draft', $new->status->value);
		$this->assertSame($new->id, $sam->profile);
	}

	public function testALinkOutlivesARename(): void
	{
		$this->writeJane();

		$jane    = $this->profiles()->link($this->accounts()->create('jane', 'a long enough password', ['author'], email: 'jane@example.test'), 'jane');
		$content = $this->app()->container()->make(Entries::class);

		$content->rename('04e1cf46-8734-1fc4-7399-c1e7571e878e', 'jane-doe');

		$this->assertSame('jane-doe', $this->profiles()->slug($jane), 'The link is by id, so a rename keeps it (D-668).');
	}

	public function testLinksAProfileToOneAccount(): void
	{
		$this->writeJane();

		$accounts = $this->accounts();
		$profiles = $this->profiles();
		$jane     = $accounts->create('jane', 'a long enough password', ['author'], '04e1cf46-8734-1fc4-7399-c1e7571e878e', email: 'jane@example.test');
		$sam      = $accounts->create('sam', 'a long enough password', ['author'], email: 'sam@example.test');

		$this->assertSame('jane', $accounts->linkedTo('04e1cf46-8734-1fc4-7399-c1e7571e878e')?->username);
		$this->assertSame('jane', $profiles->accountFor('jane')?->username);
		$this->assertNull($accounts->linkedTo('04e1cf46-8734-1fc4-7399-c1e7571e878e', except: 'jane'));
		$this->assertSame('04e1cf46-8734-1fc4-7399-c1e7571e878e', $profiles->link($jane, 'jane')->profile, 'Its own stays its own.');

		foreach ([static fn () => $profiles->link($sam, 'jane'), static fn () => $accounts->create('lee', 'a long enough password', ['author'], '04e1cf46-8734-1fc4-7399-c1e7571e878e', email: 'lee@example.test')] as $link) {
			try {
				$link();
				$this->fail('A second account.');
			} catch (AuthException $e) {
				$this->assertStringContainsString('a profile belongs to one account', $e->getMessage());
			}
		}

		$profiles->link($jane, null);
		$this->assertSame('04e1cf46-8734-1fc4-7399-c1e7571e878e', $profiles->link($sam, 'jane')->profile, 'Free once unlinked (D-356).');
	}

	public function testRefusesALockedProfile(): void
	{
		$this->writeTemporaryFile('user/content/_profile/staff.md', "---\nid: 4c955e2c-0b36-ecf4-eb83-68d67d798a53\ntitle: Staff\nlinkable: false\n---\n");
		$this->writeTemporaryFile('user/content/_profile/jane.md', "---\nid: 04e1cf46-8734-1fc4-7399-c1e7571e878e\ntitle: Jane\n---\n");

		$profiles = $this->profiles();
		$sam      = $this->accounts()->create('sam', 'a long enough password', ['author'], email: 'sam@example.test');

		$this->assertFalse($profiles->isLinkable('staff'));
		$this->assertTrue($profiles->isLinkable('jane'));
		$this->assertTrue($profiles->isLinkable('lee'), 'A profile with no file yet isn\'t locked.');

		foreach ([static fn () => $profiles->link($sam, 'staff'), static fn () => $profiles->prepare('staff', null, 'lee')] as $link) {
			try {
				$link();
				$this->fail('A locked profile (D-605).');
			} catch (AuthException $e) {
				$this->assertStringContainsString('is locked', $e->getMessage());
			}
		}

		$this->assertSame('jane', $profiles->slug($profiles->link($sam, 'jane')));
	}

	public function testSiteRolesReplaceAndAddToTheBuiltIns(): void
	{
		$roles = new Roles(AuthConfig::fromArray(new AuthConfig(roles: [
			new Role('editor', 'Copy editor', ['content.*.edit.others']),
			new Role('reviewer', 'Reviewer', ['content.*.edit']),
			new Role('member', 'Member', ['site.settings']),
			new Role('owner', 'Owner', ['site.settings'])
		])->toArray()), new ArrayRecordStore(), new SystemClock());

		$this->assertSame(['owner', 'administrator', 'editor', 'author', 'contributor', 'member', 'reviewer'], array_keys($roles->all()));
		$this->assertSame('Copy editor', $roles->get('editor')?->label);
		$this->assertFalse($roles->get('editor')->allows('site.publish'));
		$this->assertTrue($roles->get('owner')?->allows('anything.at.all'), 'Config can\'t change the owner (D-500).');
		$this->assertFalse($roles->get('administrator')?->allows('anything.at.all'));
		$this->assertSame([], $roles->get('member')?->capabilities, 'Config can\'t give the member anything (D-365).');
	}

	public function testRejectsBadRoles(): void
	{
		$this->expectException(AuthException::class);

		new Role('reviewer', 'Reviewer', ['Not A Capability']);
	}
}
