<?php

/**
 * Token command tests.
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
use Blush\Auth\Tokens;
use Blush\Console\Commands\AddAccount;
use Blush\Console\Commands\AddToken;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Tests\BootsScratchSite;

#[CoversClass(AddToken::class)]
#[CoversClass(AddAccount::class)]
final class TokenCommandsTest extends TestCase
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

	/**
	 * @param string|list<string> $command
	 * @param list<string>        $answers
	 */
	private function command(string|array $command, array $answers = []): CommandResult
	{
		return new CommandTester($this->app()->container()->make(Console::class))->run($command, $answers);
	}

	public function testAddsATokenOnlyAccount(): void
	{
		$result = $this->command('account:add agent --token-only --role=editor --name=Claude -n');
		$agent  = $this->app()->container()->make(Accounts::class)->find('agent');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertTrue($agent?->tokenOnly);
		$this->assertNull($agent->email, 'Optional (D-750).');
		$this->assertNull($agent->passwordLink);
		$this->assertStringContainsString('Created the token-only account "agent" (Editor).', $result->output);
		$this->assertStringContainsString('token:add agent', $result->output);
	}

	public function testMakesATokenAndPrintsItOnce(): void
	{
		$this->command('account:add agent --token-only --role=editor -n');

		$result = $this->command(['token:add', 'agent', '--name=Astro front end', '--expires=never', '--capabilities=content.*.edit,menus.edit', '-q']);
		$text   = trim($result->output);
		$token  = $this->app()->container()->make(Tokens::class)->verify($text);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertMatchesRegularExpression('/\Ablush_[0-9a-f-]{36}_[0-9a-f]{64}\z/', $text, 'Quiet prints the token alone.');
		$this->assertSame('Astro front end', $token?->name);
		$this->assertNull($token->expires);
		$this->assertSame(['content.*.edit', 'menus.edit'], $token->capabilities);
		$this->assertSame('', $token->createdBy, 'Made on the command line.');

		$asked = $this->command('token:add agent', ['Claude']);

		$this->assertSame(ExitCode::Success, $asked->exitCode, $asked->errors);
		$this->assertStringContainsString('Made "Claude" for agent.', $asked->output);
		$this->assertStringContainsString('shown once', $asked->output);
	}

	public function testRefusesWhatATokenCantBe(): void
	{
		$this->command('account:add agent --token-only --role=editor -n');

		$this->assertSame(ExitCode::Failure, $this->command('token:add nobody --name=X')->exitCode);
		$this->assertStringContainsString('isn\'t a capability', $this->command('token:add agent --name=X --capabilities=nope.nope')->errors);
		$this->assertStringContainsString('30, 90, 365 days', $this->command('token:add agent --name=X --expires=7')->errors);
	}
}
