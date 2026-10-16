# Background jobs

When background jobs run, and how long they and their results are kept.

`config/jobs.php` · `Blush\Job\JobConfig`

| Option | Default | What it does |
|---|---|---|
| `runner` | `RunnerMode::Auto` | When jobs run besides cron and `jobs:work`. `Auto` also runs them after a page is served while cron hasn't run lately; `Cron` never runs them on a visit; `Sync` runs each job as soon as it's queued (for local development and tests). The admin always runs the jobs you start and wait on. |
| `budget` | `50` | How many seconds `schedule:run` works through the queue |
| `webBudget` | `10` | How many seconds of work after a page is served |
| `timeout` | `900` | How many seconds a job may run before it's taken for stopped (its process died) and tried again |
| `keepDone` | `86400` | How many seconds finished jobs are kept, for their results (a day) |
| `keepFailed` | `604800` | How many seconds failed jobs are kept (a week) |

See [Cron and background jobs](../../running/cron-and-jobs.md) for cron,
and [Background jobs](../../features/background-jobs.md) for queueing
your own.
