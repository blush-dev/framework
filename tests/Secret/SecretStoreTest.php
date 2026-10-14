<?php

/**
 * Secret store tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Secret;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\ListSecrets;
use Blush\Console\Commands\RemoveSecret;
use Blush\Console\Commands\RotateSecrets;
use Blush\Console\Commands\SetSecret;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Secret\InvalidSecret;
use Blush\Secret\Secret;
use Blush\Secret\SecretDefinition;
use Blush\Secret\SecretKeys;
use Blush\Secret\SecretLocked;
use Blush\Secret\SecretMissing;
use Blush\Secret\Secrets;
use Blush\Secret\SecretScope;
use Blush\Secret\SecretStatus;
use Blush\Secret\SecretStore;
use Blush\Secret\SecretUnreadable;
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
use Blush\Setup\SiteChecks;
use Blush\Tests\BootsScratchSite;

#[CoversClass(SecretStore::class)]
#[CoversClass(Secrets::class)]
#[CoversClass(SecretScope::class)]
#[CoversClass(ListSecrets::class)]
#[CoversClass(SetSecret::class)]
#[CoversClass(RemoveSecret::class)]
#[CoversClass(RotateSecrets::class)]
#[CoversClass(SiteChecks::class)]
final class SecretStoreTest extends TestCase
{
	use BootsScratchSite;

	private const string TOKEN = 'ya29.a0-refresh-token';

	/**
	 * Boots a site with `acme/analytics`'s two secrets declared.
	 *
	 * @param array<string, string> $environment
	 */
	private function site(array $environment = []): Application
	{
		$app = $this->scratchApplication(['APP_ENV' => 'development', ...$environment]);
		$app->boot();

		$secrets = $app->container()->make(Secrets::class);
		$secrets->define(new SecretDefinition('acme/analytics', 'ga_refresh_token', 'Google refresh token', required: true));
		$secrets->define(new SecretDefinition('acme/analytics', 'property', env: 'GA_PROPERTY'));

		return $app;
	}

	private function scope(Application $app): SecretScope
	{
		return $app->container()->make(Secrets::class)->for('acme/analytics');
	}

	/**
	 * @param string|list<string> $command
	 * @param list<string>        $answers
	 */
	private function command(Application $app, string|array $command, array $answers = []): CommandResult
	{
		return new CommandTester($app->container()->make(Console::class))->run($command, $answers);
	}

	/**
	 * @return array<string, array{array<string, string>, string}>
	 */
	public static function drivers(): array
	{
		return [
			'files'  => [[], 'storage/secrets.json'],
			'sqlite' => [['STORAGE_DRIVER' => 'sqlite'], 'storage/database/site.sqlite']
		];
	}

	/**
	 * @param array<string, string> $environment
	 */
	#[DataProvider('drivers')]
	public function testStoresSecretsEncryptedInStorage(array $environment, string $file): void
	{
		$app   = $this->site(['SECRETS_KEY' => SecretKeys::generate(), ...$environment]);
		$scope = $this->scope($app);

		$this->assertSame(SecretStatus::Missing, $scope->status('ga_refresh_token'));
		$this->assertNull($scope->get('ga_refresh_token'));
		$this->assertFalse($scope->has('ga_refresh_token'));

		$scope->set('ga_refresh_token', self::TOKEN, 'account-id');

		$this->assertSame(SecretStatus::Stored, $scope->status('ga_refresh_token'));
		$this->assertSame(self::TOKEN, $scope->require('ga_refresh_token')->reveal());
		$this->assertFileExists($this->temporaryDirectory() . "/{$file}");
		$this->assertStringNotContainsString(self::TOKEN, (string) file_get_contents($this->temporaryDirectory() . "/{$file}"), 'Only ciphertext is stored.');

		$stored = $app->container()->make(SecretStore::class)->all()['acme/analytics.ga_refresh_token'] ?? null;

		$this->assertSame('account-id', $stored?->by);
		$this->assertNotNull($stored->updated);

		$updated = $scope->update('ga_refresh_token', static fn (?Secret $now): string => ($now?->reveal() ?? '') . '-next');

		$this->assertSame(self::TOKEN . '-next', $updated->reveal());
		$this->assertSame(self::TOKEN . '-next', $scope->require('ga_refresh_token')->reveal());

		$scope->remove('ga_refresh_token');

		$this->assertSame(SecretStatus::Missing, $scope->status('ga_refresh_token'));
	}

	public function testOnFilesTheTableIsOneFileOnlyItsOwnerAndGroupRead(): void
	{
		$this->scope($this->site(['SECRETS_KEY' => SecretKeys::generate()]))->set('ga_refresh_token', self::TOKEN);

		$file = $this->temporaryDirectory() . '/storage/secrets.json';
		$json = json_decode((string) file_get_contents($file), true);

		$record = is_array($json) && is_array($json['secrets'] ?? null) && is_array($json['secrets'][0] ?? null) ? $json['secrets'][0] : [];

		$this->assertSame(0660, fileperms($file) & 0777);
		$this->assertSame('acme__analytics.ga_refresh_token', $record['name'] ?? null);
		$this->assertSame('acme/analytics', $record['owner'] ?? null);
		$this->assertMatchesRegularExpression('/\Av1:[0-9a-f]{8}:/', is_string($record['value'] ?? null) ? $record['value'] : '');
	}

	public function testTheEnvironmentWinsAndCantBeStoredOver(): void
	{
		$scope = $this->scope($this->site(['SECRETS_KEY' => SecretKeys::generate(), 'GA_PROPERTY' => 'properties/123', 'ACME_ANALYTICS_GA_REFRESH_TOKEN' => '']));

		$this->assertSame(SecretStatus::Environment, $scope->status('property'));
		$this->assertSame('properties/123', $scope->require('property')->reveal());
		$this->assertSame(SecretStatus::Missing, $scope->status('ga_refresh_token'), 'An empty variable isn\'t set.');

		$this->expectException(SecretLocked::class);
		$scope->set('property', 'properties/456');
	}

	public function testRefusesWhatWasntDeclaredOrCantBeStored(): void
	{
		$app   = $this->site();
		$scope = $this->scope($app);

		$calls = [
			'undeclared' => [InvalidSecret::class, fn () => $scope->get('typo')],
			'other owner' => [InvalidSecret::class, fn () => $app->container()->make(Secrets::class)->for('acme/other')->get('ga_refresh_token')],
			'bad owner'  => [InvalidSecret::class, fn () => $app->container()->make(Secrets::class)->for('nobody')],
			'missing'    => [SecretMissing::class, fn () => $scope->require('ga_refresh_token')],
			'no key'     => [InvalidSecret::class, fn () => $scope->set('ga_refresh_token', self::TOKEN)]
		];

		foreach ($calls as $case => [$class, $call]) {
			try {
				$call();
				$this->fail("{$case} should throw.");
			} catch (InvalidSecret | SecretMissing $e) {
				$this->assertInstanceOf($class, $e, $case);
			}
		}
	}

	public function testRotatesToANewKeyKeepingThePreviousToRead(): void
	{
		$old = SecretKeys::generate();
		$new = SecretKeys::generate();

		$this->scope($this->site(['SECRETS_KEY' => $old]))->set('ga_refresh_token', self::TOKEN);

		$this->assertSame(SecretStatus::Unreadable, $this->scope($this->site(['SECRETS_KEY' => $new]))->status('ga_refresh_token'), 'Unreadable, never missing.');

		$app    = $this->site(['SECRETS_KEY' => $new, 'SECRETS_KEY_PREVIOUS' => $old]);
		$result = $this->command($app, 'secrets:rotate');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Encrypted 1 stored secret again', $result->output);
		$this->assertStringContainsString('Every stored secret is already under the current key.', $this->command($app, 'secrets:rotate')->output);

		$scope = $this->scope($this->site(['SECRETS_KEY' => $new]));

		$this->assertSame(self::TOKEN, $scope->require('ga_refresh_token')->reveal());

		$this->expectException(SecretUnreadable::class);
		$this->scope($this->site(['SECRETS_KEY' => $old]))->get('ga_refresh_token');
	}

	public function testCommandsSetListAndRemoveWithoutShowingValues(): void
	{
		$app = $this->site(['SECRETS_KEY' => SecretKeys::generate(), 'GA_PROPERTY' => 'properties/123']);
		$set = $this->command($app, 'secrets:set acme/analytics.ga_refresh_token', [self::TOKEN]);

		$this->assertSame(ExitCode::Success, $set->exitCode, $set->errors);
		$this->assertStringNotContainsString(self::TOKEN, $set->output);
		$this->assertSame(self::TOKEN, $this->scope($app)->require('ga_refresh_token')->reveal());

		$this->assertSame(ExitCode::Failure, $this->command($app, 'secrets:set acme/analytics.property', ['properties/456'])->exitCode, 'Set in the environment.');
		$this->assertSame(ExitCode::Failure, $this->command($app, 'secrets:set acme/analytics.typo', ['x'])->exitCode);

		$list = $this->command($app, 'secrets:list');

		$this->assertMatchesRegularExpression('#acme/analytics\.ga_refresh_token\s+stored\s+ACME_ANALYTICS_GA_REFRESH_TOKEN#', $list->output);
		$this->assertMatchesRegularExpression('#acme/analytics\.property\s+environment\s+GA_PROPERTY#', $list->output);
		$this->assertStringNotContainsString(self::TOKEN, $list->output);
		$this->assertStringNotContainsString('properties/123', $list->output);

		$remove = $this->command($app, 'secrets:remove acme/analytics.ga_refresh_token');

		$this->assertSame(ExitCode::Success, $remove->exitCode, $remove->errors);
		$this->assertSame(SecretStatus::Missing, $this->scope($app)->status('ga_refresh_token'));
		$this->assertStringContainsString('missing (required)', $this->command($app, 'secrets:list')->output);
	}

	public function testChecksReportTheKeyAndProblemsButNeverValues(): void
	{
		$key = SecretKeys::generate();

		$this->scope($this->site(['SECRETS_KEY' => $key]))->set('ga_refresh_token', self::TOKEN);

		$app = $this->site(['SECRETS_KEY' => $key]);
		$app->container()->make(SecretStore::class)->set(new SecretDefinition('acme/gone', 'token'), 'left behind');

		$this->assertSame([CheckStatus::Pass, CheckStatus::Warning], self::statuses($app->container()->make(SiteChecks::class)->secrets()), 'A key, the required secret stored, and acme/gone left behind.');

		$fresh  = $this->site(['SECRETS_KEY' => SecretKeys::generate()]);
		$checks = $fresh->container()->make(SiteChecks::class)->secrets();

		$this->assertSame([CheckStatus::Pass, CheckStatus::Failure, CheckStatus::Warning], self::statuses($checks), 'Its value won\'t decrypt, and acme/gone isn\'t declared.');
		$this->assertSame('Secret acme/analytics.ga_refresh_token', (string) $checks[1]->label);
		$this->assertSame('Secret acme/gone.token', (string) $checks[2]->label);

		$none = $this->site(['SECRETS_KEY' => 'short']);

		$this->assertSame(CheckStatus::Failure, $none->container()->make(SiteChecks::class)->secrets()[0]->status);
	}

	/**
	 * @param  list<CheckResult> $checks
	 * @return list<CheckStatus>
	 */
	private static function statuses(array $checks): array
	{
		return array_map(static fn (CheckResult $check): CheckStatus => $check->status, $checks);
	}
}
