# Menus in themes

A theme declares the menu locations it shows, and prints what each
location shows from its templates. For making menus and assigning them
to locations, see [Menus](../concepts/menus.md).

## Declaring locations

Declare the locations your theme shows in `theme.json`. Each is a label,
or an object:

```json
"menus": {
	"primary": "Primary",
	"mega": {
		"label": "Main",
		"depth": 2,
		"fields": {
			"columns": { "type": "number", "integer": true, "default": 1, "label": "Columns" }
		}
	},
	"social": {
		"label": "Social",
		"items": [
			{ "url": "https://github.com/example", "label": "GitHub", "icon": "mytheme/github" }
		]
	}
}
```

- A location's label also names its `<nav>` for screen readers, so
  keep it short and leave out "navigation" or "menu" ("Primary", not
  "Primary navigation").
- `depth` is how many levels deep the location shows (1 is the top
  level); deeper items are left out.
- `fields` are extra options for each item, with the same field types as
  [custom fields](../concepts/custom-fields.md). Read one with
  `$item->field('columns')`. A key that isn't a built-in option or a
  declared field is reported as a problem.
- `items` are the location's default menu, written as a menu's items
  are, shown until the site assigns the location a menu. Leave it out to
  show nothing. Link to entries by `{type}/{key}`; your theme can't know
  a site's ids.

A child theme inherits its parent's locations, and can redeclare one to
change it.

The site's assignments are kept in your theme's settings under `menus`,
so none of your [settings](../themes/settings.md) can be named `menus`.

## Printing a menu

The `menu` block prints what a location shows with accessible
markup: a `<nav>` named by the menu's label, nested lists, and
`aria-current="page"` on the link to the current page.

```php
<?= $template->block('menu', location: 'primary') ?>
```

It prints nothing when the location has nothing to show. Its classes
start with `block-menu` (`block-menu--primary`,
`block-menu__item--current`, `block-menu__link`). Items with
children get a toggle button, hidden, for a dropdown: your script can
show it and open and close the submenu it controls (`aria-controls`),
updating `aria-expanded`. Without a script, submenus stay open.

Blush's `menu` block draws its nested lists inside its own template, so
to change the lists, override the whole block (see
[How a block draws itself](blocks/rendering.md)).

## Markup of your own

For markup of your own, get what a location shows with
`$template->menu()`:

```php
<?php if ($menu = $template->menu('social')) : ?>
	<ul class="social">
		<?php foreach ($menu->items as $item) : ?>
			<li class="<?= attr($item->class) ?>">
				<a href="<?= url($item->url) ?>" <?= $item->ariaCurrent() ?>>
					<?= $template->block('icon', name: $item->icon) ?>
					<span><?= e($item->label) ?></span>
				</a>
			</li>
		<?php endforeach ?>
	</ul>
<?php endif ?>
```

Each item has `label`, `url` (`null` for a heading), `icon`,
`description`, `image`, `badge`, `class`, `rel`, `children`, and
`field($name)`. `current` is true for the current page's item, and
`ancestor` for the items above it. `ariaCurrent()` prints the attribute
that says so: `aria-current="page"` on the current page's item,
`aria-current="true"` on the items above it, and nothing on the rest.
The menu's `name` is the site menu's, and `''` for your default.

## Brand icons

For a social menu, ship the brand icons you need with your theme, in its
`icons/` folder (`icons/github.svg` is `mytheme/github`); Blush doesn't
include brand logos.

For links a plugin adds to menus, see [Menu links](menu-links.md).
