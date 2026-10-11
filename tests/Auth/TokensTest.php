<?php

/**
 * Token tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Authenticator;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Auth\Token;
use Blush\Auth\Tokens;
use Blush\Clock\FrozenClock;
use Blush\Core\Application;
use Blush\Job\JobRecord;
use Blush\Job\Jobs\PruneTokensJob;
use Blush\Tests\BootsScratchSite;

#[CoversClass(Token::class)]
#[CoversClass(Tokens::class)]
#[CoversClass(Accounts::class)]
#[CoversClass(Authenticator::class)]
#[CoversClass(Permissions::class)]
#[CoversClass(PruneTokensJob::class)]
final class TokensTest extends TestCase
{
	use BootsScratchSite;

	private ?Application $app = null;

	private FrozenClock $clock;

	private function app(): Application
	{
		if ($this->app === null) {
			$this->app   = $this->scratchApplication(['APP_ENV' => 'development']);
			$this->clock = new FrozenClock(new DateTimeImmutable('2026-06-01 12:00:00'));
			$this->app->container()->instance(ClockInterface::class, $this->clock);
			$this->app->boot();
		}

		return $this->app;
	}

	private function tokens(): Tokens
	{
		return $this->app()->container()->make(Tokens::class);
	}

	private function accounts(): Accounts
	{
		return $this->app()->container()->make(Accounts::class);
	}

	private function agent(): Account
	{
		return $this->accounts()->createTokenOnly('agent', ['editor']);
	}

	public function testMakesATokenShownOnceAndKeepsItsHash(): void
	{
		$agent          = $this->agent();
		[$token, $text] = $this->tokens()->make($agent, '  Astro   front end ', ['content.*.edit'], 30, 'maker-id');
		$file           = $this->temporaryDirectory() . "/storage/tokens/{$token->id}.json";

		$this->assertMatchesRegularExpression('/\Ablush_[0-9a-f-]{36}_[0-9a-f]{64}\z/', $text, 'The prefix, so secret scanners know it (D-747).');
		$this->assertSame([$token->id, substr($text, -64)], Tokens::parse($text));
		$this->assertSame('Astro front end', $token->name);
		$this->assertSame($agent->id, $token->account);
		$this->assertSame(strtotime('2026-07-01 12:00:00'), $token->expires);
		$this->assertFileExists($file);
		$this->assertStringNotContainsString(substr($text, -64), (string) file_get_contents($file), 'Only a hash of the secret is kept.');
		$this->assertStringContainsString('"client": null', (string) file_get_contents($file), 'Room for OAuth later (D-747).');
		$this->assertEquals($token, $this->tokens()->verify($text));
		$this->assertNull($this->tokens()->verify(substr($text, 0, -1) . (str_ends_with($text, '0') ? '1' : '0')));
		$this->assertNull($this->tokens()->verify('blush_nope'));
		$this->assertNull(Tokens::parse(str_replace('blush_', 'other_', $text)));
	}

	public function testRefusesWhatATokenCantBe(): void
	{
		$agent = $this->agent();

		foreach ([['', 90], ['Fine', 7]] as [$name, $days]) {
			try {
				$this->tokens()->make($agent, $name, null, $days);
				$this->fail('Refused.');
			} catch (AuthException) {
				$this->addToAssertionCount(1);
			}
		}

		[$never] = $this->tokens()->make($agent, 'Forever', null, null);
		$this->assertNull($never->expires);
	}

	public function testListsRevokesAndPrunes(): void
	{
		$agent      = $this->agent();
		[$old]      = $this->tokens()->make($agent, 'Old', null, 30);
		$this->clock->advance('PT1M');
		[$new]      = $this->tokens()->make($agent, 'New', null, 365);
		[$other]    = $this->tokens()->make($this->accounts()->createTokenOnly('bot', ['author']), 'Other');

		$this->assertSame(['New', 'Old'], array_column($this->tokens()->forAccount($agent), 'name'), 'Newest first.');

		$this->clock->advance('P31D');
		$this->assertTrue($old->isExpired($this->clock->now()->getTimestamp()));
		$this->assertSame(0, $this->tokens()->prune(), 'Expired tokens stay listed for 30 days (D-748).');

		$this->clock->advance('P30D');
		$this->assertSame(1, $this->tokens()->prune());
		$this->assertNull($this->tokens()->find($old->id));

		$this->assertSame(1, $this->tokens()->revokeAll($agent));
		$this->assertNull($this->tokens()->find($new->id));
		$this->assertNotNull($this->tokens()->find($other->id), 'Only that account\'s.');
		$this->assertSame('Removed 0 expired tokens, 0 expired codes and refresh tokens, and 0 apps nobody connected.', $this->app()->container()->make(PruneTokensJob::class)->handle(new JobRecord(id: '0199b6e2-0000-7000-8000-000000000001', job: 'core/prune-tokens'))->message);
	}

	public function testTokenOnlyAccountsNeverSignIn(): void
	{
		$agent = $this->accounts()->createTokenOnly('agent', ['editor'], 'An Agent');

		$this->assertTrue($agent->tokenOnly);
		$this->assertNull($agent->email, 'An email is optional (D-750).');
		$this->assertTrue($this->accounts()->find('agent')?->tokenOnly, 'Kept.');
		$this->assertNull($this->app()->container()->make(Authenticator::class)->attempt('agent', 'anything at all', '203.0.113.5'));

		$refusals = [
			fn (): mixed => $this->accounts()->issuePasswordLink($agent),
			fn (): mixed => $this->accounts()->setPassword($agent, 'a long enough password')
		];

		foreach ($refusals as $refused) {
			try {
				$refused();
				$this->fail('A token-only account has no password.');
			} catch (AuthException $e) {
				$this->assertStringContainsString('token-only', $e->getMessage());
			}
		}

		$this->assertSame('agent@example.test', $this->accounts()->setEmail($agent, 'agent@example.test')->email);
		$this->assertNull($this->accounts()->setEmail($this->accounts()->find('agent') ?? $agent, '')->email, 'And can be taken away again.');
	}

	public function testATokensLimitNarrowsWhatItsAccountMayDo(): void
	{
		$editor      = $this->agent();
		$permissions = $this->app()->container()->make(Permissions::class);
		$limited     = $permissions->limitedTo(['content.*.edit', 'content.post.create']);

		$this->assertTrue($permissions->can($editor, Capability::MenusEdit));
		$this->assertFalse($limited->can($editor, Capability::MenusEdit));
		$this->assertTrue($limited->can($editor, ContentAction::Create, 'post'));
		$this->assertFalse($limited->can($editor, ContentAction::Create, 'page'));
		$this->assertFalse($permissions->limitedTo([])->can($editor, ContentAction::Edit, 'post'));
		$this->assertFalse($limited->can($this->accounts()->createTokenOnly('member', []), ContentAction::Edit, 'post'), 'Never more than the account can.');
		$this->assertTrue($limited->limitedTo(null)->can($editor, Capability::MenusEdit));
		$this->assertContains(Capability::ApiTokens->value, $permissions->capabilities($this->accounts()->createTokenOnly('admin', ['administrator'])), 'The administrator has it by default (D-749).');
		$this->assertNotContains(Capability::ApiTokens->value, $permissions->capabilities($editor), 'The editor doesn\'t.');
	}
}
