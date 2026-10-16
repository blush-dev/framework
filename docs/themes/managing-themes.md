# Managing themes

List the themes a site has installed, switch between them, and preview
one before you switch.

```sh
bin/blush theme:list                   # installed themes, and which is active
bin/blush theme:activate acme/notebook # switch themes
```

## Where themes live

A theme is known by its **name**, `vendor/name` (`acme/notebook`), which
its `theme.json` gives. Themes live in `extensions/{vendor}/{name}/`, a
folder at their name (`extensions/acme/notebook/`), and each can be its
own git repository. They can also be installed with Composer (package
type `blush-theme`), where the theme's name is the package's. A theme
can do without `theme.json` when its `composer.json` has the type
`blush-theme`: its keys go under `extra.blush` there (see
[Extensions](../concepts/extensions.md)). If two share a name, the one
in `extensions/` wins. The framework's default theme is `blush/default`.

## The active theme

The active theme is set in `config/theme.php`, which `theme:activate`
writes for you:

```php
return new ThemeConfig(active: 'acme/notebook');
```

A theme activated in the admin's [Themes screen](../admin/themes.md) is
saved in `user/data/settings/theme.json` instead, and wins over
`config/theme.php`. `theme:activate` clears that saved theme, so the
command always takes effect.

## Broken themes

A theme whose manifest is broken is listed by `theme:list` (and the
admin's Themes screen) by where it was found, such as
`extensions/acme/notebook`, with the reason. A theme in a folder that
isn't its name is broken too.

## Previewing a theme

In development, add `?theme=acme/notebook` to any URL to preview another
theme. A previewed theme's provider runs as it would if the theme were
active, as long as its requirements are met. The active theme's provider
has run too, so what it registers stays, and routes the previewed
theme's provider adds don't take effect until it's activated.
