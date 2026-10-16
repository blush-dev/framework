# The API

Turning on the read and write API, where it's served, and who may use it.

`config/api.php` · `Blush\Api\ApiConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `false` | Serve the [API](../../features/api/README.md) |
| `path` | `'/api'` | Where it's served, its version added after it: `/api` serves `/api/v1` |
| `maxAge` | `60` | How many seconds browsers and caches may keep an answer |
| `access` | `ApiAccess::Anyone` | Who may read it: anyone, or `ApiAccess::Token` for requests with an [API token](../../features/api/tokens.md) only |
| `maxWrites` | `600` | How many [changes](../../features/api/changing-content.md) a token may make in an hour |

Off, every address under the API is the site's own "not found" page.
`enabled` and `access` can also be changed in the admin, on **Settings →
API**, which shows `path` and `maxWrites`.
