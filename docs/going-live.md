# Going live

Blush runs as a PHP site on your host, where you publish changes as you
make them.

## Before you launch

In the live site's `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL="https://example.com"
```

Then check your content:

```sh
bin/blush content:lint
```

## Publishing changes

In development, Blush notices changed files by itself. **In production it
doesn't**, because checking every file on every request would slow the
site down. Instead, after you upload new or changed content, publish it:

```sh
bin/blush publish
```

That finds what changed, updates the content index, refreshes redirects
and content types, and clears the caches, so visitors see the new content
right away.

> **A note on PHP's opcache.** Blush stores its index as PHP files, which
> PHP keeps in memory. After `bin/blush publish`, the web server can take
> a couple of seconds to notice the new files. If your host sets
> `opcache.validate_timestamps=0`, it won't notice until PHP restarts; use
> the [webhook](#publishing-without-a-shell) there instead, which publishes
> from inside the web server.

### Publishing with git

If `user/` is its own git repository, Blush can pull the latest content
first:

```sh
bin/blush publish --pull
```

To always pull, set `PUBLISH_GIT=true` in `.env` (and `PUBLISH_REMOTE` and
`PUBLISH_BRANCH` if you need them). Then `--no-pull` skips it once.

The pull updates only the `user/` repository. Themes, plugins, and icon
packs live in `extensions/`, outside it, so publishing content never
deploys code. Update those the way you deploy the rest of your site.

### Publishing without a shell

Many shared hosts don't offer SSH. Blush has a publish **webhook** for
that: a URL you call after uploading, which runs the same steps as
`bin/blush publish`.

1. Turn it on with `bin/blush init --webhook`, which adds a random
   secret to `.env`. Or set one of at least 32 random characters
   yourself:

   ```ini
   PUBLISH_SECRET="paste-a-long-random-string-here-000000"
   ```

2. Send a signed `POST` to `https://example.com/_blush/publish`. The
   signature proves the request came from you:

   ```sh
   ts=$(date +%s); body='{}'
   sig=$(printf '%s.%s' "$ts" "$body" | openssl dgst -sha256 -hmac "$PUBLISH_SECRET" -r | cut -d' ' -f1)
   curl -X POST \
   	-H "X-Publish-Timestamp: $ts" \
   	-H "X-Publish-Signature: sha256=$sig" \
   	-d "$body" https://example.com/_blush/publish
   ```

Git hosts such as GitHub can call it for you when you push, from a CI
job. The webhook doesn't exist at all until you set a secret, and each
signed request works only once, within five minutes. After 10 badly
signed requests from one address, that address gets `429 Too Many
Requests` for 15 minutes, even with a good signature (`maxAttempts` and
`lockout` in [`config/publish.php`](configuration.md#publishing)).

## Speed it up

These are optional, but worth doing on a live site. Run them after each
deploy of code, config, or themes:

```sh
bin/blush cache:compile   # precompile config, routes, content types, and more
bin/blush theme:publish   # let the web server serve theme files directly
bin/blush media:publish   # let the web server serve media directly
```

After compiling, changes to `config/` or `.env` take effect only when you
run `cache:compile` (or `cache:clear`) again.

### Caching

Caching is on by default outside development. Blush caches rendered
pages, so most visits skip rendering entirely, and answers repeat visits
with `304 Not Modified`. Publishing clears the caches, so you never need
to think about stale pages.

To clear everything by hand:

```sh
bin/blush cache:clear
```

See [Configuration](configuration.md#caching) to change where cached data
is kept or to turn the page cache off.

## Large sites: SQLite

A site keeps everything in files by default, which suits most sites. A
site with many thousands of entries outgrows that: Blush keeps an index
of every file, and at about 10,000 entries reading it needs more memory
than PHP allows by default (128 MB). Such a site keeps its data in a
SQLite database instead, one file, `user/site.sqlite`. PHP needs its
`pdo_sqlite` extension (`bin/blush doctor` says whether it has it).

To move a site:

```sh
bin/blush content:ids --write                     # every entry needs an id
php -d memory_limit=1G bin/blush storage:copy     # files into SQLite
```

Then set `STORAGE_DRIVER=sqlite` in `.env` (or `driver` in
`config/storage.php`). Everyone signs in again. Your files stay where they
were; the site no longer reads them, so keep them as a backup or remove
them.

On SQLite:

- **Edit in the admin** (or create with `content:new`). There are no
  content files to edit, and `content:index` has nothing to do.
- **Back up `user/site.sqlite`**, with your media. Publishing with git
  carries code and media, not content.
- **Run `bin/blush storage:sync` after each deploy**, beside
  `cache:compile`.
- Tools for files (`content:ids`, `content:filenames`, `content:folders`,
  `content:refs`, `content:terms`, `content:parents`) aren't offered,
  and Site Health checks only what a database can get wrong.
- A tag, category, or author has to be an entry before an entry can name
  it; create it first.

## Scheduled posts

Give an entry a future `published` date and it goes live at that time,
with no extra step: the first request after that time updates the site.
On a quiet site, a visitor might see the new post a little late; cron
(below) puts it live on time.

## Background jobs and cron

Some work happens in the background, outside the page anyone is
waiting on: putting scheduled posts live, cleaning out expired cache
entries and idle admin sessions, and longer work someone starts, such as
**Publish** and **Reindex content** on the admin's Tools screen. Plugins
can add their own.

Blush runs this work best from cron. Add this line to your server's
crontab (`crontab -e`), with your site's folder:

```sh
* * * * * cd /path/to/site && php bin/blush schedule:run > /dev/null 2>&1
```

Every minute, it queues the tasks that are due and works through the
queue for up to 50 seconds. Hosts with a control panel usually have a
"Cron Jobs" page where the same command goes, set to run every minute.

**Without cron**, jobs still run, just not on time:

- After a page is served, on servers running PHP-FPM (most hosts), Blush
  works for up to ten seconds, at most once a minute, but only while
  cron hasn't run lately. The visitor never waits: the page is sent
  first.
- In the admin, the jobs you start run while you watch, with their
  progress.

**On a server you control**, you can keep a worker running instead of
cron, with systemd or Supervisor:

```sh
bin/blush jobs:work
```

Restart it after updating Blush or your plugins.

### Seeing what ran

The admin's **Tools → Jobs** tab (for administrators and owners) lists
the scheduled tasks, with **Run Now**, and recent jobs, with **Retry**
for failed ones. It warns when cron isn't running, and shows the line
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
[Configuration](configuration.md#background-jobs) to change these.
