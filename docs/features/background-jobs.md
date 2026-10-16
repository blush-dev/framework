# Background jobs

A job is work done outside the request that asked for it: an import, a
sync with another service, anything that might outlast a page load.
Jobs are queued and run by
[cron, a worker, page visits, or the admin](../running/cron-and-jobs.md).
To run one on a timetable, see [Scheduled tasks](scheduled-tasks.md).

## Writing a job

Extend `Blush\Job\Job`:

```php
namespace Acme\Shop;

use Blush\Job\Job;
use Blush\Job\JobRecord;
use Blush\Job\JobResult;

final class SyncOrders extends Job
{
	public function __construct(private readonly Orders $orders)
	{}

	public function label(): string
	{
		return 'Sync orders';
	}

	public function handle(JobRecord $job): JobResult
	{
		return JobResult::done(sprintf('Fetched %d orders.', $this->orders->sync()));
	}
}
```

Register it under a `vendor/name` key from your plugin's
[provider's](../plugins/service-providers.md) `boot()`, and queue it
from anywhere with the queue:

```php
use Blush\Job\JobQueue;
use Blush\Job\JobRegistry;

$this->container->get(JobRegistry::class)->register('acme/sync-orders', SyncOrders::class);

$queue->push('acme/sync-orders', ['since' => '2026-10-01'], account: $account->id);
```

- **Data** is what the job works on: ids and plain values (strings,
  numbers, booleans, and arrays of them), never objects, since it's
  saved until a runner picks it up. Read it from `$job->data`.
- **`unique:`** keeps a second copy out while one waits or runs, such
  as `unique: 'sync-orders'`. Pushing again returns the one already
  there. **`delay:`** holds a job back that many seconds.
- **`account:`** records who asked, by the account's `id`. Check their
  capability before you push; the job runs as the site. A person can
  follow a job they queued in the admin.

## Long work, a chunk at a time

On shared hosting, a request may get only 30 seconds, so a job does what
it can and hands back where it got to with `JobResult::more()`, and it's
queued again to carry on:

```php
public function handle(JobRecord $job): JobResult
{
	$offset = (int) ($job->data['offset'] ?? 0);
	$total  = $this->orders->count();

	$this->orders->syncBatch($offset, 50);

	return $offset + 50 >= $total
		? JobResult::done("Synced {$total} orders.")
		: JobResult::more(['offset' => $offset + 50], intdiv(($offset + 50) * 100, $total), 'Syncing…');
}
```

The second argument to `more()` is how far along it is (0 to 100), which
the admin shows as a progress bar.

## When it fails

Throw when trying again might work (a service is down): the job is tried
again after a minute, then five, and failed after three attempts.
Override `attempts()` and `backoff()` to change that. Return
`JobResult::failed('Why.')` when trying again won't help; it's failed at
once. Failed jobs can be retried from the admin's Tools screen or with
`jobs:retry`.

## Testing code that queues jobs

To check what your code queued without running it, bind
`Blush\Job\Testing\RecordingQueue` in place of the queue. It refuses
what the real queue refuses (unregistered jobs, data that isn't plain
values) and keeps `unique` keys the same way:

```php
use Blush\Job\JobQueue;
use Blush\Job\JobRegistry;
use Blush\Job\Testing\RecordingQueue;
use Psr\Clock\ClockInterface;

$queue = new RecordingQueue($container->get(JobRegistry::class), $container->get(ClockInterface::class));
$container->instance(JobQueue::class, $queue);

// … run your code …

$this->assertCount(1, $queue->pushed('acme/sync-orders'));
```

To run jobs as they're queued instead, use the `sync` runner in
`config/jobs.php` (see [Configuration](../reference/configuration/README.md)).
