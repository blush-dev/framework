# Configuration

How a site's settings are kept: in `.env`, in `config/`, and in what the
admin saves. Every option is listed in the
[configuration reference](../reference/configuration/README.md).

Blush has two places for settings:

- **`.env`** holds what differs from one machine to the next: the site's
  URL, whether it's in development or production, and secrets. Never
  commit it. Its variables are listed in
  [`.env`](../reference/configuration/env.md).
- **`config/`** holds how the site works, as PHP files you commit.

Every setting has a sensible default, so you only configure what you want
to change.

Config is for settings. Code (routes, commands, blocks, content types)
belongs in a [plugin](../plugins/README.md).

## Config files

Each file in `config/` returns a settings object. The file names are up to
you; by convention they match what they configure. You can use `$env` to
read `.env` values:

```php
<?php

declare(strict_types=1);

use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Env\Env;

/** @var Env $env */

return new AppConfig(
	name: $env->string('APP_NAME', 'My Site'),
	url: $env->string('APP_URL', 'http://localhost'),
	environment: Environment::fromString($env->string('APP_ENV', 'production')),
	debug: $env->bool('APP_DEBUG', false),
	timezone: 'America/Chicago'
);
```

A mistake, such as an unknown option or a URL that isn't one, stops the
site with a message naming the problem.

> **After changing `config/` or `.env` on a site you've compiled, run
> `bin/blush cache:compile` again** (or `bin/blush cache:clear`). Compiled
> settings are a snapshot; see [Caching](../running/caching.md).

## Settings saved in the admin

A few settings can also be changed in the admin's
[Settings](../admin/settings/README.md) screens: the site's name, language, and
time zone, the homepage, the trailing slash, feeds, and the sitemap;
the [Themes](../admin/themes.md) screen activates a theme; and the
[Plugins](../admin/plugins.md), [Icon Packs](../admin/icon-packs.md), and
[Admin Themes](../admin/admin-themes.md) screens turn plugins, icon packs,
and admin themes on and off, and the last chooses the site's admin
theme. The admin saves them in
`user/data/settings/`, a file for each group, named for its config file,
with the same keys:

```text
user/data/settings/app.json       {"name": "Field Notes", "timezone": "Europe/Brussels"}
user/data/settings/content.json   {"home": "post"}
user/data/settings/routes.json    {"trailingSlash": true}
user/data/settings/feed.json      {"formats": ["rss", "json"], "fullContent": true, "limit": 20}
user/data/settings/sitemap.json   {"enabled": true, "disallow": ["/drafts/"]}
user/data/settings/theme.json     {"active": "acme/notebook"}
user/data/settings/plugins.json   {"enabled": ["acme/gallery"]}
user/data/settings/icons.json     {"enabled": ["acme/brands"]}
user/data/settings/admin-themes.json  {"enabled": ["blush/library", "blush/neutral"], "default": "blush/library"}
```

(Feeds' full content is saved as `fullContent`, though `config/feed.php`
calls it `content`, which a saved record keeps for itself.) Only those
keys are allowed in core's groups. Another group,
`site.json`, holds the site's own settings, which plugins declare
(`{"tagline": "Notes from the field"}`; see
[Your own settings](../features/settings.md)), and themes and plugins
keep groups of their own, named for them (`acme__gallery.json` for
`acme/gallery`). A file gains an `"id"` at its end when the admin saves
it. A value saved there wins over the one from `config/` or `.env`;
remove it from the file (or choose **Use `config/…`'s value** in the
admin) to go back to the config's value. Compiling (`bin/blush
cache:compile`) leaves the files out, so saving there needs no
compiling. Only `app`, `theme`, `plugins`, and `icons` are read on every
request; each other group is read when something needs it.
On the [SQLite driver](../running/sqlite.md), the groups are
kept in the database instead.

## What development changes

With `APP_ENV=development`:

- New and changed content shows up on the next request.
- Caching is off.
- You can preview themes with `?theme={name}`, such as `?theme=acme/notebook`.

Outside production (in development *and* staging), `robots.txt` asks
search engines not to index the site.
