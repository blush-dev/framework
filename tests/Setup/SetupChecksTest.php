<?php

/**
 * Setup check tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Setup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Core\Paths;
use Blush\Http\Status;
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
use Blush\Setup\SetupChecks;
use Blush\Setup\SetupPage;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(SetupChecks::class)]
#[CoversClass(CheckResult::class)]
#[CoversClass(SetupPage::class)]
final class SetupChecksTest extends TestCase
{
	use TemporaryDirectory;

	private function checks(): SetupChecks
	{
		return new SetupChecks(Paths::fromRoot($this->temporaryDirectory()));
	}

	/**
	 * @param  list<CheckResult> $results
	 */
	private function find(array $results, string $label): ?CheckResult
	{
		return array_find($results, static fn (CheckResult $result): bool => $result->label === $label);
	}

	public function testMissingStorageFoldersPassWhenTheyCanBeCreated(): void
	{
		$results = $this->checks()->storage();

		$this->assertCount(count(SetupChecks::STORAGE), $results);
		$this->assertSame([], SetupChecks::failures($results));
		$this->assertSame('Created when it\'s needed.', $this->find($results, 'storage/cache/')?->message);
	}

	public function testExistingWritableStoragePasses(): void
	{
		mkdir($this->temporaryDirectory() . '/storage/logs', 0775, true);

		$this->assertSame('Writable.', $this->find($this->checks()->storage(), 'storage/logs/')?->message);
	}

	public function testAFileInPlaceOfStorageFails(): void
	{
		$this->writeTemporaryFile('storage', '');

		$failures = SetupChecks::failures($this->checks()->storage());

		$this->assertSame('storage/', $failures[0]->label ?? null);
		$this->assertSame('This is a file, not a folder.', $failures[0]->message);
		// Folders under it can't be created either.
		$this->assertGreaterThan(1, count($failures));
	}

	public function testUnwritableStorageFails(): void
	{
		$storage = $this->temporaryDirectory() . '/storage';
		mkdir($storage);
		chmod($storage, 0555);

		try {
			if (is_writable($storage)) {
				$this->markTestSkipped('Running as a user who can write anywhere.');
			}

			$result = $this->find($this->checks()->storage(), 'storage/');

			$this->assertSame(CheckStatus::Failure, $result?->status);
			$this->assertStringContainsString('init', $result->hint);
			$this->assertSame(CheckStatus::Failure, $this->find($this->checks()->storage(), 'storage/cache/')?->status);
		} finally {
			chmod($storage, 0775);
		}
	}

	public function testNamesTheStorageDriver(): void
	{
		$files = $this->checks()->driver();

		$this->assertSame('Storage', $files[0]->label);
		$this->assertSame('Files.', $files[0]->message);

		if (! \Blush\Storage\Sql\SqliteConnection::available()) {
			return;
		}

		$sqlite = new SetupChecks(Paths::fromRoot($this->temporaryDirectory()), new \Blush\Storage\StorageConfig('sqlite'))->driver();

		$this->assertSame('SQLite, in user/site.sqlite.', $sqlite[0]->message);
		$this->assertFalse($sqlite[1]->isFailure(), 'Its folder can be written to.');
	}

	public function testChecksPhp(): void
	{
		$results = $this->checks()->php();

		$this->assertSame(CheckStatus::Pass, $this->find($results, 'PHP')?->status);
		$this->assertSame(CheckStatus::Pass, $this->find($results, 'ext-intl')?->status);
	}

	public function testChecksTheSite(): void
	{
		$results = $this->checks()->site(new AppConfig(environment: Environment::Development, debug: true));

		$this->assertSame(CheckStatus::Warning, $this->find($results, '.env')?->status);
		$this->assertSame('development, with debugging on', $this->find($results, 'Environment')?->message);
		$this->assertNull($this->find($results, 'APP_DEBUG'));
		$this->assertNull($this->find($results, 'APP_URL'));
		$this->assertSame(CheckStatus::Failure, $this->find($results, 'public/index.php')?->status);
		$this->assertSame(CheckStatus::Warning, $this->find($results, 'public/.htaccess')?->status);
	}

	public function testFlagsRiskySettingsInProduction(): void
	{
		$this->writeTemporaryFile('.env', '');
		$this->writeTemporaryFile('public/index.php', '<?php');
		$this->writeTemporaryFile('public/.htaccess', '');

		$results = $this->checks()->site(new AppConfig(debug: true));

		$this->assertSame(CheckStatus::Pass, $this->find($results, '.env')?->status);
		$this->assertSame(CheckStatus::Failure, $this->find($results, 'APP_DEBUG')?->status);
		$this->assertSame(CheckStatus::Warning, $this->find($results, 'APP_URL')?->status);
		$this->assertSame(CheckStatus::Pass, $this->find($results, 'public/index.php')?->status);
		$this->assertNull($this->find($results, 'public/.htaccess'));

		$results = $this->checks()->site(new AppConfig(url: 'https://example.com'));

		$this->assertNull($this->find($results, 'APP_DEBUG'));
		$this->assertNull($this->find($results, 'APP_URL'));
	}

	public function testRunsEveryCheck(): void
	{
		$checks = $this->checks();
		$app    = new AppConfig();

		$this->assertCount(
			count($checks->php()) + count($checks->site($app)) + count($checks->storage()) + count($checks->driver()),
			$checks->all($app)
		);
	}

	public function testThePageSaysWhatToFix(): void
	{
		$response = SetupPage::response([CheckResult::failure('storage/', 'Not <writable>.', 'Run "blush init".')]);
		$body     = (string) $response->getBody();

		$this->assertSame(Status::ServiceUnavailable->value, $response->getStatusCode());
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertStringContainsString('<code>storage/</code> Not &lt;writable&gt;.', $body);
		$this->assertStringContainsString('Run &quot;blush init&quot;.', $body);
	}
}
