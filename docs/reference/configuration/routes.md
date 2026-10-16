# Routes and redirects

Trailing slashes, redirects, and routes set in config.

`config/routes.php` · `Blush\Routing\RouteConfig`

| Option | Default | What it does |
|---|---|---|
| `trailingSlash` | `false` | Whether URLs end in `/` (`/about/`). The other form redirects, except for the admin, the publish webhook, preview links, and routes marked `exact` (see [Routes](../../features/routes.md)). |
| `redirects` | `[]` | Redirects, as `new Redirect('/old', '/new')` |
| `routes` | `[]` | Routes of your own (see [Routes](../../features/routes.md)) |
| `controllers` | `[]` | Classes whose attributes declare routes |

Most redirects are easier in `user/data/redirects.json`; see
[Redirects](../../concepts/redirects.md). Routes and controllers usually
come from a plugin's provider instead; see [Routes](../../features/routes.md).
