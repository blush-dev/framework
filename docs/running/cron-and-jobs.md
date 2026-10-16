# Cron and background jobs

Some work happens in the background, outside the page anyone is
waiting on: putting scheduled posts live, cleaning out expired cache
entries and idle admin sessions, and longer work someone starts, such as
**Publish** and **Reindex content** on the admin's Tools screen. Plugins
can add their own (see [Background jobs](../features/background-jobs.md)
and [Scheduled tasks](../features/scheduled-tasks.md)).

## Scheduled posts

Give an entry a future `date` and it goes live at that time,
with no extra step: the first request after that time updates the site.
On a quiet site, a visitor might see the new post a little late; cron
(below) puts it live on time.

## Running jobs from cron

Blush runs this work best from cron. Add this line to your server's
crontab (`crontab -e`), with your site's folder:

```sh
* * * * * cd /path/to/site && php bin/blush schedule:run > /dev/null 2>&1
```

Every minute, it queues the tasks that are due and works through the
queue for up to 50 seconds. Hosts with a control panel usually have a
"Cron Jobs" page where the same command goes, set to run every minute.

## Without cron

**Without cron**, jobs still run, just not on time:

- After a page is served, on servers running PHP-FPM (most hosts), Blush
  works for up to ten seconds, at most once a minute, but only while
  cron hasn't run lately. The visitor never waits: the page is sent
  first.
- In the admin, the jobs you start run while you watch, with their
  progress.

## A worker

**On a server you control**, you can keep a worker running instead of
cron, with systemd or Supervisor:

```sh
bin/blush jobs:work
```

Restart it after updating Blush or your plugins.

## Seeing what ran

The admin's **Tools → Jobs** screen (for administrators and owners) lists
recent jobs, with **Retry** for failed ones and a screen for each with
its whole error, and, on its **Schedule** tab, the scheduled tasks, with
**Run Now**. It warns when cron isn't running, and shows the line
to add. **Site Health** warns too, and so does `bin/blush doctor`.

From the command line:

```sh
bin/blush schedule:list   # the scheduled tasks, and when each runner last ran
bin/blush jobs:list       # recent jobs
bin/blush jobs:retry --all
```

A job that fails is tried again a minute later, then five minutes
after that, and only then marked failed. Finished jobs are kept a day
(failed ones a week) and then removed. See
[Background jobs configuration](../reference/configuration/jobs.md)
to change these.
