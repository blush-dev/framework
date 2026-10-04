<?php

/**
 * Publishing tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Publish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Cache\PageCache;
use Blush\Config\InvalidConfig;
use Blush\Console\Commands\Publish;
use Blush\Console\Commands\RunSchedule;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Content\ContentRepository;
use Blush\Core\Application;
use Blush\Env\Env;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Publish\Events\ContentPublished;
use Blush\Publish\GitPuller;
use Blush\Publish\PublishConfig;
use Blush\Publish\Publisher;
use Blush\Publish\PublishInProgress;
use Blush\Publish\PublishReport;
use Blush\Publish\PublishRoutes;
use Blush\Publish\PublishServiceProvider;
use Blush\Publish\PullResult;
use Blush\Publish\Puller;
use Blush\Publish\WebhookController;
use Blush\Publish\WebhookSignature;
use Blush\Publish\WebhookThrottle;
use Blush\Routing\RouteCache;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\Fixtures\Publish\RecordingPuller;

#[CoversClass(Publisher::class)]
#[CoversClass(PublishReport::class)]
#[CoversClass(PublishConfig::class)]
#[CoversClass(PublishRoutes::class)]
#[CoversClass(PublishServiceProvider::class)]
#[CoversClass(WebhookController::class)]
#[CoversClass(WebhookSignature::class)]
#[CoversClass(WebhookThrottle::class)]
#[CoversClass(GitPuller::class)]
#[CoversClass(Publish::class)]
#[CoversClass(RunSchedule::class)]
#[CoversClass(ContentPublished::class)]
#[CoversClass(Caches::class)]
final class PublishTest extends TestCase
{
	use BuildsContentSite;

	private const string SECRET = 'a-test-secret-that-is-long-enough-1234';

	private function publishConfig(string $arguments): void
	{
		$this->writeTemporaryFile('config/publish.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Publish\\PublishConfig({$arguments});\n");
	}

	private function get(Application $app, string $uri): ResponseInterface
	{
		return $app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	/**
	 * Sends a webhook request, signed unless `$signature` is given.
	 */
	private function webhook(Application $app, string $body = '', ?string $signature = null, ?int $timestamp = null, string $ip = '203.0.113.5'): ResponseInterface
	{
		$timestamp ??= $this->clock->now()->getTimestamp();
		$signature ??= new WebhookSignature(self::SECRET)->sign($timestamp, $body);

		return $app->container()->make(Kernel::class)->handle(Request::create('/_blush/publish', 'POST', [
			WebhookSignature::TIMESTAMP_HEADER => (string) $timestamp,
			WebhookSignature::SIGNATURE_HEADER => $signature
		], $body, ['REMOTE_ADDR' => $ip]));
	}

	/**
	 * Decodes a JSON response.
	 *
	 * @return array<string, mixed>
	 */
	private function json(ResponseInterface $response): array
	{
		$data = json_decode((string) $response->getBody(), true, 16, JSON_THROW_ON_ERROR);
		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return $data;
	}

	private function git(string $directory, string ...$arguments): void
	{
		$command = ['git', '-C', $directory, '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', ...array_values($arguments)];
		$process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

		$this->assertNotFalse($process);
		$this->assertSame(0, proc_close($process), implode(' ', $arguments));
	}

	public function testPublishingPutsNewContentLive(): void
	{
		$this->standardContent();

		$app = $this->site();

		$this->assertSame('miss', $this->get($app, '/')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('hit', $this->get($app, '/')->getHeaderLine(PageCache::HEADER));

		$this->entry('_posts/2009-01-01.fresh.md', 'title: Fresh Post');

		$published = [];
		$app->container()->make(ListenerRegistry::class)->listen(ContentPublished::class, static function (ContentPublished $event) use (&$published): void {
			$published[] = $event->report;
		});

		$before = $app->container()->make(ContentVersion::class)->current();
		$report = $app->container()->make(Publisher::class)->publish();

		$this->assertTrue($report->isSuccessful());
		$this->assertNull($report->pull);
		$this->assertSame(['_posts/2009-01-01.fresh.md'], $report->index?->added);
		$this->assertSame(['pages', 'bodies', 'fragments'], $report->cleared);
		$this->assertNotSame($before, $report->version);
		$this->assertSame($report->version, $app->container()->make(ContentVersion::class)->current());
		$this->assertCount(1, $published);
		$this->assertSame([], glob($this->temporaryDirectory() . '/storage/cache/store/pages/*/*') ?: []);

		$home = $this->get($this->site(), '/');

		$this->assertSame('miss', $home->getHeaderLine(PageCache::HEADER));
		$this->assertStringContainsString('Fresh Post', (string) $home->getBody());
	}

	public function testPublishingRecompilesTheRouteTable(): void
	{
		$this->standardContent();

		$app = $this->site();
		$app->container()->make(RouteCache::class)->write();

		$this->writeTemporaryFile('user/data/redirects.json', '{"/old": "/about"}');

		$this->assertSame(404, $this->get($this->site(), '/old')->getStatusCode());

		$report = $this->site()->container()->make(Publisher::class)->publish();

		$this->assertTrue($report->routes);
		$this->assertFalse($report->types);
		$this->assertSame('/about', $this->get($this->site(), '/old')->getHeaderLine('Location'));
	}

	public function testAFailedPullPublishesNothing(): void
	{
		$this->standardContent();
		$this->publishConfig('git: true');

		$app     = $this->site();
		$puller  = new RecordingPuller(new PullResult(false, 'fatal: not a git repository'));
		$version = $app->container()->make(ContentVersion::class)->current();

		$app->container()->instance(Puller::class, $puller);

		$report = $app->container()->make(Publisher::class)->publish();

		$this->assertFalse($report->isPublished());
		$this->assertFalse($report->isSuccessful());
		$this->assertSame([$this->temporaryDirectory() . '/user'], $puller->pulled);
		$this->assertNull($report->index);
		$this->assertSame($version, $app->container()->make(ContentVersion::class)->current());
		$this->assertSame(['successful' => false, 'output' => 'fatal: not a git repository'], $report->toArray()['pull']);

		$this->assertTrue($app->container()->make(Publisher::class)->publish(pull: false)->isPublished());
	}

	public function testOnePublishRunsAtATime(): void
	{
		$this->standardContent();

		$app  = $this->site();
		$lock = fopen($this->writeTemporaryFile('storage/cache/publish.lock', ''), 'c');

		$this->assertNotFalse($lock);
		$this->assertTrue(flock($lock, LOCK_EX));

		try {
			$this->expectException(PublishInProgress::class);
			$app->container()->make(Publisher::class)->publish();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	public function testGitPullsTheUserFolder(): void
	{
		$root   = $this->temporaryDirectory();
		$origin = "{$root}/origin.git";
		$author = "{$root}/author";

		mkdir($origin);
		$this->git($origin, 'init', '--bare', '--initial-branch=main');
		$this->git($root, 'clone', $origin, 'author');
		$this->writeTemporaryFile('author/content/index.md', "---\ntitle: Home\n---\n");
		$this->git($author, 'add', '.');
		$this->git($author, 'commit', '-m', 'Home');
		$this->git($author, 'push', 'origin', 'main');
		$this->git($root, 'clone', $origin, 'user');

		$this->publishConfig("git: true, remote: 'origin', branch: 'main'");

		$app = $this->site();
		$this->assertNull($app->container()->make(ContentRepository::class)->named('page', 'about'));

		$this->writeTemporaryFile('author/content/about.md', "---\ntitle: About\n---\n");
		$this->git($author, 'add', '.');
		$this->git($author, 'commit', '-m', 'About');
		$this->git($author, 'push', 'origin', 'main');

		$report = $app->container()->make(Publisher::class)->publish();

		$this->assertTrue($report->isSuccessful(), $report->pull->output ?? '');
		$this->assertTrue($report->pull?->successful);
		$this->assertSame(['about.md'], $report->index?->added);
		$this->assertFileExists("{$root}/user/content/about.md");

		$broken = new GitPuller(new PublishConfig(gitBinary: 'git'))->pull("{$root}/storage");

		$this->assertFalse($broken->successful);
		$this->assertStringContainsString('git', strtolower($broken->output));
		$this->assertFalse(new GitPuller(new PublishConfig())->pull("{$root}/missing")->successful);
		$this->assertFalse(new GitPuller(new PublishConfig(gitBinary: "{$root}/no-such-git"))->pull($root)->successful);
	}

	public function testThePublishCommand(): void
	{
		$this->standardContent();

		$app    = $this->site();
		$puller = new RecordingPuller();

		$app->container()->instance(Puller::class, $puller);

		$tester = new CommandTester($app->container()->make(Console::class));
		$result = $tester->run('publish -v');

		$this->assertTrue($result->isSuccessful(), $result->errors);
		$this->assertStringContainsString('Indexed 17 entries', $result->output);
		$this->assertMatchesRegularExpression('/Published in \d+ ms; the content version is now [0-9a-f]{16}\./', $result->output);
		$this->assertSame([], $puller->pulled);

		$this->assertTrue($tester->run('publish --pull -v')->isSuccessful());
		$this->assertCount(1, $puller->pulled);
		$this->assertSame(ExitCode::Invalid, $tester->run('publish --pull --no-pull')->exitCode);

		$this->entry('_posts/broken.md', "title: [unclosed");
		$broken = $tester->run('publish');

		$this->assertSame(ExitCode::Failure, $broken->exitCode);
		$this->assertStringContainsString('1 file(s) could not be indexed', $broken->errors . $broken->output);

		$app->container()->instance(Puller::class, new RecordingPuller(new PullResult(false, 'fatal: offline')));
		$app->container()->forgetInstance(Publisher::class);

		$failed = $tester->run('publish --pull');

		$this->assertSame(ExitCode::Failure, $failed->exitCode);
		$this->assertStringContainsString('fatal: offline', $failed->output);
	}

	public function testScheduleRun(): void
	{
		$this->standardContent();

		$app    = $this->site();
		$tester = new CommandTester($app->container()->make(Console::class));

		$this->get($app, '/');

		$result = $tester->run('schedule:run');

		$this->assertTrue($result->isSuccessful());
		$this->assertStringContainsString('next go-live: 2026-12-25T08:00:00-06:00. Pruned 0 expired cache entries.', $result->output);

		$this->clock->set('2026-12-26 00:00:00 America/Chicago');

		$this->assertStringContainsString('next go-live: none.', $tester->run('schedule:run')->output);
	}

	public function testTheWebhookIsOffWithoutASecret(): void
	{
		$this->standardContent();

		$app = $this->site();

		$this->assertNotSame(200, $this->webhook($app)->getStatusCode());
		$this->assertFalse($app->container()->make(PublishConfig::class)->hasWebhook());
	}

	public function testTheWebhookPublishesSignedRequests(): void
	{
		$this->standardContent();
		$this->publishConfig("secret: '" . self::SECRET . "', git: true");

		$app    = $this->site();
		$puller = new RecordingPuller();

		$app->container()->instance(Puller::class, $puller);

		$response = $this->webhook($app, '{"pull": false}');
		$report   = $this->json($response);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertTrue($report['published']);
		$this->assertSame([], $puller->pulled);
		$this->assertSame(['pages', 'bodies', 'fragments'], $report['cleared']);

		$this->assertSame(409, $this->webhook($app, '{"pull": false}')->getStatusCode());

		$this->clock->advance('PT1S');
		$this->assertSame(200, $this->webhook($app)->getStatusCode());
		$this->assertSame([$this->temporaryDirectory() . '/user'], $puller->pulled);
	}

	public function testTheWebhookRefusesBadRequests(): void
	{
		$this->standardContent();
		$this->publishConfig("secret: '" . self::SECRET . "'");

		$app = $this->site();
		$now = $this->clock->now()->getTimestamp();

		$this->assertSame(401, $this->webhook($app, '', 'sha256=nope')->getStatusCode());
		$this->assertSame(401, $this->webhook($app, '', '')->getStatusCode());
		$this->assertSame(401, $this->webhook($app, timestamp: $now - 301)->getStatusCode());
		$this->assertSame(401, $this->webhook($app, timestamp: $now + 301)->getStatusCode());
		$this->assertSame(401, $this->webhook($app, '{}', new WebhookSignature(self::SECRET)->sign($now, '{"x":1}'))->getStatusCode());
		$this->assertSame(401, $this->webhook($app, '', new WebhookSignature(str_repeat('x', 32))->sign($now, ''))->getStatusCode());
		$this->assertSame(400, $this->webhook($app, 'not json')->getStatusCode());
		$this->assertSame(400, $this->webhook($app, '"string"')->getStatusCode());
		$this->assertSame(405, $app->container()->make(Kernel::class)->handle(Request::create('/_blush/publish'))->getStatusCode());

		// Seen signatures survive the store clear every publish does.
		$app->container()->make(Caches::class)->clear();
		$this->assertSame(409, $this->webhook($app, 'not json')->getStatusCode());
		$this->assertNotContains(CacheNamespace::Webhooks->value, $app->container()->make(Caches::class)->clear());
	}

	public function testTheWebhookLocksOutAnAddressAfterFailedSignatures(): void
	{
		$this->standardContent();
		$this->publishConfig("secret: '" . self::SECRET . "', maxAttempts: 3, lockout: 600");

		$app = $this->site();

		for ($i = 0; $i < 3; $i++) {
			$this->assertSame(401, $this->webhook($app, '', 'sha256=nope')->getStatusCode());
		}

		// Locked out, even with a good signature; another address isn't.
		$locked = $this->webhook($app);

		$this->assertSame(429, $locked->getStatusCode());
		$this->assertSame('600', $locked->getHeaderLine('Retry-After'));
		$this->assertSame('no-store', $locked->getHeaderLine('Cache-Control'));
		$this->assertSame(200, $this->webhook($app, ip: '203.0.113.6')->getStatusCode());

		// Clearing the caches doesn't lift it; the window running out does.
		$app->container()->make(Caches::class)->clear();
		$this->assertSame(429, $this->webhook($app)->getStatusCode());

		$this->clock->advance('PT601S');
		$this->assertSame(401, $this->webhook($app, '', 'sha256=nope')->getStatusCode());

		// A signed request clears the count; a replayed one doesn't.
		$signed = new WebhookSignature(self::SECRET)->sign($this->clock->now()->getTimestamp(), '');

		$this->assertSame(200, $this->webhook($app, '', $signed)->getStatusCode());

		for ($i = 0; $i < 2; $i++) {
			$this->assertSame(401, $this->webhook($app, '', 'sha256=nope')->getStatusCode());
		}

		$this->assertSame(409, $this->webhook($app, '', $signed)->getStatusCode());
		$this->assertSame(401, $this->webhook($app, '', 'sha256=nope')->getStatusCode());
		$this->assertSame(429, $this->webhook($app)->getStatusCode());
	}

	public function testPublishConfig(): void
	{
		$config = PublishConfig::fromEnv(new Env(['PUBLISH_SECRET' => self::SECRET, 'PUBLISH_GIT' => 'true', 'PUBLISH_REMOTE' => 'origin', 'PUBLISH_BRANCH' => 'main']));

		$this->assertTrue($config->hasWebhook());
		$this->assertSame(['secret' => self::SECRET, 'git' => true, 'remote' => 'origin', 'branch' => 'main', 'path' => '/_blush/publish', 'tolerance' => 300, 'gitBinary' => 'git', 'maxAttempts' => 10, 'lockout' => 900], $config->toArray());
		$this->assertFalse(PublishConfig::fromEnv(new Env(['PUBLISH_SECRET' => '']))->hasWebhook());
		$this->assertSame('/hooks/publish', PublishConfig::fromArray(['path' => 'hooks/publish/'])->path);

		$cases = [
			static fn (): PublishConfig => new PublishConfig(secret: 'short'),
			static fn (): PublishConfig => new PublishConfig(remote: '--upload-pack=evil'),
			static fn (): PublishConfig => new PublishConfig(branch: 'a b'),
			static fn (): PublishConfig => new PublishConfig(path: '/../x'),
			static fn (): PublishConfig => new PublishConfig(tolerance: 0),
			static fn (): PublishConfig => new PublishConfig(maxAttempts: 0),
			static fn (): PublishConfig => new PublishConfig(lockout: 0),
			static fn (): PublishConfig => PublishConfig::fromArray(['unknown' => true])
		];

		foreach ($cases as $case) {
			try {
				$case();
				$this->fail('Invalid config was accepted.');
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}
	}
}
