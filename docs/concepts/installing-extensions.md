# Installing extensions

An [extension](extensions.md) gets onto a site from a `.zip` in the
admin, from Composer, or by putting its folder in `extensions/`. Nothing
there is on until you turn it on.

## Installing from a zip

**Install Theme**, **Install Plugin**, **Install Icon Pack**, and
**Install Admin Theme** take a `.zip` of an extension's folder, up to 25
MB (or less, if PHP's upload limit is lower). The manifest
(`plugin.json`, `theme.json`, `icons.json`, or `admin-theme.json`, or a
`composer.json` of the kind's `type`) can be at the zip's root or inside
one folder, as GitHub's release zips have it. It's unpacked into
`extensions/` at the name in its manifest (or its `composer.json`):
`acme/hello` goes in `extensions/acme/hello`, whatever the zip or its
folder is called. Nothing is turned on: a plugin, icon pack, or admin
theme arrives off, and a theme inactive.

Blush checks the zip before anything is written, and installs nothing
when:

- it holds another kind of extension (the message names the screen it
  belongs on), more than one kind, or none;
- an extension of another kind has its name;
- a file in it would land outside its folder, or is a symbolic link, or
  it holds more than 5,000 files or 100 MB unpacked;
- its manifest doesn't pass, or its namespace is reserved or another
  installed extension's;
- Composer installed an extension with its name (Composer updates it);
- it's a plugin or theme with a PHP file that doesn't parse (checked
  without running it).

An extension installed from a zip has no `vendor/` folder of its own.
One that [requires](../plugins/requirements.md) a library
(`"guzzlehttp/guzzle": "^7.0"`) is installed anyway, but can't run until
the site's Composer installs that library
(`composer require guzzlehttp/guzzle`), and its details say so.

Installing needs the kind's `extensions.{kind}.install` capability, and
replacing its `extensions.{kind}.update` (see
[Capabilities](accounts/README.md)). The server's PHP needs the `zip`
extension, and the web server must be able to write to `extensions/`;
the Install modal says so when it can't.

## Replacing and rolling back

A zip of an extension that's already installed offers to replace it,
naming both versions. Replacing swaps the folder and changes nothing
else: an active theme stays active, and a plugin that's on stays on.
The old folder is kept in `storage/backups/{vendor}/{name}`, one per
extension: the next replace overwrites it, and deleting the extension
deletes it. A folder that's a git checkout isn't replaced; update it
with git.

The extension's details screen shows the version that was kept, with
**Roll back to {version}**, which asks first and then swaps it in. The
version it replaces is kept in its place, so the message's **Undo**, or
rolling back again, switches back. An earlier version that wouldn't run
on the site now isn't rolled back to: a plugin whose requirements aren't
met, a theme or icon pack in use whose requirements aren't, or a theme
in use whose parent theme isn't installed. **Discard** removes the kept
version. Rolling back needs the kind's `extensions.{kind}.update`
capability, and discarding its `extensions.{kind}.delete`.

## Turning extensions on

Nothing in `extensions/` is on just because it's there. A plugin or an
icon pack there is off until it's named, either by the admin (its switch
on **Extend → Plugins** or **Extend → Icon Packs**) or in config:

```php
// config/plugins.php
return new Blush\Plugin\PluginConfig(enabled: ['acme/hello']);

// config/icons.php
return new Blush\Icon\IconConfig(enabled: ['acme/brands']);
```

Config only ever lists what's on; there's no list of what's off.

A plugin or icon pack installed with Composer is on by default:
installing it is the decision to use it.

Once the admin has turned something on or off, it saves its own list in
`user/data/settings/plugins.json` (or `icons.json`), used in place of
the config file's. That list names everything that's on, Composer's
included, so you can turn a Composer plugin off there. It starts from
what was already on, so the first switch changes only that one. From
then on, anything the list doesn't name is off, including a plugin
Composer installs later: turn it on in the admin. **Use
`config/plugins.php`'s list** (or `config/icons.php`'s) on the screen
goes back to the defaults.

Themes work as they always have: one is active, set in
`config/theme.php` or on **Extend → Themes**. Admin themes are turned on
the same way, in `config/admin-themes.php` (see
[Admin themes](../admin-themes/README.md)).
