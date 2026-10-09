<?php

/**
 * Account command tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Auth\Passwords;
use Blush\Console\Commands\AddAccount;
use Blush\Console\Commands\ListAccounts;
use Blush\Console\Commands\RemoveAccount;
use Blush\Console\Commands\SetAccountAuthor;
use Blush\Console\Commands\SetAccountPassword;
use Blush\Console\Commands\SetAccountRoles;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Prompt;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Tests\BootsScratchSite;

#[CoversClass(AddAccount::class)]
#[CoversClass(ListAccounts::class)]
#[CoversClass(SetAccountPassword::class)]
#[CoversClass(SetAccountRoles::class)]
#[CoversClass(SetAccountAuthor::class)]
#[CoversClass(RemoveAccount::class)]
#[CoversClass(Prompt::class)]
final class AccountCommandsTest extends TestCase
{
	use BootsScratchSite;

	private const string PASSWORD = 'a long enough password';

	private ?Application $app = null;

	private function app(): Application
	{
		if ($this->app === null) {
			$this->app = $this->scratchApplication(['APP_ENV' => 'development']);
			$this->app->boot();
		}

		return $this->app;
	}

	/**
	 * @param string|list<string> $command
	 * @param list<string>        $answers
	 */
	private function command(string|array $command, array $answers = []): CommandResult
	{
		return new CommandTester($this->app()->container()->make(Console::class))->run($command, $answers);
	}

	private function store(): Accounts
	{
		return $this->app()->container()->make(Accounts::class);
	}

	public function testAddsAnOwnerThenAdministratorsByDefault(): void
	{
		$result = $this->command('account:add jane --email=jane@example.test', ['short', 'mismatched password', 'something else', self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Passwords must be at least 12 characters.', $result->errors);
		$this->assertStringContainsString('Those didn\'t match; try again.', $result->errors);
		$this->assertStringContainsString('Created the "jane" account (owner).', $result->output);
		$this->assertSame(['owner'], $this->store()->find('jane')?->roles, 'A site\'s first is its owner (D-500).');

		$result = $this->command('account:add sam --email=sam@example.test', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertSame(['administrator'], $this->store()->find('sam')?->roles);
	}

	public function testAddsAnAccountWithRolesAndANewProfile(): void
	{
		$result = $this->command('account:add sam --email=sam@example.test --role=editor --role=author --author=sam', [self::PASSWORD, self::PASSWORD, 'Sam Smith']);
		$file   = $this->temporaryDirectory() . '/user/content/profiles/s/sam.md';

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('The "sam" profile doesn\'t exist yet, so it\'s made as a draft.', $result->output);
		$this->assertStringContainsString('title: "Sam Smith"', (string) file_get_contents($file));
		$this->assertStringContainsString('status: draft', (string) file_get_contents($file));
		$this->assertSame(['editor', 'author'], $this->store()->find('sam')?->roles);
		$this->assertNotNull($this->store()->find('sam')->profile, 'Linked by the new profile\'s id (D-668).');
		$this->assertSame(ExitCode::Success, $this->command('account:author sam sam', ['unused'])->exitCode, 'A profile that exists isn\'t made again.');
	}

	public function testRefusesAnUnknownRoleBeforeAskingForAPassword(): void
	{
		$result = $this->command('account:add sam --email=sam@example.test --role=boss', ['unused']);

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('There\'s no "boss" role', $result->errors);
		$this->assertStringNotContainsString('Password:', $result->output);
	}

	public function testNeedsATerminalForPasswords(): void
	{
		$result = $this->command('account:add jane --email=jane@example.test -n');

		$this->assertSame(ExitCode::Invalid, $result->exitCode);
		$this->assertStringContainsString('the console is not interactive', $result->errors);
	}

	public function testListsAndChangesAccounts(): void
	{
		$this->assertStringContainsString('No accounts yet.', $this->command('account:list')->output);

		$this->command('account:add jane --email=jane@example.test --role=author', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Failure, $this->command('account:roles jane --role=editor --role=gone')->exitCode);
		$this->assertSame(ExitCode::Success, $this->command('account:roles jane --role=editor')->exitCode);
		$this->assertSame(ExitCode::Success, $this->command('account:author jane jane', ['Jane'])->exitCode);
		$this->assertSame(ExitCode::Success, $this->command('account:password jane', ['another long password', 'another long password'])->exitCode);

		$list = $this->command('account:list')->output;

		$this->assertMatchesRegularExpression('/jane\s*\|\s*\|\s*jane@example\.test\s*\|\s*editor\s*\|\s*jane\s*\|\s*active\s*\|\s*never/', $list);
		$this->assertTrue(new Passwords()->verify('another long password', $this->store()->find('jane')->passwordHash ?? ''));

		$this->assertSame(ExitCode::Success, $this->command('account:author jane')->exitCode);
		$this->assertNull($this->store()->find('jane')?->profile);
	}

	public function testNamesAnAccount(): void
	{
		$this->command(['account:add', 'jane', '--email=jane@example.test', '--name=  Jane   Doe '], [self::PASSWORD, self::PASSWORD]);

		$this->assertSame('Jane Doe', $this->store()->find('jane')?->name);

		$this->assertSame(ExitCode::Success, $this->command(['account:name', 'jane', 'Jane Q. Doe'])->exitCode);
		$this->assertSame('Jane Q. Doe', $this->store()->find('jane')?->name);
		$this->assertMatchesRegularExpression('/jane\s*\|\s*Jane Q\. Doe\s*\|/', $this->command('account:list')->output);

		$this->assertSame(ExitCode::Failure, $this->command(['account:name', 'jane', str_repeat('a', 101)])->exitCode);
		$this->assertSame(ExitCode::Failure, $this->command(['account:name', 'nobody', 'Nobody'])->exitCode);

		$result = $this->command('account:name jane');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertNull($this->store()->find('jane')?->name);
		$this->assertStringContainsString('the admin calls it "jane"', $result->output);
	}

	public function testSetsAnAccountsEmail(): void
	{
		$this->command('account:add jane --email=jane@example.test', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Success, $this->command('account:email jane jane@new.example')->exitCode);
		$this->assertSame('jane@new.example', $this->store()->find('jane')?->email);
		$this->assertSame(ExitCode::Failure, $this->command('account:email jane nope')->exitCode, 'D-370');
		$this->assertNotSame(ExitCode::Success, $this->command('account:add sam -n', [])->exitCode, 'An account needs an email address.');
		$this->assertNull($this->store()->find('sam'));
	}

	public function testSuspendsAndReinstatesAnAccount(): void
	{
		$this->command('account:add jane --email=jane@example.test', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Success, $this->command('account:suspend jane')->exitCode);
		$this->assertTrue($this->store()->find('jane')?->suspended);
		$this->assertMatchesRegularExpression('/jane\s*\|.*\|\s*suspended\s*\|/', $this->command('account:list')->output);
		$this->assertStringContainsString('already suspended', $this->command('account:suspend jane')->output);

		$this->assertSame(ExitCode::Success, $this->command('account:reinstate jane')->exitCode);
		$this->assertFalse($this->store()->find('jane')?->suspended);
		$this->assertSame(ExitCode::Failure, $this->command('account:reinstate nobody')->exitCode);
	}

	public function testRemovesAnAccountAfterAsking(): void
	{
		$this->command('account:add jane --email=jane@example.test', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Failure, $this->command('account:remove jane', ['no'])->exitCode);
		$this->assertNotNull($this->store()->find('jane'));

		$this->assertSame(ExitCode::Success, $this->command('account:remove jane --yes')->exitCode);
		$this->assertNull($this->store()->find('jane'));
		$this->assertSame(ExitCode::Failure, $this->command('account:remove jane --yes')->exitCode);
	}
}
