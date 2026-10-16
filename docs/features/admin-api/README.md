# The admin's API

Blush's admin is one front end for the admin's API, and you can swap in
your own. This page covers building one and how the API works; the
pages below list its requests by area.

## Building your own admin

Build it with Vite (with `build.manifest` on) and point `app` at the
build folder, in `config/admin.php`:

```php
return new AdminConfig(enabled: true, app: __DIR__ . '/../public/my-admin');
```

Blush puts the entry's script and styles on the admin's page, and serves
the build folder's files at `/admin/assets/` (scripts, styles, images,
and fonts; never `.vite/`). Build with plain file names, as the
[theme build](../../themes/README.md) does: Blush adds `?v=` and a hash of each
file's contents to its URL, so browsers cache the files until they
change. The page also includes a JSON block (`#blush-admin-config`)
with the admin's path, the API's path, the site's name, and the
signed-in account's `colorScheme` and `adminTheme` (`null` when no one
is signed in, or when the account follows the site's default), and
`adminThemes`: the site's `default` and the `offered` themes, each with
its `name`, `label`, `description`, `stylesheet` (`null` for Neutral),
and `preview` colors. For a light or dark account, `<html>` also carries
`data-color-scheme`, and for any admin theme but Neutral (the account's,
or the site's default), `data-admin-theme` with its name, and the page
links its stylesheet (`#blush-admin-theme`), served from
`/admin/assets/admin-themes/{vendor}/{name}/theme.css` with its fonts
beside it.

## How the API works

The API is JSON under `/admin/api`, and uses the session cookie. Once
signed in, send the token from `session` or `login` in an
`X-CSRF-Token` header with every `POST`, `PATCH`, and `DELETE`. Errors
are JSON too: `{"error": "…"}`, with a 400, 401, 403, 404, 413, 422, or
429 status.

What each request needs follows the account's
[capabilities](../../concepts/accounts/capabilities.md).

## Requests by area

| Page | What it covers |
|---|---|
| [Sessions and your own account](session.md) | Signing in and out, your password, preferences, name, and email, and password links |
| [Dashboard and counts](dashboard.md) | The Home screen and the section panel's counts |
| [Tools and logs](tools-and-logs.md) | The Tools screen's buttons, and the site's log |
| [Backups](backups.md) | Making, scheduling, uploading, and restoring backups |
| [Accounts, profiles, and roles](accounts-and-roles.md) | The Users screens |
| [Extensions](extensions.md) | Themes, plugins, icon packs, and admin themes |
| [Settings](settings.md) | The Settings screens |
| [Content types](types.md) | Listing, creating, and changing types |
| [Workflows and statuses](workflows.md) | The Workflows screen |
| [Relationships](relations.md) | Relations between types |
| [Fields](fields.md) | Controls, field types, and the field library |
| [Site Health](site-health.md) | Checks, problems, and fixes |
| [Listing entries](listing-entries.md) | The entry lists |
| [Editing entries](editing-entries.md) | Reading, creating, changing, trashing, and duplicating entries, preview links, and bulk changes |
| [Blocks and icons](blocks-and-icons.md) | What the editor's inserters offer |
| [References](references.md) | What the editor's pickers can point at |
| [Media](media.md) | The media library |
| [Menus](menus.md) | Menus and menu locations |
| [Redirects](redirects.md) | The Redirects screen |
