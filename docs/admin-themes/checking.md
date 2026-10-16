# Checking an admin theme

```sh
bin/blush admin-theme:check
bin/blush admin-theme:check acme/harbor
```

This reads every admin theme (or one) and reports anything wrong with
its manifest or `tokens.json`, [requirements](../plugins/requirements.md)
that aren't met, and colors that are hard to read: text on the
background and surfaces, accent text on the accent, each status
color on its own soft fill, and the
chrome's text and accent on the chrome, in both color schemes. Low contrast is a
warning, not an error, so a theme with it still works.
