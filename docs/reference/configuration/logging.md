# Logging

Where the site's log goes, and how much it keeps.

`config/log.php` · `Blush\Log\LogConfig`

| Option | Default | What it does |
|---|---|---|
| `driver` | `LogDriver::File` | `File`, `Stderr`, or `Null` |
| `level` | `Level::Warning` | The least serious level logged (`Debug` … `Emergency`) |
| `file` | `'site.log'` | The log file, in `storage/logs/` |
| `channel` | `'core'` | The name on each line |
