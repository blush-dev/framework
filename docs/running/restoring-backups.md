# Restoring a backup

Restoring puts parts of the site back the way a [backup](backups.md) has
them. It needs the `site.backups.restore` capability (owners, by default)
as well as `site.backups`.

## Restoring from the admin

1. On **Tools → Backups**, open the backup's menu and choose **Restore…**.
2. Pick the parts to restore. Every part the backup holds is ticked but
   Accounts, Roles, and API Access, since restoring them changes who can sign
   in. Parts you leave out stay as they are now.
3. Read what the dialog says, then choose **Restore**.

Before anything is replaced, Blush backs up the parts you picked as they
are now, noted "Before restoring" and the backup's name. The restore
then runs as a [background job](cron-and-jobs.md), with its progress
above the list. When it's done, the toast's **Undo** restores that
backup, putting the site back. That backup stays in the list until you
delete it.

To restore from the command line, use `bin/blush backup:restore` (see
[Backing up from the command line](backups.md#backing-up-from-the-command-line)).

## What a restore replaces

What a restore replaces, it replaces whole: a post written since the
backup is gone, and one deleted since is back. Afterwards, content and
media are reindexed and caches cleared.

## What refuses a restore

Some things refuse a restore, and the dialog says which:

- **A newer Blush.** A backup made by a newer version of Blush (or in a
  newer format) can't be restored until you update.
- **Another storage driver.** A part backed up from files can't be
  restored to a site that now keeps it in [SQLite](sqlite.md), and the
  other way round. Restore it to a site using the old driver, then move
  it with `storage:copy`.
- **A backup or restore already running.** One runs at a time.

## Worth knowing first

- **Restoring Accounts signs everyone out, you included.** Passwords go
  back to what they were when the backup was made, and an account made
  since is gone.
- **Content without its types and relationships** is restored with the
  types and relationships the site has now: an entry of a type that's
  gone won't show.

If a restore stops partway, what it restored stays restored, and the
error names the backup made before it: restore that one to put the site
back.
