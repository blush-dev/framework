# Configuration

Blush has two places for settings:

- **`.env`** holds what differs from one machine to the next: the site's
  URL, whether it's in development or production, and secrets. Never
  commit it.
- **`config/`** holds how the site works, as PHP files you commit.

Every setting has a sensible default, so you only configure what you want
to change.

## `.env`

| Variable | Default | What it does |
|---|---|---|
| `APP_ENV` | `production` | `development`, `staging`, or `production` |
| `APP_DEBUG` | `false` | Show detailed error pages. Never turn this on for a live site. |
| `APP_NAME` | `Blush` | The site's name |
| `APP_URL` | `http://localhost` | The site's full URL, such as `https://example.com` |
| `APP_TIMEZONE` | `UTC` | The site's timezone, such as `America/Chicago` |
| `APP_LOCALE` | `en_US` | The site's language and region (see the note below) |
| `APP_SECRET` | | Signs [preview links](admin.md#previewing-drafts); they're off without it. At least 32 characters. `bin/blush init` writes one. |
| `PUBLISH_SECRET` | | Turns on the [publish webhook](going-live.md#publishing-without-a-shell). At least 32 characters. |
| `PUBLISH_GIT` | `false` | Run `git pull` in `user/` when publishing |
| `PUBLISH_REMOTE`, `PUBLISH_BRANCH` | | The git remote and branch to pull |

Real environment variables (set by your host or server) win over `.env`.

The `APP_*` values (except `APP_SECRET`) reach Blush through
`config/app.php`, which reads them with `$env`. If your `config/app.php` doesn't pass `locale`, add
`locale: $env->string('APP_LOCALE', 'en_US')` to it.

### What development changes

With `APP_ENV=development`:

- New and changed content shows up on the next request.
- Caching is off.
- You can preview themes with `?theme={slug}`.

Outside production (in development *and* staging), `robots.txt` asks
search engines not to index the site.

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
> settings are a snapshot; see [Going live](going-live.md#speed-it-up).

### App

`config/app.php` · `Blush\Core\AppConfig`

| Option | Default | What it does |
|---|---|---|
| `name` | `'Blush'` | The site's name |
| `url` | `'http://localhost'` | The full site URL |
| `environment` | `Environment::Production` | `Development`, `Staging`, or `Production` |
| `debug` | `false` | Detailed error pages |
| `timezone` | `'UTC'` | The site's timezone |
| `locale` | `'en_US'` | The site's language and region |
| `providers` | `[]` | Your own [service providers](extending.md) |

Without `config/app.php`, these come from the `APP_*` variables.

### Content

`config/content.php` · `Blush\Content\Type\ContentConfig`

| Option | Default | What it does |
|---|---|---|
| `types` | `[]` | Your [content types](content-types.md) |
| `home` | `null` | A type whose listing is the home page |
| `disabled` | `[]` | Built-in types to turn off (`'author'`) |
| `dataTypes` | `true` | Whether types in `user/data/types/` are read |
| `dataTypeUrls` | `true` | Whether those types may set their own `urls` |
| `autoIndex` | `true` | Whether development requests pick up content changes |

### Theme

`config/theme.php` · `Blush\Theme\ThemeConfig`

| Option | Default | What it does |
|---|---|---|
| `active` | `'default'` | The active theme's slug |

`bin/blush theme:activate` writes this file for you.

### Media

`config/media.php` · `Blush\Media\MediaConfig`

| Option | Default | What it does |
|---|---|---|
| `url` | `'/media'` | The URL `user/media` is served from |
| `types` | Images, audio, video, and WebVTT captions | The MIME types that may be served |

### Embeds

`config/embed.php` · `Blush\Embed\EmbedConfig`

The [`embed` component](components.md#built-in-components) asks each
video's site for its size and title (over [oEmbed](https://oembed.com)),
once a month per URL. YouTube and Vimeo are built in; add other sites
here:

```php
<?php

declare(strict_types=1);

use Blush\Embed\EmbedConfig;
use Blush\Embed\OEmbedProvider;

return new EmbedConfig(providers: [
	new OEmbedProvider(
		'dailymotion',
		'Dailymotion',
		['https://www.dailymotion.com/video/*'],
		'https://www.dailymotion.com/services/oembed'
	)
]);
```

A provider has a name, a label, the URLs it embeds (`*` matches
anything), and its oEmbed address, which must be `https://`. Find these
on the site's developer pages or in [oembed.com's list](https://oembed.com/providers.json).
Only URLs a provider matches are embedded; others are links. Embeds
that need the site's own script to work (such as posts on X or
Instagram) show as links for now.

| Option | Default | What it does |
|---|---|---|
| `providers` | `[]` | oEmbed providers to add; one named `youtube` or `vimeo` replaces the built-in |
| `fetch` | `true` | Ask providers for sizes and titles; `false` never does (YouTube and Vimeo still embed, at 16:9) |
| `timeout` | `3` | Seconds to wait for a provider |
| `ttl` | `2592000` | Seconds to keep an answer (30 days) |
| `failureTtl` | `3600` | Seconds before asking again after a provider didn't answer |

Answers are kept in `storage/cache/store/embeds`, which publishing and
`cache:clear` leave alone. Delete that folder to ask every provider again.

### Markdown

`config/markdown.php` · `Blush\Markdown\MarkdownConfig`

| Option | Default | What it does |
|---|---|---|
| `options` | `[]` | [CommonMark options](https://commonmark.thephpleague.com/2.x/configuration/) |
| `extensions` | CommonMark and GitHub extras, footnotes, definition lists, highlighting, attributes | The [CommonMark extensions](https://commonmark.thephpleague.com/2.x/extensions/overview/) to use |
| `inlineParsers` | `[]` | Extra inline parsers |
| `figures` | `true` | Turn a lone image into a `<figure>` |
| `absoluteLinks` | `true` | Turn links starting with `/` into full URLs |
| `directives` | `true` | Render [components](components.md) in Markdown |

Attributes are on by default: `{.class #id}` at the end of a heading,
paragraph, or list item, or on a line of its own above a table, code
block, or rule, gives it classes and an id. The editor's Classes and ID
fields write them.

To add extensions, list the defaults along with yours (listing one twice
is fine). For example, for smart quotes and dashes:

```php
<?php

declare(strict_types=1);

use Blush\Markdown\MarkdownConfig;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;

return new MarkdownConfig(
	extensions: [...MarkdownConfig::DEFAULT_EXTENSIONS, SmartPunctExtension::class]
);
```

### Feeds

`config/feed.php` · `Blush\Feed\FeedConfig`

| Option | Default | What it does |
|---|---|---|
| `formats` | RSS, Atom, and JSON | Which formats to offer (`FeedFormat::Rss`, `::Atom`, `::Json`) |
| `content` | `true` | Include each entry's full content; off, only its summary |
| `limit` | `10` | Entries per feed |

Feeds are turned on per [content type](content-types.md) with its `feed`
option.

### Sitemap and robots.txt

`config/sitemap.php` · `Blush\Sitemap\SitemapConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Serve `/sitemap` and `/robots.txt` |
| `disallow` | `[]` | Paths `robots.txt` asks search engines to skip |
| `robots` | `null` | Your own `robots.txt`, served exactly as written |

### Routes and redirects

`config/routes.php` · `Blush\Routing\RouteConfig`

| Option | Default | What it does |
|---|---|---|
| `trailingSlash` | `false` | Whether URLs end in `/` (`/about/`). The other form redirects. |
| `redirects` | `[]` | Redirects, as `new Redirect('/old', '/new')` |
| `routes` | `[]` | Your own routes (see [Extending](extending.md#your-own-pages)) |
| `controllers` | `[]` | Classes whose attributes declare routes |

Most redirects are easier in `user/data/redirects.yaml`; see
[Writing content](content.md#redirects).

### Caching

`config/cache.php` · `Blush\Cache\CacheConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | Off in development, on elsewhere | Turn caching on or off |
| `driver` | `'file'` | Where cached data lives: `file`, `php`, `apcu`, `array`, or `null` |
| `stores` | `[]` | A different driver per cache, such as `['pages' => 'apcu']` |
| `pages` | `true` | Cache whole pages |
| `maxAge` | `0` | How long browsers may keep a page, in seconds |

### Publishing

`config/publish.php` · `Blush\Publish\PublishConfig`

| Option | Default | What it does |
|---|---|---|
| `secret` | `null` | The webhook secret; the webhook is off without one |
| `git` | `false` | Run `git pull` in `user/` when publishing |
| `remote`, `branch` | `null` | What to pull |
| `path` | `'/_blush/publish'` | The webhook's URL |
| `tolerance` | `300` | How many seconds a webhook request's timestamp may be off |
| `gitBinary` | `'git'` | The `git` command to run |

Without this file, the `PUBLISH_*` variables are used. Keep the secret in
`.env` either way.

### Admin

`config/admin.php` · `Blush\Admin\AdminConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `false` | Turn the admin on; none of its URLs exist while it's off |
| `path` | `'/admin'` | Where the admin lives |
| `app` | `null` | The folder of your own built admin front end; see [The admin](admin.md#your-own-admin) |

### Preview links

`config/preview.php` · `Blush\Preview\PreviewConfig`

| Option | Default | What it does |
|---|---|---|
| `secret` | `null` | Signs preview links; they're off without one |
| `lifetime` | `604800` | How many seconds a link works (a week) |
| `path` | `'/_blush/preview'` | The preview URL |

Without this file, `APP_SECRET` is used. Keep the secret in `.env`
either way.

### Accounts and sessions

`config/auth.php` · `Blush\Auth\AuthConfig`

| Option | Default | What it does |
|---|---|---|
| `roles` | `[]` | Your own roles (`Blush\Auth\Role` objects), which can replace built-in ones; see [Accounts and roles](accounts.md#your-own-roles) |
| `authorTaxonomy` | `'author'` | The taxonomy an account's author belongs to, if you renamed the built-in one |
| `minPasswordLength` | `12` | The shortest password accepted (at least 8) |
| `maxAttempts` | `5` | Wrong passwords allowed for one username and address before a lockout |
| `lockout` | `900` | How many seconds a lockout lasts |

`config/session.php` · `Blush\Session\SessionConfig`

| Option | Default | What it does |
|---|---|---|
| `cookie` | `'blush_session'` | The session cookie's name; over HTTPS it gets the `__Host-` prefix |
| `idle` | `7200` | How many seconds a session lasts without a request |
| `lifetime` | `43200` | How many seconds a session lasts at most |
| `secure` | `null` | Force the cookie's `Secure` flag on or off; by default it's on over HTTPS |

### Static export

`config/export.php` · `Blush\Export\ExportConfig`

| Option | Default | What it does |
|---|---|---|
| `url` | The site's URL | The URL the exported site will live at, such as `https://example.com` |
| `crawl` | `true` | Follow links to find pages |
| `paths` | `[]` | Extra paths to export, such as `['/hidden-page']` |
| `exclude` | `[]` | Paths to skip; wildcards allowed (`'/drafts/*'`) |
| `hosts` | `['apache', 'netlify']` | Which [host files](going-live.md#host-files) to write |
| `redirectPages` | `true` | Write a page at each redirected URL that forwards the browser |

### Logging

`config/log.php` · `Blush\Log\LogConfig`

| Option | Default | What it does |
|---|---|---|
| `driver` | `LogDriver::File` | `File`, `Stderr`, or `Null` |
| `level` | `Level::Warning` | The least serious level logged (`Debug` … `Emergency`) |
| `file` | `'blush.log'` | The log file, in `storage/logs/` |
| `channel` | `'blush'` | The name on each line |

### Extensions and middleware

- `config/extensions.php` · `Blush\Extension\ExtensionConfig`: `enabled`
  (only these extensions) and `disabled` (never these). Every installed
  extension is on by default.
- `config/http.php` · `Blush\Http\HttpConfig`: `middleware`, a list of
  PSR-15 middleware classes run on every request.
