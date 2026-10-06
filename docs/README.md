# Blush documentation

Blush is a flat-file CMS for PHP. Your site is a folder of Markdown files:
there's no database to set up, and every page, post, and setting is a file
you can edit, copy, and keep in git.

> **Blush 2 is under heavy development.** Things will change before the
> first stable release, so don't run it in production yet.

## Start here

1. **[Installation](installation.md):** get a site running on your computer
   or your host.
2. **[Writing content](content.md):** add pages and posts, and learn how
   files become URLs.
3. **[Going live](going-live.md):** publish changes and turn on caching.

## Guides

| Guide | What it covers |
|---|---|
| [Installation](installation.md) | Requirements, installing, and serving your site |
| [Writing content](content.md) | Files and folders, front matter, drafts, scheduling, and Markdown extras |
| [Media](media.md) | Images, audio, and video |
| [Content types](content-types.md) | Blogs, taxonomies, custom fields, feeds, and archives |
| [Directives](directives.md) | Callouts, galleries, buttons, and your own directives, in your content |
| [Components](components.md) | Reusable pieces of a theme's templates |
| [Themes](themes.md) | Choosing, customizing, and building themes |
| [Menus and regions](menus.md) | Navigation menus, and the sidebar and footer areas themes offer |
| [The admin](admin.md) | Turning on the admin, its dashboard, and building your own |
| [Accounts and roles](accounts.md) | Who can sign in to the admin, and what they can do |
| [Configuration](configuration.md) | `.env` and every `config/` option |
| [Going live](going-live.md) | Caching, publishing, and webhooks |
| [Command line](cli.md) | Every `bin/blush` command |
| [Extending Blush](extending.md) | Service providers, custom routes, commands, and extensions: plugins and icon packs |
| [Coming from Blush 1.x](coming-from-1x.md) | What carries over and what changed |

## How a Blush site is laid out

```
my-site/
  .env            Settings that differ per machine (URL, environment, secrets)
  config/         Site settings, as PHP files
  user/
    content/      Your pages and posts (Markdown, HTML, JSON, or YAML)
    media/        Images, audio, and video
    data/         Editable data: menus, regions, redirects, theme settings, content types
  extensions/     Themes, plugins, and icon packs you've made or installed, at their names (acme/hello/)
  resources/
    views/        Template overrides for whatever theme is active
    lang/         Translations for your own directives and icons (the `app` namespace)
    icons/        Your own SVG icons (see Directives)
  public/         The web root: index.php and published files only
  storage/        Caches, the content index, logs, sessions, admin accounts, and deleted entries (never commit)
  src/            Your own PHP classes (the App\ namespace)
  bin/blush       The command-line tool
```

The short version: **you write in `user/`, you add extensions in
`extensions/`, and you configure in `config/` and `.env`.** Everything in `storage/` is generated and safe to delete,
except `storage/accounts/`, which holds the admin's accounts.

`user/` holds what you write: content, media, and data. Publishing (and
its `git pull`) only ever touches `user/`, so publishing content never
deploys code. The [extensions](extending.md#extensions) you add, themes,
plugins, and icon packs, live in `extensions/`, each in a folder at its
name (`extensions/acme/notebook/`), the way Composer keeps packages in
`vendor/`. Each can be its own git repository.
