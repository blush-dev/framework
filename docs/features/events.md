# Events

Blush announces what it's doing through events you can listen for, so
a plugin can act when content changes, a page renders, or a backup is
made.

## Listening

Listen in your plugin's [provider's](../plugins/service-providers.md)
`boot()`:

```php
use Blush\Content\Events\ContentIndexed;
use Blush\Event\Listener\ListenerRegistry;

public function boot(): void
{
	$this->container->get(ListenerRegistry::class)->listen(ContentIndexed::class, Acme\Hello\PingSearchEngines::class);
}
```

A listener is an invokable class that takes the event.

## Blush's events

| Event | When |
|---|---|
| `Blush\View\Events\PageRendering` | A page is about to render (see [Scripts and styles](scripts-and-styles.md)) |
| `Blush\Content\Events\ContentIndexed` | Content changed |
| `Blush\Content\Events\EntriesWentLive` | Scheduled entries went live (below) |
| `Blush\Content\Events\EntriesExpired` | Entries expired (below) |
| `Blush\Publish\Events\ContentPublished` | Content was published |
| `Blush\Cache\Events\CacheCleared` | The cache store was emptied by `cache:clear`, the admin's Clear caches, a publish, or `cache:compile`; its `namespaces` say which, a good moment to purge a CDN |
| `Blush\Backup\Events\BackupMade` | A backup was made (below) |
| `Blush\Workflow\StatusChanging`, `StatusChanged` | An entry's status changes (see [Workflows](workflows.md)) |

### Scheduled entries going live

`EntriesWentLive` comes from the [scheduled task](scheduled-tasks.md)
that puts scheduled entries live, every minute with cron. Its `entries`
are the entries that were scheduled when it last looked and are
published now, so you can send a webhook, purge a CDN, or post to a
social site when a scheduled post goes live. An entry published as
it's saved isn't in it. Without cron, the next runner catches up, so
`since` (when it last looked) can be long before `at`; check an entry's
`date` if you only want fresh news.

### Entries expiring

`EntriesExpired` comes from the same task: its `entries` are those that
expired since it last looked (their `expires` date came, in a type with
expiry; see [Status and visibility](../concepts/status-and-visibility.md)),
with the same `since` and `at`.

### Backups

`BackupMade` is dispatched once a [backup](../running/backups.md) is
made and kept, however it was made: by hand, on the schedule, on the
command line, or before a restore (`$event->backup->kind` says which),
with the path of its `.zip` as `$event->file`. Not for an uploaded one.

It's the place to copy backups off the server (to S3, SFTP, and the
like), so they outlive it. It runs in the job or command that made the
backup, so queue a [job](background-jobs.md) of your own for a slow
copy. A backup holds password hashes: copy it somewhere as private as
the server.
