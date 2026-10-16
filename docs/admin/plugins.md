# Plugins

With `extensions.plugins.view`, **Extend → Plugins** lists every installed
[plugin](../plugins/README.md), and turns them on and off.

## The list

Each plugin is listed by label, with its name, version, and
description, and a switch; its mark is green while it runs. The filters
above the list work as on [Themes](themes.md#filters), with On and Off for its
status. Turning a plugin on or off takes effect
straight away, and a message says so, naming any other extensions
(plugins, the active theme, or icon packs) that started or stopped with
it (the ones that [require](../plugins/requirements.md) it). What a plugin adds shows on the screens it belongs to, not here.

## On and off

A plugin in `extensions/` is off until it's turned on here or named in
`config/plugins.php`'s `enabled` list; a Composer plugin is on. Once
you've used a switch, the list saved here names every plugin that's
on, so a Composer plugin can be turned off too, and one Composer
installs later starts off, saying why. A plugin whose requirements aren't met can't be
turned on, and says what it needs, such as "Needs Blush ^3.0 (this site
runs 2.1.0)." A broken plugin, whose manifest can't be read, is listed
by its folder (or Composer package) with what's wrong, and can't be
turned on until that's fixed; one in `extensions/` can be deleted. The switches are saved in `user/data/settings/plugins.json`, over
`config/plugins.php`'s `enabled` list; once they are, the note under the
list has **Use `config/plugins.php`'s list** to go back to it.

## A plugin's details

A plugin's name, or **Plugin details** in its **⋯** menu, opens its
details: **Details** (its name, version, who made it, license, where
common open source licenses link to their text, namespace, and folder,
then its links, such as homepage, documentation, and issues, without
email addresses, and where to fund it) beside **Dependencies**: each of
its requirements, checked against this site, a required extension
linked to its details, its conflicts, what it replaces, provides, and
suggests, then, under **What others say about it**, the extensions that
require it, conflict with it, replace it, or also provide it.

**Delete Plugin** removes a plugin's folder from
`extensions/` once it's off; a Composer plugin is removed with
`composer remove` instead. **Install Plugin** installs one from a `.zip`,
as **Install Theme** does (see [Installing extensions](installing-extensions.md));
it arrives turned off.
