# Storage

Where the site's data is kept: files, or one SQLite database.

`config/storage.php` · `Blush\Storage\StorageConfig`

| Option | Default | What it does |
|---|---|---|
| `driver` | `'filesystem'` | Where the site's data is kept: `filesystem` (files, as a flat-file site keeps them) or `sqlite` (one SQLite database; see [SQLite](../../running/sqlite.md)) |
| `areas` | `[]` | A different driver for an area: `content`, `data`, `accounts`, `sessions`, `jobs`, or `secrets`, such as `['sessions' => 'filesystem']` |
| `sqlite` | `'storage/database/site.sqlite'` | The SQLite database's file, from the site's folder (or a full path) |

Without this file, `STORAGE_DRIVER` is used. Media files are always
files, whatever the driver.

The saved settings are read before plugins load, so the `data` area's
driver must be one built into Blush (`filesystem` or `sqlite`).
