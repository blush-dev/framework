# Backups

Making, listing, deleting, and restoring backups. See
[Backups](../../running/backups.md).

| Command | What it does |
|---|---|
| `backup:make` | Back up the site's stored data to `storage/backups/site/`: every part, or the ones `--parts=` names, separated by commas (`backup:list --parts` lists them). `--note=` keeps a note with it, up to 60 characters. It runs start to end here, not as a job, and refuses while another backup is being made. |
| `backup:list` | List the backups, newest first: each one's name, when it was made, how (`manual`, `scheduled`, `before-restore`, or `uploaded`), what it holds, its size, and its note, or why it failed. `--parts` lists the parts a backup can hold instead, with how many records and bytes each holds now. |
| `backup:delete <name>` | Delete a backup by its name, or cancel the one being made. It waits a day in `storage/backups/site/.deleted/` before it's removed for good. |
| `backup:restore <name>` | Restore parts of a backup: every part it holds but `accounts`, `roles`, and `access`, or the ones `--parts=` names. It says what refuses it (a newer Blush, a part kept by another storage driver now, a backup or restore running) and what it will do (restoring `accounts` signs everyone out), and asks first unless `--yes`. The site's parts are backed up first, and it names that backup, to restore to undo it. Content and media are reindexed and caches cleared after. See [Restoring backups](../../running/restoring-backups.md). |
