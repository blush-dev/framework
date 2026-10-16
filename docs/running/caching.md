# Caching

Speed up a live site by compiling what Blush builds on each request, letting
the web server serve files directly, and caching rendered pages.

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

## The page cache

Caching is on by default outside development. Blush caches rendered
pages, so most visits skip rendering entirely, and answers repeat visits
with `304 Not Modified`. [Publishing](publishing.md) clears the caches,
so you never need to think about stale pages.

To clear everything by hand:

```sh
bin/blush cache:clear
```

See [Caching configuration](../reference/configuration/cache.md) to
change where cached data is kept or to turn the page cache off.

For the caching headers Blush sends and compressing responses, see
[Hosting](../getting-started/hosting.md#caching-and-compression).
