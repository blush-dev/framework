# Background jobs

Running scheduled tasks and background jobs. See
[Cron and background jobs](../../running/cron-and-jobs.md) for setting
up cron.

| Command | What it does |
|---|---|
| `schedule:run` | For cron, every minute: queue the scheduled tasks that are due (putting scheduled posts live, pruning the cache, idle admin sessions, and old jobs), then run queued jobs for up to 50 seconds. `--budget=` changes the seconds. Fails when a job failed for good. `-v` lists each job it ran. |
| `schedule:list` | List the scheduled tasks, how often each runs, and when it last ran and runs next; then when cron, a worker, page visits, and the admin last ran jobs |
| `jobs:work` | Keep running jobs and scheduled tasks until stopped, for a server that keeps a process going (then cron isn't needed). `--sleep=` sets the seconds to rest when there's nothing to do (3), `--max-time=` stops after that many seconds, and `--stop-when-empty` stops once the queue is empty. Restart it after updating code. |
| `jobs:list` | List the background jobs, newest first, with their ids, status, who queued them, and what they last said. `--status=` shows one: `queued`, `running`, `done`, or `failed`. |
| `jobs:retry <id>` | Queue a failed job again. `--all` queues every failed job. |
| `jobs:prune` | Remove finished jobs kept past their time (a day for done, a week for failed). A scheduled task does this daily. |
