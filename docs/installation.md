# Installation

## Requirements

- **PHP 8.5** or newer, with the `intl` and `mbstring` extensions
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
cp .env.example .env
```

Then open `.env` and set at least these:

```ini
APP_ENV=development
APP_DEBUG=true
APP_NAME="My Site"
APP_URL="http://127.0.0.1:8000"
APP_TIMEZONE="America/Chicago"
```

`APP_ENV=development` shows content changes right away and gives you
detailed error pages. Switch to `production` when the site goes live (see
[Going live](going-live.md)).

## See it in your browser

The quickest way is the built-in server:

```sh
bin/blush serve
```

Open http://127.0.0.1:8000. You'll see the home page from
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

## Subdirectories aren't supported yet

Blush expects to be at the root of its domain (`https://example.com/`), not
in a subfolder (`https://example.com/blog/`). Use a subdomain for now.

## Next steps

- [Write some content](content.md).
- [Pick or customize a theme](themes.md).
- When you're ready to launch, read [Going live](going-live.md).
