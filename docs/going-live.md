# Going live

You can run Blush two ways:

- **As a PHP site** on your host, where you publish changes as you make
  them. Most of this page is about that.
- **As a [static export](#static-export):** plain HTML files you can host
  anywhere, with no PHP at all.

## Before you launch

In the live site's `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL="https://example.com"
```

Then check your content:

```sh
bin/blush content:lint
```

## Publishing changes

In development, Blush notices changed files by itself. **In production it
doesn't**, because checking every file on every request would slow the
site down. Instead, after you upload new or changed content, publish it:

```sh
bin/blush publish
```

That finds what changed, updates the content index, refreshes redirects
and content types, and clears the caches, so visitors see the new content
right away.

> **A note on PHP's opcache.** Blush stores its index as PHP files, which
> PHP keeps in memory. After `bin/blush publish`, the web server can take
> a couple of seconds to notice the new files. If your host sets
> `opcache.validate_timestamps=0`, it won't notice until PHP restarts; use
> the [webhook](#publishing-without-a-shell) there instead, which publishes
> from inside the web server.

### Publishing with git

If `user/` is its own git repository, Blush can pull the latest content
first:

```sh
bin/blush publish --pull
```

To always pull, set `PUBLISH_GIT=true` in `.env` (and `PUBLISH_REMOTE` and
`PUBLISH_BRANCH` if you need them). Then `--no-pull` skips it once.

The pull updates only the `user/` repository. Themes, plugins, and icon
packs kept in their own repositories inside it aren't touched, so
publishing content never deploys code. Update those the way you deploy the rest of your
site.

### Publishing without a shell

Many shared hosts don't offer SSH. Blush has a publish **webhook** for
that: a URL you call after uploading, which runs the same steps as
`bin/blush publish`.

1. Turn it on with `bin/blush init --webhook`, which adds a random
   secret to `.env`. Or set one of at least 32 random characters
   yourself:

   ```ini
   PUBLISH_SECRET="paste-a-long-random-string-here-000000"
   ```

2. Send a signed `POST` to `https://example.com/_blush/publish`. The
   signature proves the request came from you:

   ```sh
   ts=$(date +%s); body='{}'
   sig=$(printf '%s.%s' "$ts" "$body" | openssl dgst -sha256 -hmac "$PUBLISH_SECRET" -r | cut -d' ' -f1)
   curl -X POST \
   	-H "X-Publish-Timestamp: $ts" \
   	-H "X-Publish-Signature: sha256=$sig" \
   	-d "$body" https://example.com/_blush/publish
   ```

Git hosts such as GitHub can call it for you when you push, from a CI
job. The webhook doesn't exist at all until you set a secret, and each
signed request works only once, within five minutes.

## Speed it up

These are optional, but worth doing on a live site. Run them after each
deploy of code, config, or themes:

```sh
bin/blush cache:compile   # precompile config, routes, content types, and more
bin/blush theme:publish   # let the web server serve theme files directly
bin/blush media:publish   # let the web server serve media directly
```

After compiling, changes to `config/` or `.env` take effect only when you
run `cache:compile` (or `cache:clear`) again.

### Caching

Caching is on by default outside development. Blush caches rendered
pages, so most visits skip rendering entirely, and answers repeat visits
with `304 Not Modified`. Publishing clears the caches, so you never need
to think about stale pages.

To clear everything by hand:

```sh
bin/blush cache:clear
```

See [Configuration](configuration.md#caching) to change where cached data
is kept or to turn the page cache off.

## Scheduled posts

Give an entry a future `published` date and it goes live at that time,
with no extra step: the first request after that time updates the site.

On a quiet site, a visitor might see the new post a little late. To go
live exactly on time, add this command to cron, every minute or so:

```sh
bin/blush schedule:run
```

It also cleans out expired cache entries.

## Static export

`bin/blush build` turns your whole site into plain files: HTML pages,
feeds, sitemaps, media, and theme files. Upload them to any web host,
including GitHub Pages, Netlify, Cloudflare Pages, or plain Apache.

```sh
bin/blush build --base-url=https://example.com
```

The files are written to `storage/export/`. Preview them exactly as a
static host would serve them:

```sh
bin/blush serve --static
```

- `--base-url` is where the files will live. Without it, Blush uses
  `config/export.php`'s `url`, then `APP_URL`.
- `--incremental` skips rendering when nothing has changed since the last
  build.
- `--no-crawl` exports only the pages Blush knows about, without
  following links. Normally it follows every link, which also finds
  broken links for you; they're listed as warnings.

Every page Blush can serve is exported, including listings and their
extra pages, date archives, term pages, feeds, and your 404 page.
Redirects are exported too. Some things can't work without PHP: the
publish webhook, and scheduled posts going live by themselves (rebuild
after the date passes).

### Host files

A static host needs to be told a few things, such as where your 404 page
is and which URLs redirect. `build` writes these for you:

- **`.htaccess`**, for Apache and most shared hosting.
- **`_redirects` and `_headers`**, for Netlify and Cloudflare Pages.

Choose which to write with `hosts` in
[`config/export.php`](configuration.md#static-export). A file of the same
name in your `public/` folder wins over the generated one.
