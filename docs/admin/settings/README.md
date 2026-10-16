# Settings

With `site.settings`, the **Settings** section has a screen for each
group of the site's settings:

| Screen | What it sets |
|---|---|
| [General](general.md) | Name, description, email, language, time zone, date formats, and accounts |
| [Reading](reading.md) | The homepage and feeds |
| [Writing](writing.md) | How Markdown renders, raw HTML, and embeds |
| [Media](media.md) | What may be uploaded, and where it goes |
| [Email](email.md) | Who the site's email is from |
| [Addresses and Search](addresses-and-search.md) | Trailing slashes, the sitemap, and `robots.txt` |
| [AI](ai.md) | Markdown copies, `llms.txt`, AI crawlers, and the MCP server |
| [API](api.md) | The API and who can read it |
| [System](system.md) | Where types come from, caching, and publishing (shown only) |

## Saving settings

Change something and a bar at the bottom counts your unsaved changes,
with **Revert** and **Save Changes**; leaving the screen with changes
unsaved asks first. Saving keeps them in `user/data/settings/` (a file
for each group, such as `feed.json`), where
they win over `config/` and `.env`, and they take effect on the next
page load, with nothing to compile. Each setting is a row: its name, its
control (a yes or no is a switch saying On or Off), and, beside it, what
it does and whether it's saved there or comes from its config file. A saved one has
**Use `config/…`'s value**, which goes back to the config's value when
you save.

## Settings that are only shown

A setting that's only shown says when it's still the default and names
the file it's set in, and a risky one is flagged, such as detailed error
pages on a live site. Secrets are never shown, only whether one is set.
Those settings live in `config/` and `.env` (see
[Configuration](../../concepts/configuration.md)); after changing them on a site
you've compiled, run `bin/blush cache:compile` again.
