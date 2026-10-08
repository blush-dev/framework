<?php

/**
 * Job tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Job;

use DateTimeImmutable;
use DateTimeZone;
use stdClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Blush\Clock\FrozenClock;
use Blush\Config\InvalidConfig;
use Blush\Console\Commands\ListJobs;
use Blush\Console\Commands\ListSchedule;
use Blush\Console\Commands\PruneJobs;
use Blush\Console\Commands\RetryJobs;
use Blush\Console\Commands\WorkJobs;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Job\FileJobStore;
use Blush\Job\Frequency;
use Blush\Job\JobConfig;
use Blush\Job\JobException;
use Blush\Job\JobFactory;
use Blush\Job\JobQueue;
use Blush\Job\JobRecord;
use Blush\Job\JobRegistrar;
use Blush\Job\JobRegistry;
use Blush\Job\JobResult;
use Blush\Job\JobRunner;
use Blush\Job\JobServiceProvider;
use Blush\Job\JobStatus;
use Blush\Job\JobStore;
use Blush\Job\RunnerKind;
use Blush\Job\Schedule;
use Blush\Job\Scheduler;
use Blush\Job\StoredJobQueue;
use Blush\Job\Testing\RecordingQueue;
use Blush\Job\UnknownJob;
use Blush\Job\WorkReport;
use Blush\Support\RegistrationException;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Job\ChunkedJob;
use Blush\Tests\Fixtures\Job\RefusingJob;
use Blush\Tests\Fixtures\Job\ThrowingJob;

#[CoversClass(FileJobStore::class)]
#[CoversClass(JobConfig::class)]
#[CoversClass(JobFactory::class)]
#[CoversClass(StoredJobQueue::class)]
#[CoversClass(RecordingQueue::class)]
#[CoversClass(JobRecord::class)]
#[CoversClass(JobRegistrar::class)]
#[CoversClass(JobRegistry::class)]
#[CoversClass(JobResult::class)]
#[CoversClass(JobRunner::class)]
#[CoversClass(JobServiceProvider::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(Scheduler::class)]
#[CoversClass(WorkReport::class)]
#[CoversClass(ListJobs::class)]
#[CoversClass(ListSchedule::class)]
#[CoversClass(PruneJobs::class)]
#[CoversClass(RetryJobs::class)]
#[CoversClass(WorkJobs::class)]
final class JobTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	private FrozenClock $clock;

	/**
	 * Boots a scratch site with the fixture jobs registered.
	 */
	private function boot(string $config = ''): void
	{
		if ($config !== '') {
			$this->writeTemporaryFile('config/jobs.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Job\\JobConfig({$config});\n");
		}

		$this->app   = $this->scratchApplication(['APP_ENV' => 'production', 'APP_TIMEZONE' => 'America/Chicago']);
		$this->clock = new FrozenClock(new DateTimeImmutable('2026-06-01 12:00:00', new DateTimeZone('America/Chicago')));
		$this->app->container()->instance(ClockInterface::class, $this->clock);
		$this->app->boot();

		$registry = $this->app->container()->make(JobRegistry::class);
		$registry->register('test/count', ChunkedJob::class);
		$registry->register('test/throw', ThrowingJob::class);
		$registry->register('test/refuse', RefusingJob::class);
	}

	private function queue(): JobQueue
	{
		return $this->app->container()->make(JobQueue::class);
	}

	private function runner(): JobRunner
	{
		return $this->app->container()->make(JobRunner::class);
	}

	private function command(string $command): CommandResult
	{
		return new CommandTester($this->app->container()->make(Console::class))->run($command);
	}

	public function testWorksAJobAChunkAtATime(): void
	{
		$this->boot();

		$queued = $this->queue()->push('test/count', ['total' => 3], account: 'jane');

		$this->assertSame(JobStatus::Queued, $queued->status);
		$this->assertSame('jane', $queued->account);

		$first = $this->runner()->run($queued);

		$this->assertNotNull($first);
		$this->assertSame(JobStatus::Queued, $first->status, 'There was more, so it was queued again.');
		$this->assertSame(['done' => 1, 'total' => 3], $first->data);
		$this->assertSame(33, $first->progress);
		$this->assertSame('Counted 1 of 3.', $first->message);

		$report = $this->runner()->work(RunnerKind::Cron, 5);

		$this->assertSame(2, $report->count());
		$this->assertSame(1, $report->count(JobStatus::Done));

		$done = $this->queue()->find($queued->id);

		$this->assertNotNull($done);
		$this->assertSame(JobStatus::Done, $done->status, 'A job someone queued is kept when it\'s done.');
		$this->assertSame(100, $done->progress);
		$this->assertSame('Counted to 3.', $done->message);
		$this->assertSame($this->clock->now()->getTimestamp(), $done->finished);
		$this->assertNull($this->runner()->run($done), 'A finished job doesn\'t run again.');
	}

	public function testRetriesAThrowingJobAfterABackoffThenFailsIt(): void
	{
		$this->boot();

		$job   = $this->queue()->push('test/throw');
		$first = $this->runner()->run($job);

		$this->assertNotNull($first);
		$this->assertSame(JobStatus::Queued, $first->status);
		$this->assertSame(1, $first->attempts);
		$this->assertSame('The service is down.', $first->error);
		$this->assertSame($this->clock->now()->getTimestamp() + 60, $first->available);
		$this->assertSame(0, $this->runner()->work(RunnerKind::Cron, 5)->count(), 'It waits out its backoff.');

		$this->clock->advance('PT61S');

		$last = $this->runner()->run($first);

		$this->assertNotNull($last);
		$this->assertSame(JobStatus::Failed, $last->status, 'Out of attempts.');
		$this->assertSame(2, $last->attempts);

		$retried = $this->queue()->retry($last);

		$this->assertNotNull($retried);
		$this->assertSame(JobStatus::Queued, $retried->status);
		$this->assertSame(0, $retried->attempts);
		$this->assertNull($retried->error);
		$this->assertNull($this->queue()->retry($retried), 'Only a failed job is retried.');
	}

	public function testFailsAJobThatRefusesWithoutRetrying(): void
	{
		$this->boot();

		$job = $this->runner()->run($this->queue()->push('test/refuse'));

		$this->assertNotNull($job);
		$this->assertSame(JobStatus::Failed, $job->status);
		$this->assertSame(0, $job->attempts);
		$this->assertSame('Nothing to do it with.', $job->message);
		$this->assertSame(['a detail'], $job->details);
	}

	public function testFailsAJobNobodyRegisteredAnyMore(): void
	{
		$this->boot();

		$job = $this->queue()->push('test/count');
		$this->app->container()->make(JobRegistry::class)->unregister('test/count');

		$run = $this->runner()->run($job);

		$this->assertNotNull($run);
		$this->assertSame(JobStatus::Failed, $run->status);
		$this->assertSame('No job is registered as "test/count".', $run->error);
	}

	public function testQueuesAUniqueJobOnce(): void
	{
		$this->boot();

		$first  = $this->queue()->push('test/count', unique: 'count');
		$second = $this->queue()->push('test/count', unique: 'count');

		$this->assertSame($first->id, $second->id);
		$this->assertCount(1, $this->queue()->all());

		$this->runner()->work(RunnerKind::Cron, 5);

		$this->assertNotSame($first->id, $this->queue()->push('test/count', unique: 'count')->id, 'Once it\'s done, it can be queued again.');
	}

	public function testRefusesUnknownJobsAndDataThatIsNotPlain(): void
	{
		$this->boot();

		try {
			$this->queue()->push('test/nothing');
			$this->fail('An unknown job is refused.');
		} catch (UnknownJob $e) {
			$this->assertSame('No job is registered as "test/nothing".', $e->getMessage());
		}

		$this->expectException(JobException::class);
		$this->expectExceptionMessage('must be ids and plain values');

		$this->queue()->push('test/count', ['entry' => new stdClass()]);
	}

	public function testRefusesAKeyThatIsNotVendorAndName(): void
	{
		$this->boot();

		$this->expectException(RegistrationException::class);

		$this->app->container()->make(JobRegistry::class)->register('count', ChunkedJob::class);
	}

	public function testTwoRunnersNeverClaimOneJob(): void
	{
		$this->boot();

		$store = $this->app->container()->make(JobStore::class);
		$job   = $this->queue()->push('test/count');

		$this->assertNotNull($store->claim($job, $job));
		$this->assertNull($store->claim($job, $job), 'The second claim finds it gone.');
		$this->assertSame(JobStatus::Running, $store->find($job->id)?->status);
		$this->assertNull($this->runner()->run($job), 'A job another runner has isn\'t run.');
	}

	public function testRecoversAJobWhoseRunnerStopped(): void
	{
		$this->boot();

		$store = $this->app->container()->make(JobStore::class);
		$job   = $this->queue()->push('test/count');

		$store->claim($job, $job->with(['started' => $this->clock->now()->getTimestamp()]));

		$this->assertSame(0, $this->runner()->recover(), 'A job running a while isn\'t taken for dead.');

		$this->clock->advance('PT901S');

		$this->assertSame(1, $this->runner()->recover());

		$recovered = $store->find($job->id);

		$this->assertSame(JobStatus::Queued, $recovered?->status);
		$this->assertSame(1, $recovered->attempts);
		$this->assertStringContainsString('stopped without finishing', $recovered->error ?? '');
	}

	public function testPrunesFinishedJobsPastTheirKeep(): void
	{
		$this->boot();

		$done   = $this->runner()->finish($this->queue()->push('test/count', account: 'jane'));
		$failed = $this->runner()->finish($this->queue()->push('test/refuse', account: 'jane'));

		$this->assertSame(JobStatus::Done, $done->status);
		$this->assertSame(JobStatus::Failed, $failed->status);
		$this->assertSame(0, $this->queue()->prune());

		$this->clock->advance('P2D');

		$this->assertSame(1, $this->queue()->prune(), 'Done jobs go after a day.');
		$this->assertNull($this->queue()->find($done->id));

		$this->clock->advance('P6D');

		$this->assertSame(1, $this->queue()->prune(), 'Failed jobs go after a week.');
		$this->assertSame([], $this->queue()->all());
	}

	public function testDeletesOnlyJobsThatAreNotRunning(): void
	{
		$this->boot();

		$store = $this->app->container()->make(JobStore::class);
		$job   = $this->queue()->push('test/count');
		$other = $this->queue()->push('test/count');

		$running = $store->claim($job, $job);

		$this->assertNotNull($running);
		$this->assertFalse($this->queue()->delete($running));
		$this->assertTrue($this->queue()->delete($other));
		$this->assertNull($this->queue()->find($other->id));
	}

	public function testRunsJobsWhenTheyAreQueuedInSyncMode(): void
	{
		$this->boot('runner: Blush\\Job\\RunnerMode::Sync');

		$job = $this->queue()->push('test/count', ['total' => 4], account: 'jane');

		$this->assertSame(JobStatus::Done, $job->status);
		$this->assertSame('Counted to 4.', $job->message);
	}

	public function testSchedulesCoreTasksAndQueuesThemWhenDue(): void
	{
		$this->boot();

		$schedule  = $this->app->container()->make(Schedule::class);
		$scheduler = $this->app->container()->make(Scheduler::class);

		$this->assertSame(['blush/go-live', 'blush/prune-cache', 'blush/prune-sessions', 'blush/prune-jobs'], array_keys($schedule->all()));

		$schedule->add('test/count', Frequency::daily('03:00'));

		$this->assertCount(5, $scheduler->tick(), 'Every task that never ran is due.');
		$this->assertSame([], $scheduler->tick(), 'Nothing is due again within the minute.');

		$report = $this->runner()->work(RunnerKind::Cron, 5);

		$this->assertSame(5, $report->count(JobStatus::Done));
		$this->assertSame([], $this->queue()->all(), 'Scheduled jobs aren\'t kept once they\'re done.');

		$this->clock->advance('PT1M');

		$this->assertSame(['blush/go-live'], array_map(static fn (JobRecord $job): string => $job->job, $scheduler->tick()));

		$task = $schedule->get('test/count');

		$this->assertNotNull($task);
		$this->assertSame('2026-06-01 12:00', $scheduler->lastRun('test/count')?->format('Y-m-d H:i'));
		$this->assertSame('2026-06-02 03:00', $scheduler->nextRun($task)?->format('Y-m-d H:i'));

		$this->clock->set('2026-06-03 09:00:00 America/Chicago');

		$this->assertContains('test/count', array_map(static fn (JobRecord $job): string => $job->job, $scheduler->tick()), 'A missed run happens once, when a runner comes by.');
	}

	public function testRunsAScheduledTaskNowForSomeone(): void
	{
		$this->boot();

		$scheduler = $this->app->container()->make(Scheduler::class);
		$job       = $scheduler->runNow('blush/prune-jobs', 'jane');

		$this->assertNotNull($job);
		$this->assertSame('jane', $job->account);
		$this->assertNull($scheduler->runNow('test/count'), 'It isn\'t on the schedule.');

		$done = $this->runner()->finish($job);

		$this->assertSame(JobStatus::Done, $done->status);
		$this->assertNotNull($this->queue()->find($job->id), 'A scheduled job someone ran is kept for them.');
	}

	public function testRecordsWhenEachRunnerRan(): void
	{
		$this->boot();

		$this->assertNull($this->runner()->lastDependableRun());

		$this->runner()->work(RunnerKind::Admin, 1);

		$this->assertNull($this->runner()->lastDependableRun(), 'The admin isn\'t cron.');

		$this->runner()->work(RunnerKind::Cron, 1);

		$this->assertSame($this->clock->now()->getTimestamp(), $this->runner()->lastDependableRun());
		$this->assertSame($this->clock->now()->getTimestamp(), $this->runner()->lastRuns()['admin']);
		$this->assertNull($this->runner()->lastRuns()['web']);
	}

	public function testARecordingQueueKeepsWhatIsPushedAndRunsNothing(): void
	{
		$this->boot();

		$container = $this->app->container();
		$queue     = new RecordingQueue($container->make(JobRegistry::class), $this->clock);

		$container->instance(JobQueue::class, $queue);

		$first = $container->make(Scheduler::class)->runNow('blush/prune-jobs', 'jane');
		$again = $queue->push('blush/prune-jobs', unique: Scheduler::UNIQUE . 'blush/prune-jobs');

		$queue->push('test/count', ['total' => 2]);

		$this->assertSame($first?->id, $again->id, 'A unique key turns a second copy away.');
		$this->assertCount(1, $queue->pushed('blush/prune-jobs'));
		$this->assertCount(2, $queue->pushed());
		$this->assertSame(JobStatus::Queued, $queue->find($again->id)?->status, 'Nothing ran.');
		$this->assertSame([], $container->make(JobStore::class)->all(), 'Nothing was stored.');

		try {
			$queue->push('test/nothing');
			$this->fail('An unknown job is refused, as the site would.');
		} catch (UnknownJob) {
		}

		$queue->clear();

		$this->assertSame([], $queue->pushed());
		$this->assertSame([], $queue->all());
	}

	public function testRejectsAConfigBelowOneSecond(): void
	{
		$this->expectException(InvalidConfig::class);

		new JobConfig(budget: 0);
	}

	public function testListsRetriesWorksAndPrunesFromTheCommandLine(): void
	{
		$this->boot();

		$failed = $this->runner()->finish($this->queue()->push('test/refuse', account: 'jane'));
		$this->queue()->push('test/count', ['total' => 2]);

		$list = $this->command('jobs:list');

		$this->assertSame(ExitCode::Success, $list->exitCode);
		$this->assertStringContainsString($failed->id, $list->output);
		$this->assertStringContainsString('Nothing to do it with.', $list->output);
		$this->assertStringContainsString('jane', $list->output);
		$this->assertStringNotContainsString('Refuse', $this->command('jobs:list --status=queued')->output);
		$this->assertSame(ExitCode::Invalid, $this->command('jobs:list --status=lost')->exitCode);

		$this->assertSame(ExitCode::Invalid, $this->command('jobs:retry')->exitCode);
		$this->assertStringContainsString('Queued 1 job again.', $this->command("jobs:retry {$failed->id}")->output);

		$work = $this->command('jobs:work --stop-when-empty');

		$this->assertSame(ExitCode::Success, $work->exitCode);
		$this->assertStringContainsString('Ran', $work->output);
		$this->assertSame([], $this->queue()->all(JobStatus::Queued));

		$schedule = $this->command('schedule:list');

		$this->assertStringContainsString('blush/prune-jobs', $schedule->output);
		$this->assertStringContainsString('Daily at 03:00', $schedule->output);
		$this->assertStringContainsString('Worker (jobs:work):', $schedule->output);

		$this->clock->advance('P8D');

		$this->assertStringContainsString('Removed 2 finished jobs.', $this->command('jobs:prune')->output);
	}
}
