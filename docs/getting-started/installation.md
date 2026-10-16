# Installation

Install Blush, set up a new site, and see it in your browser.

## Requirements

- **PHP 8.5** or newer, with the `dom`, `intl`, and `mbstring` extensions.
  Recommended: `opcache` and `fileinfo`. Optional: `zip` (installing
  extensions from a `.zip`), `exif` (photos' embedded details), `zlib`
  (compressed PDFs' details), and `apcu` (keeping the cache in memory
  instead of files, with the `apcu` cache driver).
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

`init` sets up the site. It asks for the site's name, address
(`http://127.0.0.1:8000`, where `bin/blush serve` runs it, is offered;
under DDEV, DDEV's address is), timezone (your computer's is offered; type to search the list), and
environment, and writes them to a new `.env` file, with a secret for
signing [preview links](../admin/previewing-drafts.md). It creates the
`storage/` folders Blush writes to, and offers to create the site's
owner account for the admin (see [Accounts](../concepts/accounts/README.md)): it
asks for your email address, your name, and a username (one made from
your email address is offered). The owner's email address becomes the
site's (`APP_EMAIL`). It ends with what the site is set up as, and where
to go next. It's safe to run again: it never changes a `.env` you
already have, except to add missing secrets and the site's email
address. The [publish webhook](../running/publishing.md#publishing-without-a-shell)
isn't asked about; `bin/blush init --webhook` turns it on.

Pick the `development` environment while you build the site. It shows
content changes right away and gives you detailed error pages. Switch to
`production` when the site goes live (see [Before you launch](before-you-launch.md)).

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
(Without that file, a welcome page shows the next steps instead, and,
while you're not in production, anything wrong with your setup.)

Use `bin/blush serve --port=8080` for another port.

To serve the site from Apache, nginx, or DDEV, see [Hosting](hosting.md).

## Run `blush` from anywhere in your site (optional)

Every command in these docs starts with `bin/blush`, run from the site's
folder. To type just `blush`, from the site's folder or any folder
inside it, save this script as `blush` somewhere on your `PATH`, such as
`~/.local/bin/blush`:

```bash
#!/usr/bin/env bash

# Runs the Blush CLI of the site you're in (this folder or any above it).

dir=$PWD

while [[ $dir != / && ! -f $dir/bin/blush ]]; do
	dir=$(dirname "$dir")
done

if [[ ! -f $dir/bin/blush ]]; then
	echo "blush: not inside a Blush site (no bin/blush here or above)" >&2
	exit 1
fi

exec php "$dir/bin/blush" "$@"
```

Then make it executable:

```sh
chmod +x ~/.local/bin/blush
```

Now `blush doctor` in `user/content/` runs the same as `bin/blush doctor`
at the site's root. Each site keeps its own `bin/blush`, so the script
always runs the Blush of the site you're in. It needs `bash`, so it's
for macOS, Linux, and WSL; on Windows otherwise, keep using
`php bin/blush`.

## Next steps

- [See how a site is laid out](site-layout.md).
- [Write your first page](your-first-page.md).
- [Pick or customize a theme](../themes/README.md).
- When you're ready to launch, read [Hosting](hosting.md) and
  [Before you launch](before-you-launch.md).
