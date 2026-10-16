# Labels and translations

A block's label, description, prop names, and variants are
translatable text, kept in the translation catalog of the plugin that
owns its namespace, under `blocks.{name}`:

```json
{
	"@@locale": "en",
	"@@domain": "acme/hello",
	"blocks": {
		"badge": {
			"label": "Badge",
			"description": "A short word set apart from the text.",
			"props": {
				"tone": {
					"label": "Tone",
					"choices": { "info": "Info", "new": "New", "tip": "Tip" }
				}
			},
			"variants": {
				"outline": {
					"label": "Outline",
					"description": "An outline instead of a fill."
				}
			}
		}
	}
}
```

Blush's own blocks have theirs in Blush's catalog. Any of them can
be reworded by your site's catalogs in `user/lang/`
(see [Translations in a theme](../../themes/translations.md)). A block that
translates text as it renders (`$this->messages->t()` in its class, or its own
template's `$messages->t()`) reads its own plugin's catalogs, then
Blush's; a theme's catalog doesn't reword it. See
[Translations](../translations.md) for catalogs and `t()`.

A variant someone else adds to your block, such as a theme's
`bordered` callout, has its text in their catalog, under the same
`blocks.{name}.variants.{variant}` key. `theme:check --strict`
notes the variants in a theme's `theme.json` that have no label.

Without a label, one is made from the name (`recent-posts` becomes
"Recent posts").

Icons' labels go in the same catalog, under `icons.{name}.label` (see
[Icons](../../concepts/blocks/icons.md)).
