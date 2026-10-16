# Child themes

Templates belong to themes; your site has none of its own. To change
one piece of a theme, make a **child theme**: a theme whose `parent` is
the one you're changing. Anything the child doesn't include comes from
its parent, so it holds only the templates you change.

## Making one

```sh
bin/blush theme:new acme/notebook-child --parent=acme/notebook
bin/blush theme:activate acme/notebook-child
```

Then copy the template into the child's `views/` folder and edit the
copy. The child's copy wins. For example, to change the site footer,
copy the default theme's `views/partials/footer.php` to
`extensions/acme/notebook-child/views/partials/footer.php`.

## Styles, icons, and text

Only the active theme's `styles` are loaded, but a file the child
doesn't have is found in its parent. `theme:new` gives a child no
`style.css` and no `styles`, which means `["style.css"]`, so the
parent's `style.css` loads. To add styles on top, add your own file and
list both: `"styles": ["style.css", "child.css"]`. If the parent's
stylesheets have other names, list them the same way.

Icons work the same way (the child's `icons/`), and a child's catalogs
reword its parent's text (see [Translations](translations.md)).

The default footer ends with "Powered by Blush." To reword it, you don't
need to copy the template: its key is `powered_by` (see
[Your own wording](translations.md#your-own-wording)).

## Which file is in charge

Not sure which file is in charge of a view? Ask:

```sh
bin/blush theme:why partials/footer
```
