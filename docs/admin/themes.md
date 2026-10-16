# Themes

With `extensions.themes.view`, **Extend → Themes** shows every installed
[theme](../themes/README.md) as a card, and switches the site from one
to another.

## The cards

The active one comes first, marked **Active**. Each card has a
sketch of a page in the theme's colors (from its `theme.json`'s
`preview`, see [theme.json](../themes/theme-json.md)),
its label, version, and description, where it's installed, and the
theme it falls back to, if it has a parent. The icons above the cards switch to a compact
list, a row for each theme; the choice is kept in this browser, and
Icon Packs and Admin Themes share it.

## Filters

Above the list (on Plugins, Icon Packs, and Admin Themes too), **Filter themes** finds
a theme by its label, name, description, or
[keywords](../plugins/creating-a-plugin.md); **Status** narrows it to the active
theme, the inactive ones, or those that need attention (one that can't
be activated or isn't running, a broken one, or an abandoned one), and
**Source** to Composer's, folders in `extensions/`, or the one that
ships with Blush. The filters are in the address, so a filtered list
can be bookmarked; the line under them says how many match, with
**Clear Filters**.

## Activating and deleting

- **Activate** asks first, naming any extensions it would stop, then
  switches the site to that theme. It's
  saved in `user/data/settings/theme.json`, over `config/theme.php`, and takes
  effect on the next page load. If it doesn't go through, the card says
  so; the site keeps the theme it had.
- A theme that falls back to a theme that isn't installed says
  **Can't activate**, with what to do, and so does one whose
  [requirements](../plugins/requirements.md) (or those of a theme it
  falls back to) aren't met, saying what it needs. So does a theme whose
  `theme.json` is broken (or whose namespace another extension has),
  listed by where it was found, with the reason.
- When the active theme's requirements stop being met (a plugin it
  needs is turned off, say), its card says **Not running**, with what
  it needs: visitors see the default theme until that's fixed.
- The **⋯** menu opens the theme's details, copies its folder path or
  its `theme:activate` command, and in development, **Preview on the site** opens the site
  with that theme (`?theme={name}`, see [Themes](../themes/README.md)).
- **Delete Theme** (in the menu) removes a theme's folder from
  `extensions/`, after asking. The active theme, and any theme it falls
  back to, can't be deleted; activate another first. Themes installed
  with Composer are removed with `composer remove`, and the default
  theme can't be removed. A theme that fell back to the one you deleted
  can't be activated until it's pointed at one that's installed.

When the active theme was set here, the note under the cards has
**Use `config/theme.php`'s theme**, which removes the saved one.
Running `bin/blush theme:activate {name}` also clears it.

## A theme's details

A theme's label, or **Theme details** in its menu, opens its details:
**Appearance**, its preview in both its light and dark colors, each with
its palette's six colors listed under it; then **Details** (its name,
version, authors, license, where common open source licenses link to
their text, namespace, type, and folder, then its links and where to
fund it) beside **Dependencies**: the theme it falls back to (its
`parent`, if it has one), its
requirements (checked as if it were active), conflicts, and the rest of
its package links, then, under **What others say about it**, the themes
that fall back to it and the extensions that require it. **Activate** and
**Delete Theme** are there too; a theme the site uses says why it can't
be deleted, and a Composer theme gives the `composer remove` command.
Broken themes have no details page.

**Install Theme** installs one from a `.zip`: see
[Installing extensions](installing-extensions.md).

How the admin itself looks is set per account, on
[Your Account](users/your-account.md).
