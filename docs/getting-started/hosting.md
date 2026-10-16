# Hosting

Serve your site from a real web server. Pick whichever fits your host. In
every case, only the `public/` folder is ever served to visitors; your
config, content, and `.env` stay private.

## Shared hosting (Apache)

Upload the **whole project** into your web root (such as `public_html`).
That's it. The `.htaccess` file at the project root sends every request
into `public/`, so nothing else can be requested. If your host lets you
point the domain at `public/` instead, that works too.

Your host needs `mod_rewrite`, which almost every Apache host has. Without
it, the root `.htaccess` refuses every request rather than expose your
files.

## Keeping the project outside the web root

For a more locked-down setup, upload the project somewhere the web can't
reach (such as `~/my-site`), and copy only the **contents** of `public/`
into `public_html`. Then edit that copy of `index.php` so it can find the
project:

```php
$root = '/home/me/my-site';

require "{$root}/vendor/autoload.php";

new Blush\Http\HttpRunner($root, paths: ['public' => __DIR__])->run();
```

## nginx

Point the server's `root` at the project's `public/` folder. The site
project includes a ready-made `nginx.conf.example`. nginx ignores
`.htaccess` files, so never make the project root itself the web root.

## DDEV

The site project works with DDEV: run `ddev start`. DDEV serves the project
root with Apache, just like shared hosting.

On a Mac, DDEV usually syncs your files into its container with Mutagen,
which can take a moment. If a page you just created shows "not found",
reload after a second.

## Caching and compression

Blush sets caching headers itself, the same on any server. A file whose
URL ends in `?v=…` (the stylesheets and scripts Blush prints) is cached
for a year, since a changed file gets a new `?v=`. Any other file is
checked with the server on each use, which costs a short "not modified"
answer when it hasn't changed. Don't add rules that cache files for a
fixed time by type (Apache's `ExpiresByType`): a file without a version
could stay stale in visitors' browsers for that long.

Compressing responses is up to your server, and many hosts already do
it. On Apache, you can add this to the `.htaccess` your server reads:

```apache
<IfModule mod_brotli.c>
	AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/css text/javascript text/markdown text/xml image/svg+xml application/javascript application/json application/manifest+json application/xml application/atom+xml application/rss+xml
</IfModule>

<IfModule !mod_brotli.c>
	<IfModule mod_deflate.c>
		AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript text/markdown text/xml image/svg+xml application/javascript application/json application/manifest+json application/xml application/atom+xml application/rss+xml
	</IfModule>
</IfModule>
```

On nginx, turn on `gzip` and list the same types in `gzip_types`. Fonts
(`woff2`) and images other than SVG are already compressed, so leave
them out.

For Blush's own page cache, see [Caching](../running/caching.md).

## Subdirectories aren't supported yet

Blush expects to be at the root of its domain (`https://example.com/`), not
in a subfolder (`https://example.com/blog/`). Use a subdomain for now.
