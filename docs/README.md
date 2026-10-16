# Blush documentation

Blush is a CMS for PHP that keeps your site in files by default. Your site
is a folder of Markdown files: there's no database to set up, and every
page, post, and setting is a file you can edit, copy, and keep in git.

> **Blush 2 is under heavy development.** Things will change before the
> first stable release, so don't run it in production yet.

## Start here

1. **[Installation](getting-started/installation.md):** get a site running
   on your computer or your host.
2. **[Your first page](getting-started/your-first-page.md):** write a page
   and see it on your site.
3. **[Before you launch](getting-started/before-you-launch.md):** what to
   check before the site goes live.

## Sections

| Section | What it covers |
|---|---|
| [Getting started](getting-started/README.md) | Installing, hosting, how a site is laid out, and coming from Blush 1.x |
| [Core concepts](concepts/README.md) | Content files, types, relationships, media, blocks, menus, accounts, extensions, and configuration |
| [Using the admin](admin/README.md) | Every screen of the admin, and the editor |
| [Running a site](running/README.md) | Publishing, caching, SQLite, cron and jobs, and backups |
| [Plugins](plugins/README.md) | Making a plugin: where a site's code belongs |
| [Themes](themes/README.md) | Choosing, customizing, and building themes |
| [Icon packs](icon-packs/README.md) | Adding icons to a site |
| [Admin themes](admin-themes/README.md) | Choosing how the admin looks, and making a theme for it |
| [APIs and features](features/README.md) | What your code can do: routes, commands, blocks, fields, jobs, events, the API, MCP, and more |
| [Reference](reference/README.md) | Every `config/` option and every `bin/blush` command |

## Where code goes

Most sites are built from **extensions**: themes for how the site looks,
and [plugins](plugins/README.md) for everything else a site's code does.
An extension can be turned on and off, versioned, and reused on other
sites, so that's where to start. A site's own `src/` folder is there for
code that's only ever one site's, such as a client's custom build: see
[Site code in `src/`](plugins/site-code.md).

How a site's folders fit together is in
[Site layout](getting-started/site-layout.md).
