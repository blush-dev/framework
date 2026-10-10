<?php

/**
 * Exposure check tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Setup;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Core\Paths;
use Blush\Setup\CheckStatus;
use Blush\Setup\ExposureCheck;
use Blush\Setup\ProbeResponse;
use Blush\Setup\UrlProbe;
use Blush\Support\Filesystem;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ExposureCheck::class)]
final class ExposureCheckTest extends TestCase
{
	use TemporaryDirectory;

	private const string URL = 'https://example.test';

	/**
	 * The paths the probe was asked for.
	 *
	 * @var list<string>
	 */
	private array $asked = [];

	protected function setUp(): void
	{
		$this->writeTemporaryFile('.env', "APP_SECRET=abc\n");
		$this->writeTemporaryFile('config/app.php', "<?php\nreturn new AppConfig();\n");
	}

	/**
	 * Builds the check over a probe that answers each path with `$answer`.
	 *
	 * @param Closure(string): ?ProbeResponse $answer
	 */
	private function check(Closure $answer, string $url = self::URL, Environment $environment = Environment::Production): ExposureCheck
	{
		$answer = function (string $path) use ($answer): ?ProbeResponse {
			$this->asked[] = $path;

			return $answer($path);
		};

		$probe = new class ($answer) implements UrlProbe {
			/**
			 * @param Closure(string): ?ProbeResponse $answer
			 */
			public function __construct(
				private Closure $answer
			) {}

			public function get(string $url, int $limit): ?ProbeResponse
			{
				return ($this->answer)((string) parse_url($url, PHP_URL_PATH));
			}
		};

		return new ExposureCheck(Paths::fromRoot($this->temporaryDirectory()), new AppConfig(url: $url, environment: $environment), $probe, new Filesystem());
	}

	public function testFailsWhenTheSiteFolderIsServed(): void
	{
		$root   = $this->temporaryDirectory();
		$result = $this->check(static fn (string $path): ProbeResponse => is_file($root . $path) ? new ProbeResponse(200, (string) file_get_contents($root . $path)) : new ProbeResponse(404, ''))->run();

		$this->assertSame(CheckStatus::Failure, $result->status);
		$this->assertSame('Anyone can download .env, config/app.php, and storage/ from https://example.test.', $result->message);
		$this->assertSame(['/.env', '/config/app.php', '/storage/' . ExposureCheck::SENTINEL], $this->asked);
		$this->assertFileDoesNotExist("{$root}/storage/" . ExposureCheck::SENTINEL, 'The sentinel is removed.');
	}

	public function testPassesWhenEveryPathGetsTheSitesOwnPage(): void
	{
		$result = $this->check(static fn (): ProbeResponse => new ProbeResponse(200, '<!doctype html><title>Home</title>'))->run();

		$this->assertSame(CheckStatus::Pass, $result->status, 'A 200 that isn\'t the file isn\'t the file.');
		$this->assertSame('Not served at https://example.test: .env, config/app.php, and storage/.', $result->message);
	}

	public function testWarnsWhenTheAddressCantBeReached(): void
	{
		$result = $this->check(static fn (): ?ProbeResponse => null)->run();

		$this->assertSame(CheckStatus::Warning, $result->status);
		$this->assertStringStartsWith('Couldn\'t reach https://example.test to check', $result->message);
	}

	public function testRunsOnlyInProductionAtAnAddressOthersCanReach(): void
	{
		$answer = static fn (): ?ProbeResponse => null;

		$this->assertTrue($this->check($answer)->applies());
		$this->assertFalse($this->check($answer, environment: Environment::Development)->applies());

		foreach (['http://localhost', 'http://127.0.0.1:8000', 'https://blush.localhost'] as $url) {
			$this->assertFalse($this->check($answer, $url)->applies(), $url);
		}
	}

	public function testLeavesOutEmptyFiles(): void
	{
		$this->writeTemporaryFile('.env', '');

		$this->check(static fn (): ProbeResponse => new ProbeResponse(404, ''))->run();

		$this->assertNotContains('/.env', $this->asked, 'An empty file can\'t be told from a blank page.');
	}
}
