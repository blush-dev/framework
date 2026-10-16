# Scripts and styles

Besides your theme's own `styles` and `scripts`, which every page loads,
a theme can load scripts and styles that Blush, plugins, and themes
register by name, only on the pages that need them.

## Loading one by its handle

Blush, plugins, and themes register scripts and styles by name, a
handle such as `core/player`, and pages load them only when they need
them. Ask for one in a template with `$template->enqueue('acme/gallery')`;
it prints in the `<head>` once, after anything it needs, however many
times it's asked for. Blocks and components ask for their own, so the
audio and video player (`core/player`) loads only on pages that play
something.

The head is printed once the whole page has rendered, so a template
can add to it anywhere, even in your footer partial after the layout
has printed `$template->head()`.

## Scripts in the foot

Scripts that should load after the page's markup go in the foot
instead, where your layout prints `$template->foot()`:
`$template->foot()->script($url)`, or
`$template->foot()->inlineScript('id', $js)`. The head and the foot
share their tags, so a script asked for in both prints once, in the
head, and `has()` and `remove()` on either find tags in both.

Inline code prints between its tags as written, trimmed, so a line of
code prints as `<script id="scheme">…</script>`; code over several
lines keeps its line breaks. Code and data that belong to a registered
script can be tied to it, as
[Scripts and styles](../features/scripts-and-styles.md) shows.

## Registering your theme's own

Your theme registers its own the same way plugins do: in its
provider's `boot()`, with `from` set to your theme's name, as
[Scripts and styles](../features/scripts-and-styles.md) shows. That's the
place for them when your theme has a provider. A theme without one can
list them under `assets` in `theme.json` instead, each handle with its
`styles`, `scripts`, and the handles it `requires`:

```json
"assets": {
	"acme/lightbox": {
		"styles": ["css/lightbox.css"],
		"scripts": [
			{ "path": "js/lightbox.js", "footer": true, "attributes": { "type": "module" } },
			"https://cdn.example.com/zoom.js"
		],
		"requires": ["core/player"]
	}
}
```

A file is a path inside your theme or a full URL, or an object with
that `path`, its `attributes`, and, for a script, `footer`. Themes
register theirs after plugins, a parent's before its child's, and a
theme's provider after its `theme.json`, so a provider's handle wins;
registrations from [the site's own providers](../plugins/site-code.md)
win over any theme's. `theme.json`'s `styles` and `scripts` stay as they
are: files every page loads.

To restyle or replace the audio and video player, see
[Styling the player](player.md).
