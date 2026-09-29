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
use Blush\Auth\AccountStore;
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

	private function store(): AccountStore
	{
		return $this->app()->container()->make(AccountStore::class);
	}

	public function testAddsAnAdministratorByDefault(): void
	{
		$result = $this->command('account:add jane', ['short', 'mismatched password', 'something else', self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Passwords must be at least 12 characters.', $result->errors);
		$this->assertStringContainsString('Those didn\'t match; try again.', $result->errors);
		$this->assertStringContainsString('Created the "jane" account (administrator).', $result->output);
		$this->assertSame(['administrator'], $this->store()->find('jane')?->roles);
	}

	public function testAddsAnAccountWithRolesAndAnAuthor(): void
	{
		$result = $this->command('account:add sam --role=editor --role=author --author=sam', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('No "sam" author exists yet', $result->errors . $result->output);
		$this->assertSame(['editor', 'author'], $this->store()->find('sam')?->roles);
		$this->assertSame('sam', $this->store()->find('sam')->author);
	}

	public function testRefusesAnUnknownRoleBeforeAskingForAPassword(): void
	{
		$result = $this->command('account:add sam --role=boss', ['unused']);

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('There\'s no "boss" role', $result->errors);
		$this->assertStringNotContainsString('Password:', $result->output);
	}

	public function testNeedsATerminalForPasswords(): void
	{
		$result = $this->command('account:add jane -n');

		$this->assertSame(ExitCode::Invalid, $result->exitCode);
		$this->assertStringContainsString('the console is not interactive', $result->errors);
	}

	public function testListsAndChangesAccounts(): void
	{
		$this->assertStringContainsString('No accounts yet.', $this->command('account:list')->output);

		$this->command('account:add jane --role=author', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Failure, $this->command('account:roles jane --role=editor --role=gone')->exitCode);
		$this->assertSame(ExitCode::Success, $this->command('account:roles jane --role=editor')->exitCode);
		$this->assertSame(ExitCode::Success, $this->command('account:author jane jane')->exitCode);
		$this->assertSame(ExitCode::Success, $this->command('account:password jane', ['another long password', 'another long password'])->exitCode);

		$list = $this->command('account:list')->output;

		$this->assertMatchesRegularExpression('/jane\s*\|\s*editor\s*\|\s*jane\s*\|\s*never/', $list);
		$this->assertTrue(new Passwords()->verify('another long password', $this->store()->find('jane')->passwordHash ?? ''));

		$this->assertSame(ExitCode::Success, $this->command('account:author jane')->exitCode);
		$this->assertNull($this->store()->find('jane')?->author);
	}

	public function testRemovesAnAccountAfterAsking(): void
	{
		$this->command('account:add jane', [self::PASSWORD, self::PASSWORD]);

		$this->assertSame(ExitCode::Failure, $this->command('account:remove jane', ['no'])->exitCode);
		$this->assertNotNull($this->store()->find('jane'));

		$this->assertSame(ExitCode::Success, $this->command('account:remove jane --yes')->exitCode);
		$this->assertNull($this->store()->find('jane'));
		$this->assertSame(ExitCode::Failure, $this->command('account:remove jane --yes')->exitCode);
	}
}
