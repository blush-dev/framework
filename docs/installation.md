# Installation

## Requirements

- **PHP 8.5** or newer, with the `dom`, `intl`, and `mbstring` extensions
- **Composer**
- A web server: Apache (including most shared hosting), nginx, or PHP's
  built-in server for local work

Blush doesn't need a database or any long-running process.

## Create a site

A Blush site starts from the `blush-dev/blush` site project, which you copy
and make your own:

```sh
git clone -b 2.x https://github.com/blush-dev/blush.git my-site
cd my-site
composer install
bin/blush init
```

`init` sets up the site. It asks for the site's name, address (use
`http://127.0.0.1:8000` for now), timezone, and environment, and writes
them to a new `.env` file, with a secret for signing
[preview links](admin.md#previewing-drafts). It also asks whether to turn on the
[publish webhook](going-live.md#publishing-without-a-shell), and creates the
`storage/` folders Blush writes to, and offers to create an administrator
account for the admin (see [Accounts and roles](accounts.md)). It's safe
to run again: it never changes a `.env` you already have, except to add
missing secrets.

Pick the `development` environment while you build the site. It shows
content changes right away and gives you detailed error pages. Switch to
`production` when the site goes live (see [Going live](going-live.md)).

You can change any setting later by editing `.env`.

## Check your setup

```sh
bin/blush doctor
```

`doctor` checks PHP and its extensions, your `.env`, settings that are
risky on a live site (such as `APP_DEBUG` left on), and whether Blush can
write to `storage/`. Each problem comes with what to do about it.

Your web server may run a different PHP than your command line, so on a
host, it's the web server's settings that count. If Blush can't write to
`storage/`, the site shows a page that says so, rather than an error.

## See it in your browser

The quickest way is the built-in server:

```sh
bin/blush serve
```

Open http://127.0.0.1:8000. You'll see the homepage from
`user/content/index.md`. Edit that file and reload to see your change.

Use `bin/blush serve --port=8080` for another port.

## Serving it for real

Pick whichever fits your host. In every case, only the `public/` folder is
ever served to visitors; your config, content, and `.env` stay private.

### Shared hosting (Apache)

Upload the **whole project** into your web root (such as `public_html`).
That's it. The `.htaccess` file at the project root sends every request
into `public/`, so nothing else can be requested. If your host lets you
point the domain at `public/` instead, that works too.

Your host needs `mod_rewrite`, which almost every Apache host has. Without
it, the root `.htaccess` refuses every request rather than expose your
files.

### Keeping the project outside the web root

For a more locked-down setup, upload the project somewhere the web can't
reach (such as `~/my-site`), and copy only the **contents** of `public/`
into `public_html`. Then edit that copy of `index.php` so it can find the
project:

```php
$root = '/home/me/my-site';

require "{$root}/vendor/autoload.php";

new Blush\Http\HttpRunner($root, paths: ['public' => __DIR__])->run();
```

### nginx

Point the server's `root` at the project's `public/` folder. The site
project includes a ready-made `nginx.conf.example`. nginx ignores
`.htaccess` files, so never make the project root itself the web root.

### DDEV

The site project works with DDEV: run `ddev start`. DDEV serves the project
root with Apache, just like shared hosting.

On a Mac, DDEV usually syncs your files into its container with Mutagen,
which can take a moment. If a page you just created shows "not found",
reload after a second.

## Subdirectories aren't supported yet

Blush expects to be at the root of its domain (`https://example.com/`), not
in a subfolder (`https://example.com/blog/`). Use a subdomain for now.

## Next steps

- [Write some content](content.md).
- [Pick or customize a theme](themes.md).
- When you're ready to launch, read [Going live](going-live.md).
