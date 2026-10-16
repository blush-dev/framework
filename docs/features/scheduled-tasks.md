# Scheduled tasks

A scheduled task is a [background job](background-jobs.md) queued on a
timetable. Schedule a job you've registered from your plugin's
[provider's](../plugins/service-providers.md) `boot()`:

```php
use Blush\Job\Frequency;
use Blush\Job\Schedule;

$this->container->get(Schedule::class)->add('acme/sync-orders', Frequency::hourly());
```

`Frequency` has:

- `everyMinute()` and `everyMinutes(15)`;
- `hourly(30)` (at half past);
- `daily('03:00')`;
- `weekly(1, '06:00')` (Mondays; 0 is Sunday);
- `cron('0 9 1 * *')` for any cron expression.

Times are in the site's time zone. A run missed while nothing came by
(no cron, a quiet site) happens once, when a runner next comes by (see
[Cron and jobs](../running/cron-and-jobs.md)).

`add()` again replaces a job's place on the schedule, and `remove()`
takes it off, Blush's own tasks included (`core/go-live`,
`core/prune-cache`, `core/prune-sessions`, `core/prune-jobs`).
