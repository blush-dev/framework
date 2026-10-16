# Themes

A theme controls how your site looks. It never owns your content or your
URLs, so you can switch themes at any time without breaking anything.

Blush ships with a **default theme**: plain, accessible, light and dark,
with no build step. A theme is expected to include its own templates;
anything it leaves out comes from Blush's own plain versions, not from
the default theme. To build on the default theme, name it as your
theme's `parent`.

There are three ways to change how your site looks, from least to most
work:

1. [Adjust the active theme's settings](settings.md) with a data file.
2. [Override a few templates](child-themes.md) with a child theme.
3. [Build your own theme](creating-a-theme.md).

## Using themes

| Page | What it covers |
|---|---|
| [Managing themes](managing-themes.md) | Listing, activating, and previewing themes |
| [Theme settings](settings.md) | Setting a theme's options, and an entry's own classes and stylesheet |
| [Site settings in templates](site-settings.md) | Reading the site's own settings with `$site->setting()` |
| [Child themes](child-themes.md) | Changing a few of a theme's templates |
| [Going live with a theme](going-live.md) | Copying theme files into `public/` |

## Building a theme

| Page | What it covers |
|---|---|
| [Creating a theme](creating-a-theme.md) | `theme:new`, Blush's own templates, and a theme's folders |
| [`theme.json`](theme-json.md) | Every manifest key, the admin's preview, and autocomplete |
| [Templates](templates.md) | Template calls, escaping, dates, and the objects every template gets |
| [Entries in templates](entries.md) | What an entry offers: its fields, relations, excerpt, and parents |
| [The head and the foot](head-and-foot.md) | What Blush puts in `<head>`, and adding to it and to the end of the page |
| [Which template is used](template-hierarchy.md) | The order Blush tries templates in, for every kind of page |
| [Templates for single entries](entry-templates.md) | Offering templates in the admin's **Template** row |
| [Media in templates](media.md) | Printing an entry's images and other files |
| [Scripts and styles](scripts-and-styles.md) | Loading registered scripts and styles, and a theme's `assets` |
| [Styling the player](player.md) | The audio and video player's custom properties |
| [Translations](translations.md) | Your theme's text, its catalogs, and rewording anyone's text |
| [Pagination](pagination.md) | A listing's page links |
| [Caching slow parts](caching.md) | Keeping slow HTML with `$template->cache()` |
| [Avatars](avatars.md) | A profile's picture or initials |
| [Blocks and components](blocks-and-components.md) | Giving blocks a look, and your theme's own components |
| [BEM classes](bem.md) | Building BEM class names anywhere |
| [Bleed](bleed.md) | Wide and full-width elements |
| [Image variants](image-variants.md) | Classes your theme offers images |
| [Building assets with Vite](vite.md) | Sass and bundled JavaScript |
| [Checking a theme](checking-a-theme.md) | `theme:check` |
