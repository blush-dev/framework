# Backups

A backup is one `.zip` of what makes your site your site: its content,
content types, fields, relationships, and media details; its settings,
redirects, menus, workflows, jobs, and activity; and its accounts, roles,
and API access. You make backups from the admin or the command line, and
they're kept on your server in `storage/backups/site/`, which is never
served.

Backups hold password hashes. Download the ones that matter to somewhere
private, and don't email them.

To put a backup back, see [Restoring a backup](restoring-backups.md). To
have backups made for you, see [Scheduled backups](scheduled-backups.md).

## What's in a backup

A backup is made of **parts**, and you can back up some of them or all:

| Group | Parts |
|---|---|
| Content | Content (entries and how they're linked), Types, Fields, Relationships, Media Details (titles, alt text, and captions) |
| Site | Settings, Redirects, Menus, Workflows, Jobs (finished jobs and the schedule's state), Activity |
| Users | Accounts, Roles, API Access (API tokens and connected apps) |
| Plugins | A part for each plugin that adds one, and **Other** for anything a plugin keeps without one |

Some things are never backed up:

- **Media files.** They're plain files in `user/media`: copy that folder
  as you would any files. Their details (alt text, captions) are in the
  backup.
- **Sessions**, so restoring never brings back a sign-in that ended.
- **Secrets** (mail passwords, API keys). They're encrypted with your
  site's key, so a copy is no use without it; enter them again after
  moving to a new server.
- Caches, the content index, logs, extensions, and `config/`.

A backup keeps your data the way your site keeps it. On files, the
`.zip` holds the files themselves at their paths (under `filesystem/`),
so you can open it and read your content. On [SQLite](sqlite.md), it
holds a copy of the database (`sqlite/site.sqlite`) with only the parts
you picked. Each backup also has a `backup.json` that says when it was
made, by whom, with which version of Blush (`blushVersion`), what it
holds, and the version of its own layout (`formatVersion`).

## Backing up from the admin

With the `site.backups` capability (owners, by default), go to
**Tools → Backups**. Its **Backups** tab shows when the last backup
was made, when the next is, and the space they take, then the list of
backups, newest first; its **Schedule** tab sets
[backups to be made for you](scheduled-backups.md).

1. Choose **Back Up Now**.
2. Leave every part ticked, or pick some. Each group (Content, Site,
   Users) is its own table, and the box beside its name picks or clears
   the group. Each part shows how many records it holds and its size. Before changing a theme or a plugin, Settings on its own is
   often enough.
3. Add a note if you like, such as "Before switching themes" (up to 60
   characters), then choose **Back Up**.

The backup appears at the top of the list with a bar showing the part
it's copying. It runs as a [background job](cron-and-jobs.md),
and the admin runs it while the tab is open, so it finishes even without
cron. **Cancel** stops it, keeping nothing. One backup is made at a time.

Each backup's menu has **Download**, **Add a note** (or **Edit note**),
and **Delete**. A backup's note shows under its date and file name. To delete several,
tick them (the box in the list's header ticks them all) and choose
**Delete** in the bar that appears. Deleting can be undone from the toast; a deleted backup
waits a day in `storage/backups/site/.deleted/` before it's removed for
good. A backup that failed says why on its row; delete it once you've
fixed the cause.

## Backing up from the command line

```sh
bin/blush backup:make                          # every part
bin/blush backup:make --parts=settings --note=Before-the-update
bin/blush backup:list                          # every backup
bin/blush backup:list --parts                  # the parts, and what each holds now
bin/blush backup:delete blush-2026-10-16-0300
bin/blush backup:restore blush-2026-10-16-0300 --parts=content,settings
```

A backup made or restored on the command line runs start to end there,
not as a job. `backup:restore` restores every part the backup holds but
Accounts, Roles, and API Access unless `--parts` names them, asks first
(`--yes` doesn't), and says which backup to restore to undo it.
See [the backup commands](../reference/cli/backups.md).

## Where backups are kept

`storage/backups/site/` unless `config/backups.php` names another folder
(see [Backups configuration](../reference/configuration/backups.md)).
Each backup is a `.zip` named for when it was made, such as
`blush-2026-10-16-0300.zip`, and a `.json` beside it with its note and
status. A `.zip` you copy into the folder by hand is listed too.

## Uploading a backup

To bring in a backup from another server or your own computer, choose
**Upload a Backup** on Tools → Backups and drop the `.zip` on the dialog
(or choose it). It must be a backup as Blush made it: a `.zip` with its
`backup.json`, holding nothing a backup doesn't. It joins the list,
marked as uploaded, and nothing about the site changes until you
[restore it](restoring-backups.md). The schedule never removes it.

A backup with the name of one already in the list is refused, as are
files larger than the server takes (the dialog says how large). On the
command line, copy the `.zip` into `storage/backups/site/` instead: it's
listed the next time the list is read.
