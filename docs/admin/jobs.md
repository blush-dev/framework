# Jobs

With `site.jobs` (administrators), **Tools → Jobs** shows the work done
in the [background](../running/cron-and-jobs.md), in two tabs.

**Jobs** lists recent jobs, newest
first: what each is, who started it, its status, and what it last said,
with **Retry** for a failed job and **Delete**. Filter it by status;
the tab counts the failed jobs while there are some. Choose a job to
open its own screen: its progress while it waits or runs, why it
failed (the whole error), what it said and handed back, when it was
queued, started, and finished, its attempts, and who queued it, with
**Retry** and **Delete**.

**Schedule** lists what runs on a timetable
(putting scheduled posts live, and cleaning out the cache, idle
sessions, and old jobs), how often, when each last ran and runs next,
with **Run Now**.

Above the tabs, a notice says whether cron is
running jobs, and if it isn't, the line to add to your server's crontab.
