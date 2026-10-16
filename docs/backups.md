# Backups

A backup is one `.zip` of what makes your site your site: its content,
content types, fields, relationships, and media details; its settings,
redirects, menus, workflows, jobs, and activity; and its accounts, roles,
and API access. You make backups from the admin or the command line, and
they're kept on your server in `storage/backups/site/`, which is never
served.

Backups hold password hashes. Download the ones that matter to somewhere
private, and don't email them.

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
so you can open it and read your content. On SQLite, it holds a copy of
the database (`sqlite/site.sqlite`) with only the parts you picked. Each
backup also has a `backup.json` that says when it was made, by whom,
with which version of Blush (`blushVersion`), what it holds, and the
version of its own layout (`formatVersion`).

## Backing up from the admin

With the `site.backups` capability (owners, by default), go to
**Tools → Backups**. Its **Backups** tab shows when the last backup
was made, when the next is, and the space they take, then the list of
backups, newest first; its **Schedule** tab sets backups to be made for
you.

1. Choose **Back Up Now**.
2. Leave every part ticked, or pick some. Each group (Content, Site,
   Users) is its own table, and the box beside its name picks or clears
   the group. Each part shows how many records it holds and its size. Before changing a theme or a plugin, Settings on its own is
   often enough.
3. Add a note if you like, such as "Before switching themes" (up to 60
   characters), then choose **Back Up**.

The backup appears at the top of the list with a bar showing the part
it's copying. It runs as a [background job](going-live.md#background-jobs-and-cron),
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
See [Command line](cli.md#backups).

## Where backups are kept

`storage/backups/site/` unless `config/backups.php` names another folder
(see [Configuration](configuration.md#backups)). Each backup is a `.zip`
named for when it was made, such as `blush-2026-10-16-0300.zip`, and a
`.json` beside it with its note and status. A `.zip` you copy into the
folder by hand is listed too.

## Restoring a backup

Restoring puts parts of the site back the way a backup has them. It
needs the `site.backups.restore` capability (owners, by default) as well
as `site.backups`.

1. On **Tools → Backups**, open the backup's menu and choose **Restore…**.
2. Pick the parts to restore. Every part the backup holds is ticked but
   Accounts, Roles, and API Access, since restoring them changes who can sign
   in. Parts you leave out stay as they are now.
3. Read what the dialog says, then choose **Restore**.

Before anything is replaced, Blush backs up the parts you picked as they
are now, noted "Before restoring" and the backup's name. The restore
then runs as a background job, with its progress above the list. When
it's done, the toast's **Undo** restores that backup, putting the site
back. That backup stays in the list until you delete it.

What a restore replaces, it replaces whole: a post written since the
backup is gone, and one deleted since is back. Afterwards, content and
media are reindexed and caches cleared.

Some things refuse a restore, and the dialog says which:

- **A newer Blush.** A backup made by a newer version of Blush (or in a
  newer format) can't be restored until you update.
- **Another storage driver.** A part backed up from files can't be
  restored to a site that now keeps it in SQLite, and the other way
  round. Restore it to a site using the old driver, then move it with
  `storage:copy`.
- **A backup or restore already running.** One runs at a time.

And some things are worth knowing first:

- **Restoring Accounts signs everyone out, you included.** Passwords go
  back to what they were when the backup was made, and an account made
  since is gone.
- **Content without its types and relationships** is restored with the
  types and relationships the site has now: an entry of a type that's
  gone won't show.

If a restore stops partway, what it restored stays restored, and the
error names the backup made before it: restore that one to put the site
back.

## Backing up on a schedule

The **Schedule** tab on Tools → Backups makes backups for you, as a
[background job](going-live.md#background-jobs-and-cron) run by cron (or
by page visits, or while the admin is open, when there's no cron).

- **How Often:** Off, Daily, or Weekly on a day you pick.
- **Time:** the hour it runs, in the site's time zone.
- **Keep:** how many of the schedule's backups are kept (7, 14, 30, or
  60). When a new one is made, the oldest goes. Backups you make
  yourself, ones made before a restore, and ones you upload are never
  removed by the schedule.
- **What Each Holds:** the parts each scheduled backup holds. With every
  part ticked, a part a plugin adds later is in it too.
- **Send an Email:** **Only when a backup fails** (the default: one email
  each time one fails, and one more when they work again), **After every
  backup**, or **Never**. It goes to the site's email address (Settings →
  General) unless you give another.

Choose **Save Schedule** to save it. **Next Backup** above the list says
when it runs next.

The email says whether the backup worked, when, its size and what it
holds, and links to Tools → Backups, where you sign in to see it. **It
never attaches the backup or links to a download**: a backup holds
password hashes, and email is no place for them. **Send a Test Email**
checks that the site's mail reaches you, and **Preview the Email** shows
both emails as they'd be sent. Only scheduled backups email; one you make
yourself finishes on the screen.

A scheduled backup due while another backup or a restore is running is
skipped until its next time. When backups are scheduled to email but
the site can't send mail, [Site Health](admin.md) warns, since a failed
backup would otherwise go unnoticed.

Backups are kept on the server they back up. To keep copies somewhere
else, a plugin can copy each one as it's made (see
[Events](extending.md#events)).

The schedule is saved as a setting over `config/backups.php`; see
[Configuration](configuration.md#backups) to set it in code instead.

## Uploading a backup

To bring in a backup from another server or your own computer, choose
**Upload a Backup** on Tools → Backups and drop the `.zip` on the dialog
(or choose it). It must be a backup as Blush made it: a `.zip` with its
`backup.json`, holding nothing a backup doesn't. It joins the list,
marked as uploaded, and nothing about the site changes until you
[restore it](#restoring-a-backup). The schedule never removes it.

A backup with the name of one already in the list is refused, as are
files larger than the server takes (the dialog says how large). On the
command line, copy the `.zip` into `storage/backups/site/` instead: it's
listed the next time the list is read.
