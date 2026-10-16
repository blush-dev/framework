# Backups

Where backups are kept, and the backup schedule.

`config/backups.php` · `Blush\Backup\BackupConfig`

| Option | Default | What it does |
|---|---|---|
| `path` | `'storage/backups/site'` | The folder [backups](../../running/backups.md) are kept in, from the site's root unless it's absolute. Keep it somewhere the web server doesn't serve. |
| `schedule` | off | When the site backs itself up: `new BackupSchedule(...)` (`Blush\Backup\BackupSchedule`), with `frequency` (`BackupFrequency::Off`, `Daily`, or `Weekly`), `day` (0 for Sunday to 6, for weekly), `hour` (0 to 23, in the site's time zone; `3`), `keep` (how many of its backups are kept, 1 to 365; `14`), `parts` (the part names, or `null` for every part), `notice` (`BackupNotice::Failures`, `Always`, or `Never`), and `to` (an address, or `''` for the site's). Tools → Backups saves it as a setting over this file. |

See [Scheduled backups](../../running/scheduled-backups.md).
