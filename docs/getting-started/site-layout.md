# How a site is laid out

A Blush site is a folder. This page names each part of it and what
belongs there.

```
my-site/
  .env            Settings that differ per machine (URL, environment, secrets)
  config/         Site settings, as PHP files
  user/
    content/      Your pages and posts, as Markdown files
    media/        Images, audio, and video
    data/         Editable data: menus, redirects, settings, content types
  extensions/     Themes, plugins, and icon packs you've made or installed, at their names (acme/hello/)
  public/         The web root: index.php and published files only
  storage/        Caches, the content index, logs, sessions, admin accounts, and deleted entries (never commit)
  src/            Optional PHP classes of your own (the App\ namespace); reusable code belongs in a plugin
  bin/blush       The command-line tool
```

The short version: **you write in `user/`, you add extensions in
`extensions/`, and you configure in `config/` and `.env`.** Everything in
`storage/` is generated and safe to delete, except `storage/accounts/`,
which holds the admin's accounts.

## `user/`

`user/` holds what you write: content, media, and data. Publishing (and
its `git pull`) only ever touches `user/`, so publishing content never
deploys code (see [Publishing](../running/publishing.md)).

## `extensions/`

The [extensions](../concepts/extensions.md) you add, themes, plugins, and
icon packs, live in `extensions/`, each in a folder at its name
(`extensions/acme/notebook/`), the way Composer keeps packages in
`vendor/`. Each can be its own git repository.

The site itself has no templates, icons, or code conventions of its own:
templates come from themes (to change one, make a
[child theme](../themes/README.md)), icons from icon packs, plugins, and
themes, and code from [plugins](../plugins/README.md).

## `config/` and `.env`

`.env` holds what differs per machine, and `config/` the site's settings
as PHP files. See [Configuration](../concepts/configuration.md).

## `src/`

`src/` is optional. Most sites put their code in a plugin instead; see
[Site code in `src/`](../plugins/site-code.md) for when it fits.
