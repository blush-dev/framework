# Theme settings

To adjust a theme without touching its files, set the options it offers
in a data file. A single entry can change its own look too.

## A theme's options

Set the options a theme offers, if any, in its own group of settings, a
file named for the theme with `__` for its `/`. For `acme/notebook`,
`user/data/settings/acme__notebook.json`:

```json
{
	"layout": "grid"
}
```

Each theme keeps its own, so switching themes and back keeps what you
set. The default theme has none.

A theme declares its options under `settings` in
[`theme.json`](theme-json.md), and reads them in templates with
`$theme->setting('name')` (see [Templates](templates.md)).

## An entry's own look

A single entry can change its own look with front matter:

```yaml
class: wide-page             # extra classes on <body>
stylesheet: /media/zine.css  # an extra stylesheet
```
