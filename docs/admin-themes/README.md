# Admin themes

An admin theme changes how the admin looks: its colors, fonts, type
sizes, corner radii, and how roomy its rows are. It can't change the
admin's layout or what it does. It's a skin over the admin, not a new
admin.

Blush comes with six admin themes, all on the platform's own fonts:

- **Neutral** (`blush/neutral`): cool gray with a cobalt accent. This is
  the admin's own look, and what everyone sees until they choose another.
- **Library** (`blush/library`): cool parchment, a bottle-green accent,
  and titles in the system's serif.
- **Cyanotype** (`blush/cyanotype`): Prussian blue on sun-faded paper,
  and the print itself in the dark.
- **Dune** (`blush/dune`): sand in late sun, with the twilight indigo of
  the dunes' shadow side.
- **Observatory** (`blush/observatory`): a star chart on paper, the
  night sky in the dark, and a nebula magenta accent.
- **Ridgeline** (`blush/ridgeline`): sunset over the mountains, golden
  hour and the ridge in silhouette.

Each account chooses its own theme under **Theme and Color Scheme** on
[Your Account](../admin/users/your-account.md). The site's default is what
everyone else sees, including the sign-in screen.

## Using admin themes

| Page | What it covers |
|---|---|
| [Choosing the site's default](choosing-the-default.md) | The theme accounts see until they choose their own |
| [Turning admin themes on](turning-on.md) | Which themes accounts may choose, and installing one |

## Making an admin theme

| Page | What it covers |
|---|---|
| [Making an admin theme](making-an-admin-theme.md) | The folder and `admin-theme.json` |
| [`tokens.json`](tokens-json.md) | Setting the admin's design tokens: colors and fonts |
| [Every token](every-token.md) | Each token, its CSS custom property, and its range; the chrome, textures, and shadows |
| [Checking a theme](checking.md) | `admin-theme:check` |
| [Autocomplete in your editor](autocomplete.md) | The JSON Schemas for both files |
