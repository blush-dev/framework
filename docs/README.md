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
3. **[Going live](going-live.md):** publish changes, turn on caching, or
   export a static site.

## Guides

| Guide | What it covers |
|---|---|
| [Installation](installation.md) | Requirements, installing, and serving your site |
| [Writing content](content.md) | Files and folders, front matter, drafts, scheduling, and Markdown extras |
| [Media](media.md) | Images, audio, and video |
| [Content types](content-types.md) | Blogs, taxonomies, custom fields, feeds, and archives |
| [Components](components.md) | Callouts, galleries, and your own components, in Markdown and templates |
| [Themes](themes.md) | Choosing, customizing, and building themes |
| [Configuration](configuration.md) | `.env` and every `config/` option |
| [Going live](going-live.md) | Caching, publishing, webhooks, and static export |
| [Command line](cli.md) | Every `bin/blush` command |
| [Extending Blush](extending.md) | Service providers, custom routes, commands, and extensions |
| [Coming from Blush 1.x](coming-from-1x.md) | What carries over and what changed |

## How a Blush site is laid out

```
my-site/
  .env            Settings that differ per machine (URL, environment, secrets)
  config/         Site settings, as PHP files
  user/
    content/      Your pages and posts (Markdown, HTML, JSON, or YAML)
    media/        Images, audio, and video
    data/         Editable data: redirects, theme settings, content types
    themes/       Themes you've made or installed
    extensions/   Extensions you've made or installed
  resources/
    views/        Template overrides for whatever theme is active
    lang/         Translations for your own components and icons (the `app` namespace)
    icons/        Your own SVG icons (see Components)
  public/         The web root: index.php and published files only
  storage/        Caches, the content index, logs, and exports (never commit)
  src/            Your own PHP classes (the App\ namespace)
  bin/blush       The command-line tool
```

The short version: **you write in `user/`, and you configure in `config/`
and `.env`.** Everything in `storage/` is generated and safe to delete.

`user/` holds everything that's yours: what you write, and the themes and
extensions you add. Each theme and extension can be its own git
repository. If you keep `user/` itself in git, ignore those two folders
there so each repository stays separate:

```gitignore
/themes/
/extensions/
```
