# Caching

Whether caching is on, where cached data lives, and page caching.

`config/cache.php` · `Blush\Cache\CacheConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | Off in development, on elsewhere | Turn caching on or off |
| `driver` | `'file'` | Where cached data lives: `file`, `php`, `apcu`, `array`, or `null` |
| `stores` | `[]` | A different driver per cache, such as `['pages' => 'apcu']` |
| `pages` | `true` | Cache whole pages |
| `maxAge` | `0` | How long browsers may keep a page, in seconds |

See [Caching](../../running/caching.md).
