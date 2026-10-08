# Configuration

Blush has two places for settings:

- **`.env`** holds what differs from one machine to the next: the site's
  URL, whether it's in development or production, and secrets. Never
  commit it.
- **`config/`** holds how the site works, as PHP files you commit.

Every setting has a sensible default, so you only configure what you want
to change.

A few settings can also be changed in the admin's
[Settings](admin.md#settings) screens: the site's name, language, and
time zone, the homepage, the trailing slash, feeds, and the sitemap;
the [Themes](admin.md#themes) screen activates a theme; and the
[Plugins](admin.md#plugins) and [Icon Packs](admin.md#icon-packs)
screens turn plugins and icon packs on and off. The admin saves them in `user/data/settings.json`, in sections named for the
config files, with the same keys:

```json
{
    "app": { "name": "Field Notes", "timezone": "Europe/Brussels" },
    "content": { "home": "post" },
    "routes": { "trailingSlash": true },
    "feed": { "formats": ["rss", "json"], "content": true, "limit": 20 },
    "sitemap": { "enabled": true, "disallow": ["/drafts/"] },
    "theme": { "active": "acme/notebook" },
    "plugins": { "enabled": ["acme/gallery"] },
    "icons": { "enabled": ["acme/brands"] }
}
```

Only those keys are allowed, plus `site`, which holds the settings
[field sets](content-types.md#field-sets) add to the Settings screens
(`{"site": {"tagline": "Notes from the field"}}`; see
[Your own settings](themes.md#your-own-settings)). A value saved there wins over the one from
`config/` or `.env`; remove it from the file (or choose **Use
`config/…`'s value** in the admin) to go back to the config's value.
Compiling (`bin/blush cache:compile`) leaves the file out, so saving
there needs no compiling.

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
| `STORAGE_DRIVER` | `filesystem` | Where the site's data is kept (see [Storage](#storage)) |

Real environment variables (set by your host or server) win over `.env`.

The `APP_*` values (except `APP_SECRET`) reach Blush through
`config/app.php`, which reads them with `$env`. If your `config/app.php` doesn't pass `locale`, add
`locale: $env->string('APP_LOCALE', 'en_US')` to it.

### What development changes

With `APP_ENV=development`:

- New and changed content shows up on the next request.
- Caching is off.
- You can preview themes with `?theme={name}`, such as `?theme=acme/notebook`.

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
| `description` | `''` | One line about the site: the summary in `llms.txt`, and the homepage's meta description and feeds' descriptions when nothing more specific describes them |
| `dateFormat` | `'long'` | How themes show dates: `full`, `long`, `medium`, or `short` follow the site's language (`October 4, 2026` in `en_US`); an [ICU pattern](https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax) such as `'d MMMM y'` keeps its order in any language |
| `timeFormat` | `'short'` | How themes show times, the same way: `short` is `2:30 PM` in `en_US`; `'HH:mm'` is always `14:30` |
| `languages` | `[]` | Other languages your content is written in, by code: `['fr' => 'fr_FR']`, or `['fr' => ['locale' => 'fr_FR', 'label' => 'Français']]`. See [Translations](content.md#translations) |
| `untranslated` | `'redirect'` | What another language does with an entry not translated into it: `'hide'` (not found), `'redirect'` (to the original), or `'include'` (redirect, and list the originals in that language's lists too). See [Pages that aren't translated](content.md#pages-that-arent-translated) |

Without `config/app.php`, these come from the `APP_*` variables.

### Content

`config/content.php` · `Blush\Content\ContentConfig`

| Option | Default | What it does |
|---|---|---|
| `home` | `null` | A type whose listing is the homepage |
| `disabled` | `[]` | Built-in types to turn off (`'profile'`) |
| `dataTypes` | `true` | Whether types in `user/data/types/` and relations in `user/data/relations/` are read |
| `dataTypeUrls` | `true` | Whether those types may set their own `urls` |
| `autoIndex` | `true` | Whether development requests pick up content changes |

### Storage

`config/storage.php` · `Blush\Storage\StorageConfig`

| Option | Default | What it does |
|---|---|---|
| `driver` | `'filesystem'` | Where the site's data is kept. `filesystem` (files, as a flat-file site keeps them) is the only one for now. |
| `areas` | `[]` | A different driver for an area: `content`, `data`, `accounts`, `sessions`, or `jobs`, such as `['sessions' => 'filesystem']` |

Without this file, `STORAGE_DRIVER` is used. Media files are always
files, whatever the driver.

### Fields

`config/fields.php` · `Blush\Field\FieldConfig`

| Option | Default | What it does |
|---|---|---|
| `sets` | `[]` | Your [field sets](content-types.md#field-sets) |
| `dataSets` | `true` | Whether sets in `user/data/fields/` are read |

### Theme

`config/theme.php` · `Blush\Theme\ThemeConfig`

| Option | Default | What it does |
|---|---|---|
| `active` | `'blush/default'` | The active theme's name, such as `'acme/notebook'` |

`bin/blush theme:activate` writes this file for you.

### Media

`config/media.php` · `Blush\Media\MediaConfig`

| Option | Default | What it does |
|---|---|---|
| `url` | `'/media'` | The URL `user/media` is served from |
| `types` | Images, audio, video, WebVTT captions, and PDFs | The MIME types that may be served and uploaded |
| `autoIndex` | `true` | Whether development requests pick up media changes in the library (elsewhere, `media:index` or publishing does) |
| `uploads` | Every kind, any size PHP takes, in `{year}/{month}` | What the admin may upload, how large, and where it goes (a `MediaUploads`; below) |

Documents besides PDFs (`text/plain`, `text/csv`, `text/markdown`,
`application/epub+zip`, `application/rtf`, and Word, Excel,
PowerPoint, and OpenDocument files) can be served and uploaded once
you add their types to `types`.

`uploads` is what the Media settings screen edits, and what it saves
in `user/data/settings.json` wins over this file:

```php
use Blush\Media\MediaConfig;
use Blush\Media\MediaUploadRule;
use Blush\Media\MediaUploads;

return new MediaConfig(
	url: '/media',
	uploads: new MediaUploads(maxSize: 24, kinds: [
		'audio' => new MediaUploadRule(maxSize: 40, path: 'audio'),
		'file'  => new MediaUploadRule(enabled: false)
	])
);
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Whether anything may be uploaded. Files already in `user/media` are served either way |
| `maxSize` | `null` | The largest file, in megabytes; `null` for whatever PHP takes (`upload_max_filesize`, `post_max_size`), which is always the most |
| `path` | `'{year}/{month}'` | The folder under `user/media` a file goes in: a plain folder (`uploads`), or a pattern with `{year}`, `{month}`, `{day}`, `{kind}` (`images`, `videos`, `audio`, `documents`, or `files`), and `{ext}`. Empty puts files straight in `user/media` |
| `kinds` | `[]` | Rules for one kind (`image`, `video`, `audio`, `document`, `file`), each a `MediaUploadRule` with `enabled`, and its own `maxSize` and `path` (`null` takes the ones above) |

Changing a path doesn't move anything: a file keeps the address it was
uploaded at.

### Embeds

`config/embed.php` · `Blush\Embed\EmbedConfig`

The [`embed` directive](directives.md#built-in-directives) asks each
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
`cache:clear` leave alone. Run `bin/blush cache:clear --embeds` to ask
every provider again.

### Markdown

`config/markdown.php` · `Blush\Markdown\MarkdownConfig`

Blush's Markdown is the same on every site: CommonMark, plus autolinks,
`~~struck~~` and `==highlighted==` text, tables, task lists, footnotes,
definition lists, attributes, and [directives](directives.md) (see
[Markdown](content.md#markdown)). These options
change how it renders, not what it means. The first five are on the
[Writing settings screen](admin.md#settings) too.

| Option | Default | What it does |
|---|---|---|
| `mentions` | `true` | `@name` links to the [profile](content-types.md) with that slug, once it's published |
| `smartPunctuation` | `true` | Straight quotes become curly ones, `--` and `---` dashes, and `...` an ellipsis |
| `headingAnchors` | `true` | Each heading gets a link to itself, styled by `anchors` |
| `figures` | `true` | Turn a lone image into a `<figure>` |
| `html` | `RawHtml::Allow` | What raw HTML in content does: `Allow` renders it, `Filter` shows script, frames, forms, and styles as text and drops `javascript:` link addresses, and `Escape` shows it all as text |
| `lineBreaks` | `true` | A line break inside a paragraph is kept as `<br>`; off, it's a space |
| `absoluteLinks` | `true` | Turn links starting with `/` into full URLs |
| `directives` | `true` | Render [directives](directives.md) in Markdown |
| `anchors` | see below | How heading anchors look: a `Blush\Markdown\HeadingAnchorOptions` |
| `footnotes` | see below | How footnotes look: a `Blush\Markdown\FootnoteOptions` |

`HeadingAnchorOptions` takes the anchor's `class` (`heading-anchor`),
the `symbol` it shows (`#`), its `title` (`Link to this section`; `''`
for none), a `prefix` for its id (`''`), and whether it goes `before`
the heading's text (`false`, after). A heading with an id of its own
(`## Install {#setup}`) is linked by that id. The anchor is hidden from
screen readers and the tab order, and left out of excerpts. For
example:

```php
<?php

declare(strict_types=1);

use Blush\Markdown\HeadingAnchorOptions;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\RawHtml;

return new MarkdownConfig(
	html: RawHtml::Filter,
	anchors: new HeadingAnchorOptions(symbol: '¶', before: true)
);
```

`FootnoteOptions` takes the classes of the list at the end
(`container`, `footnotes`), of each reference in the text (`reference`,
`footnote-ref`), of each note (`note`, `footnote`), and of each note's
link back (`backReference`, `footnote-backref`), and whether a rule goes
above the list (`rule`, `true`).

Who may add raw HTML in the admin is a matter of capabilities (see
[Capabilities](accounts.md#capabilities)); `html` applies to every page,
whoever wrote it.

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
| `blockAi` | `[]` | Kinds of AI crawler the generated `robots.txt` asks to stay away: `'training'`, `'search'`, `'fetchers'` |

`blockAi` takes three kinds of AI crawler, each a list of known bots kept
up to date with Blush:

- `training`: crawlers that collect pages to train models (GPTBot,
  ClaudeBot, CCBot, Bytespider, meta-externalagent), plus
  Google-Extended and Applebot-Extended, which keep pages out of Google's
  and Apple's AI training while their search crawlers still visit.
- `search`: crawlers that index pages so AI answers can cite and link
  them (OAI-SearchBot, Claude-SearchBot, PerplexityBot).
- `fetchers`: tools that fetch a page when a person asks their assistant
  about it (ChatGPT-User, Claude-User, Perplexity-User). Blocking these
  blocks your readers' own tools.

`robots.txt` is a request: well-behaved crawlers follow it, others may
not. Outside production, it already asks every crawler to stay away, and
your own `robots` replaces the generated file and these rules with it.

### Markdown pages and llms.txt

`config/llms.php` · `Blush\Llms\LlmsConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Serve a Markdown version of every page and `/llms.txt` |
| `full` | `false` | Also serve `/llms-full.txt`: every page `llms.txt` lists, in full, in one file |

Agents and AI tools read Markdown more easily than HTML, so every
published page also has a Markdown version at its address plus `.md`:
`/archives/hello` is `/archives/hello.md`, `/about/` is `/about.md`, and
the homepage is `/index.md`. It's the entry as you wrote it, with its
title, address, dates, and summary at the top. Directives such as
`:::figure` stay as written. Links and images get full addresses
(`/about` becomes `https://example.com/about`, and media points at its
media URL, as on the HTML page), in Markdown and in directives' URL
options, so the copy still works once it's read elsewhere. Code and
HTML are left exactly as written. Unlisted entries have one; drafts,
scheduled entries, and hidden entries don't. Each page links to its
Markdown version in its `<head>`.

`/llms.txt` follows [llmstxt.org](https://llmstxt.org): your site's
name, its [description](#app), then a section for each content type
linking every public entry's Markdown version, newest first for dated
types and by title for terms and profiles. Collections and trees are
listed unless they say [`llms: false`](content-types.md), and profiles
are left out unless they say `llms: true` (only profiles with their own
files are listed). A [type of terms](content-types.md#terms-and-relationships),
such as tags, is a collection: it's listed unless it says `llms: false`,
which the admin's new Terms types and the taxonomy migration add. Every page keeps its
Markdown copy either way.

`/llms-full.txt` has the same heading, then the Markdown copy of every
page `llms.txt` lists, in the same order, so a tool can read the whole
site in one request. It's off by default because it's large: about
3.6 MB for a site of a thousand posts. Blush builds it on each request
(it's too big for the page cache).

All of these can also be changed in the admin, on **Settings → AI**.

### Routes and redirects

`config/routes.php` · `Blush\Routing\RouteConfig`

| Option | Default | What it does |
|---|---|---|
| `trailingSlash` | `false` | Whether URLs end in `/` (`/about/`). The other form redirects, except for the admin, the publish webhook, preview links, and routes marked `exact` (see [Extending](extending.md#your-own-pages)). |
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
| `maxAttempts` | `10` | Badly signed webhook requests from one address before it's locked out |
| `lockout` | `900` | How many seconds a lockout lasts, from the first failed request |
| `gitBinary` | `'git'` | The `git` command to run |

Without this file, the `PUBLISH_*` variables are used. Keep the secret in
`.env` either way.

### Background jobs

`config/jobs.php` · `Blush\Job\JobConfig`

| Option | Default | What it does |
|---|---|---|
| `runner` | `RunnerMode::Auto` | When jobs run besides cron and `jobs:work`. `Auto` also runs them after a page is served while cron hasn't run lately; `Cron` never runs them on a visit; `Sync` runs each job as soon as it's queued (for local development and tests). The admin always runs the jobs you start and wait on. |
| `budget` | `50` | How many seconds `schedule:run` works through the queue |
| `webBudget` | `10` | How many seconds of work after a page is served |
| `timeout` | `900` | How many seconds a job may run before it's taken for stopped (its process died) and tried again |
| `keepDone` | `86400` | How many seconds finished jobs are kept, for their results (a day) |
| `keepFailed` | `604800` | How many seconds failed jobs are kept (a week) |

See [Going live](going-live.md#background-jobs-and-cron) for cron.

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
| `minPasswordLength` | `12` | The shortest password accepted (at least 8) |
| `maxAttempts` | `5` | Wrong passwords allowed for one username and address before a lockout |
| `lockout` | `900` | How many seconds a lockout lasts |
| `passwordLinkLifetime` | `604800` | How many seconds a [password link](accounts.md#password-links) lasts (a week; at least 60) |
| `signups` | `false` | Whether anyone can make an account on the site. Saved for now: there's no sign-up form yet |
| `signupRole` | `'member'` | The role an account made by signing up holds; never `owner` or `administrator` |

`config/session.php` · `Blush\Session\SessionConfig`

| Option | Default | What it does |
|---|---|---|
| `cookie` | `'blush_session'` | The session cookie's name; over HTTPS it gets the `__Host-` prefix |
| `idle` | `7200` | How many seconds a session lasts without a request |
| `lifetime` | `43200` | How many seconds a session lasts at most |
| `secure` | `null` | Force the cookie's `Secure` flag on or off; by default it's on over HTTPS |

### Logging

`config/log.php` · `Blush\Log\LogConfig`

| Option | Default | What it does |
|---|---|---|
| `driver` | `LogDriver::File` | `File`, `Stderr`, or `Null` |
| `level` | `Level::Warning` | The least serious level logged (`Debug` … `Emergency`) |
| `file` | `'blush.log'` | The log file, in `storage/logs/` |
| `channel` | `'blush'` | The name on each line |

### Plugins and middleware

- `config/plugins.php` · `Blush\Plugin\PluginConfig`: `enabled`, the
  plugins in `extensions/` to turn on, by name
  (`new PluginConfig(enabled: ['acme/hello'])`). A local plugin is off
  until it's named; a Composer plugin is on. Turning plugins on and off
  in the admin saves its own `enabled` list in
  `user/data/settings.json`, in place of this one, naming every plugin
  that's on, Composer's included; a plugin it doesn't name is off. A
  plugin that's on
  still runs only when its [requirements](extending.md#requirements) are
  met. Naming one that isn't installed is an error.
- `config/icons.php` · `Blush\Icon\IconConfig`: `enabled`, the
  [icon packs](extending.md#icon-packs) in `extensions/` to turn on, by
  name (`new IconConfig(enabled: ['acme/brands'])`). A local pack is off
  until it's named; a Composer pack is on. The admin's own list, naming
  every pack that's on, replaces this one when it's saved.
- Neither has a list of what's off: config only says what's on
  ([Turning extensions on](extending.md#turning-extensions-on)).
- `config/http.php` · `Blush\Http\HttpConfig`: `middleware`, a list of
  PSR-15 middleware classes run on every request.
