<?php

/**
 * Admin jobs tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Admin\ActionController;
use Blush\Admin\FixAccess;
use Blush\Admin\HealthCheckJob;
use Blush\Admin\SiteHealth;
use Blush\Admin\SiteHealthController;
use Blush\Content\Lint\Linter;
use Blush\Admin\HealthFix;
use Blush\Admin\HealthFixJob;
use Blush\Admin\JobController;
use Blush\Job\JobQueue;
use Blush\Job\JobRegistry;
use Blush\Job\JobRunner;
use Blush\Job\RunnerKind;
use Blush\Setup\CheckStatus;
use Blush\Setup\SiteChecks;
use Blush\Tests\Fixtures\Job\RefusingJob;

#[CoversClass(JobController::class)]
#[CoversClass(ActionController::class)]
#[CoversClass(SiteChecks::class)]
#[CoversClass(HealthFixJob::class)]
#[CoversClass(HealthFix::class)]
#[CoversClass(FixAccess::class)]
#[CoversClass(HealthCheckJob::class)]
#[CoversClass(SiteHealth::class)]
#[CoversClass(SiteHealthController::class)]
#[CoversClass(Linter::class)]
final class AdminJobsTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Signs in and returns the CSRF token.
	 */
	private function token(): string
	{
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		return $token;
	}

	public function testSomeoneFollowsTheJobTheyStartedToItsEnd(): void
	{
		$this->boot();
		$token = $this->token();

		$queued = self::json($this->send('POST', '/actions/publish', headers: ['X-CSRF-Token' => $token]));
		$id     = $queued['job'] ?? null;

		$this->assertIsString($id);

		$waiting = self::json($this->send('GET', "/jobs/{$id}"))['job'] ?? null;

		$this->assertIsArray($waiting);
		$this->assertSame('queued', $waiting['status'] ?? null);
		$this->assertSame('jane', $waiting['account'] ?? null);
		$this->assertTrue($waiting['due'] ?? null);

		$this->assertSame(403, $this->send('POST', "/jobs/{$id}/run")->getStatusCode(), 'Running needs the CSRF token.');

		$done = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($done);
		$this->assertSame('done', $done['status'] ?? null);
		$this->assertSame('Publish', $done['label'] ?? null);
		$this->assertSame(100, $done['progress'] ?? null);
		$this->assertIsString($done['message'] ?? null);
		$this->assertStringStartsWith('Published in', $done['message']);
		$this->assertNotNull($this->app->container()->make(JobRunner::class)->lastRuns()['admin'], 'The admin counted as a runner.');

		$this->assertSame(403, $this->send('GET', '/jobs')->getStatusCode(), 'An editor sees their own jobs, not the list.');
	}

	public function testSomeoneElsesJobIsNotTheirs(): void
	{
		$this->boot();

		$job = $this->app->container()->make(JobQueue::class)->push('blush/reindex', account: 'someone');

		$this->token();

		$this->assertSame(403, $this->send('GET', "/jobs/{$job->id}")->getStatusCode());
		$this->assertSame(404, $this->send('GET', '/jobs/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74')->getStatusCode());
	}

	public function testListsAndManagesJobsWithTheCapability(): void
	{
		$this->boot(roles: ['administrator']);
		$this->app->container()->make(JobRegistry::class)->register('test/refuse', RefusingJob::class);

		$queue  = $this->app->container()->make(JobQueue::class);
		$failed = $this->app->container()->make(JobRunner::class)->finish($queue->push('test/refuse'));
		$queued = $queue->push('blush/reindex');
		$token  = $this->token();

		$list = self::json($this->send('GET', '/jobs'));

		$this->assertSame([$queued->id, $failed->id], array_column(is_array($list['jobs'] ?? null) ? $list['jobs'] : [], 'id'), 'Newest first.');
		$this->assertSame(['blush/go-live', 'blush/prune-cache', 'blush/prune-sessions', 'blush/prune-jobs'], array_column(is_array($list['tasks'] ?? null) ? $list['tasks'] : [], 'job'));
		$this->assertSame('auto', $list['mode'] ?? null);
		$this->assertIsString($list['cron'] ?? null);
		$this->assertStringContainsString('schedule:run', $list['cron']);
		$this->assertSame(['cron' => null, 'worker' => null, 'web' => null, 'admin' => null], $list['runners'] ?? null);

		$retried = self::json($this->send('POST', "/jobs/{$failed->id}/retry", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($retried);
		$this->assertSame('queued', $retried['status'] ?? null);
		$this->assertSame(409, $this->send('POST', "/jobs/{$queued->id}/retry", headers: ['X-CSRF-Token' => $token])->getStatusCode(), 'Only a failed job is retried.');

		$this->assertSame(['deleted' => $queued->id], self::json($this->send('DELETE', "/jobs/{$queued->id}", headers: ['X-CSRF-Token' => $token])));

		$now = self::json($this->send('POST', '/jobs/schedule/blush/prune-jobs', headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($now);
		$this->assertSame('blush/prune-jobs', $now['job'] ?? null);
		$this->assertSame('jane', $now['account'] ?? null);
		$this->assertSame(404, $this->send('POST', '/jobs/schedule/test/refuse', headers: ['X-CSRF-Token' => $token])->getStatusCode(), 'It isn\'t on the schedule.');
	}

	public function testASiteHealthFixWorksAChunkAtATime(): void
	{
		for ($n = 1; $n <= HealthFixJob::CHUNK + 5; $n++) {
			$this->writeTemporaryFile(sprintf('user/content/note-%03d.md', $n), "---\ntitle: Note {$n}\n---\n");
		}

		$this->boot(roles: ['owner'], ids: false);
		$token = $this->token();

		$id = self::json($this->send('POST', '/health/ids', '{}', ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsString($id);

		$first = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($first);
		$this->assertSame('queued', $first['status'] ?? null, 'More to do.');
		$this->assertSame(95, $first['progress'] ?? null);
		$this->assertSame('Fixed 100 of 105 files.', $first['message'] ?? null);

		$done = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($done);
		$this->assertSame('done', $done['status'] ?? null);
		$this->assertSame('Changed 105 files.', $done['message'] ?? null);
		$result = $done['result'] ?? null;

		$this->assertIsArray($result);
		$this->assertIsArray($result['assigned'] ?? null);
		$this->assertCount(105, $result['assigned'], 'Every chunk\'s changes, together.');
		$this->assertSame([], $result['failed'] ?? null);
	}

	public function testCheckAgainWorksAChunkAtATime(): void
	{
		for ($n = 1; $n <= HealthCheckJob::CHUNK + 5; $n++) {
			$this->writeTemporaryFile(sprintf('user/content/note-%03d.md', $n), "---\ntitle: Note {$n}\n---\n");
		}

		$this->boot(roles: ['owner']);
		$token = $this->token();

		$first = self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]));

		$this->assertArrayNotHasKey('job', $first, 'With no report yet, the first check is made at once.');
		$this->assertSame(205, $first['checked'] ?? null);

		$this->writeTemporaryFile('user/content/broken.md', "---\ntitle: [unclosed\n---\n");
		$this->writeTemporaryFile('user/data/media/2026/lake.png.yml', "alt: [unclosed\n");

		$site = self::json($this->send('POST', '/health/site', headers: ['X-CSRF-Token' => $token]));
		$id   = $site['job'] ?? null;

		$this->assertIsString($id, 'Site Health checks the files in a job.');
		$this->assertIsArray($site['checks'] ?? null, 'And answers the rest at once.');
		$this->assertSame($id, self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['job'] ?? null, 'One check at a time.');

		$chunk = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($chunk);
		$this->assertSame('queued', $chunk['status'] ?? null);
		$this->assertSame('Checked 200 of 206 content files.', $chunk['message'] ?? null);

		$messages = [];

		do {
			$done = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

			$this->assertIsArray($done);
			$messages[] = $done['message'] ?? null;
		} while (($done['status'] ?? null) === 'queued');

		$this->assertSame('done', $done['status'] ?? null);
		$this->assertSame(['Checked 206 of 206 content files.', 'Checked 1 of 1 media details files.', 'Checked 206 content files and 1 media details file.'], $messages, 'Content, then media details, then what needs every file.');

		$health = self::json($this->send('GET', '/health'));

		$this->assertSame(206, $health['checked'] ?? null);
		$this->assertContains('broken.md', array_column(is_array($health['files'] ?? null) ? $health['files'] : [], 'path'), 'What a chunk found is in the report.');
		$this->assertContains('user/data/media/2026/lake.png.yml', array_column(is_array($health['files'] ?? null) ? $health['files'] : [], 'path'), 'And the media details.');
	}

	public function testAFixStopsWhenItsAccountCanNoLongerMakeIt(): void
	{
		$this->writeTemporaryFile('user/content/note.md', "---\ntitle: Note\n---\n");
		$this->boot(roles: ['owner'], ids: false);

		$job = $this->app->container()->make(JobQueue::class)->push('blush/health-fix', ['fix' => 'ids', 'remaining' => null], 'nobody');
		$run = $this->app->container()->make(JobRunner::class)->finish($job);

		$this->assertSame('failed', $run->status->value);
		$this->assertSame('The account that asked for this fix can\'t make it any more.', $run->message);
	}

	public function testSiteHealthWarnsUntilCronRuns(): void
	{
		$this->boot();

		$checks = $this->app->container()->make(SiteChecks::class);
		$jobs   = $checks->jobs()['jobs'] ?? null;

		$this->assertNotNull($jobs);
		$this->assertSame(CheckStatus::Warning, $jobs->status);
		$this->assertStringContainsString('schedule:run', $jobs->hint);

		$this->app->container()->make(JobRunner::class)->beat(RunnerKind::Cron);

		$this->assertSame(CheckStatus::Pass, ($checks->jobs()['jobs'] ?? null)?->status);
	}
}
