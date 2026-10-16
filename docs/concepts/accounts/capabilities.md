# Capabilities

A [role](roles.md) is a list of **capabilities**: each one is something
an account holding the role may do.

## Content

What a role may do to entries is set for each content type, with the
type's name in the middle (`content.post.edit` is editing your own
posts):

| Capability | Allows |
|---|---|
| `content.{type}.create` | Creating entries of the type |
| `content.{type}.edit`, `content.{type}.edit.others` | Editing your own entries, and others' |
| `content.{type}.publish`, `content.{type}.publish.others` | Publishing your own entries, and others' |
| `content.{type}.delete`, `content.{type}.delete.others` | Deleting your own entries, and others' |

Use `*` for the type to grant it on every type, including types added
later: `content.*.edit`. The built-in roles do. A `.others` capability
only works alongside its own (`content.post.edit.others` needs
`content.post.edit`). The admin shows a type only to accounts that can
edit its entries.

## Media

Media's are by kind for uploading, and by whose file it is for the
rest. A file is the account's that uploaded it; files from before
uploads were recorded, or added by hand, are anyone's:

| Capability | Allows |
|---|---|
| `media.{kind}.upload` | Uploading files of a kind: `image`, `video`, `audio`, `document`, or `file` (other files); `media.*.upload` is every kind |
| `media.edit`, `media.edit.others` | Changing the details (alt text, caption, and so on) of your own files, and anyone's |
| `media.delete`, `media.delete.others` | Deleting your own files, and anyone's |

As with content, `.others` only works alongside its own. The Media
screen shows to anyone with one of them; the editor's media picker
browses the library for anyone who can edit entries. Editors have them
all; Authors upload every kind and change and delete their own files;
Contributors upload images and change their own.

## Raw HTML

Raw HTML in an entry's body (tags, not HTML inside code) is by how
much of it an account may add in the admin. Files edited on disk aren't
checked:

| Capability | Allows |
|---|---|
| `html.allowed` | Adding HTML from the allowed list: text-level tags such as `<kbd>`, `<abbr>`, `<sup>`, and `<span>`, blocks such as `<div>`, `<details>`, and tables, and pictures, audio, and video, with their own attributes plus `class`, `id`, `title`, `lang`, `dir`, `role`, `aria-*`, and `data-*`. No `style` |
| `html.unfiltered` | Adding any HTML but what's always refused |

Always refused, whoever you are: `<script>`, `<style>`, `<iframe>` and
other frames, `<object>`, `<embed>`, forms and their fields, `<svg>`
and `<math>`, `<meta>`, `<link>`, `<base>`, and `<template>`; event
attributes such as `onclick`; and `javascript:`, `vbscript:`, `file:`,
and (except for pictures) `data:` addresses, in HTML or in a Markdown
link. Use a [block](../blocks/README.md) for embeds and icons. Without
either capability, no HTML can be added. Only what a save adds counts,
so anyone who can edit an entry can change its words, or take HTML out.
Owners and Administrators have `html.unfiltered`, and Editors
`html.allowed`.

## The site

The rest are for the whole site:

| Capability | Allows |
|---|---|
| `menus.edit` | Editing menus |
| `site.publish` | Publishing the site |
| `cache.clear` | Clearing caches |
| `site.settings` | Changing site settings |
| `site.logs` | Reading the site's log on the Tools screen |
| `site.activity` | Seeing every change to entries and media on Tools → Activity, with Undo (owners, administrators, and editors, by default). Anyone sees and undoes the changes to an entry they may edit. |
| `site.backups` | Seeing, making, downloading, noting, and deleting the site's [backups](../../running/backups.md) on Tools → Backups (owners, by default, since backups hold password hashes) |
| `site.backups.restore` | [Restoring](../../running/restoring-backups.md) a backup, with `site.backups` (owners, by default) |
| `site.redirects` | Adding, changing, and deleting redirects on the Redirects screen. Renaming or moving an entry adds its own redirect without it. |
| `site.jobs` | Seeing every background job and scheduled task on the Tools screen, with Retry, Delete, and Run Now. Anyone can follow the jobs they start. |
| `site.health` | Seeing Site Health, and its fixes for content and media files (owners only, by default) |
| `accounts.view` | Seeing accounts and roles (each account action below also needs it) |
| `accounts.create` | Creating accounts, with their first roles |
| `accounts.edit` | Changing an account's name and profile, and making and emailing password links |
| `accounts.roles` | Giving and taking roles |
| `accounts.suspend` | Suspending and reinstating accounts |
| `accounts.delete` | Removing accounts |
| `roles.manage` | Making, changing, resetting, and deleting roles |
| `api.tokens` | Making [API tokens](../../features/api/tokens.md) for your own account (owners and administrators, by default) |
| `api.connect` | Connecting [AI apps](../../features/mcp/connecting-an-app.md) to act as you (every role but Member, by default) |

## Extensions

Each kind of extension has its own, with the kind in the middle:
`themes`, `plugins`, `icon-packs`, or `admin-themes`
(`extensions.plugins.delete` is deleting plugins). For admin themes,
`activate` also chooses the site's admin theme:

| Capability | Allows |
|---|---|
| `extensions.{kind}.view` | Seeing the kind's screen (each action below also needs it) |
| `extensions.{kind}.install` | Installing them from a `.zip` |
| `extensions.{kind}.update` | Replacing an installed one with a newer `.zip`, and rolling back to the version that was kept |
| `extensions.{kind}.activate` | Activating a theme, or turning plugins and icon packs on and off |
| `extensions.{kind}.delete` | Deleting them, and discarding a kept version |

Use `*` for the kind to grant it on every kind: `extensions.*.view`.
Only the Owner and the Administrator have them built in, and the
Administrator doesn't install, update, or delete plugins or themes:
those put code on the site, and code can do anything, so whoever has
them can do anything an owner can. Give them to an Administrator only
if you'd make them an owner.

## From plugins

Plugins can add their own (see [Capabilities](../../features/capabilities.md)).
The Owner has them as soon as they're added; give them to other roles
yourself, the Administrator included.
