# Writing settings

**Settings → Writing** sets how Markdown renders, what raw HTML does,
and which sites' links embed.

## Markdown

These are all on by default:

- **Mentions:** `@name` links to the profile with that slug once it's
  published; anyone else's stays text.
- **Smart punctuation:** curly quotes, dashes, and ellipses.
- **Heading anchors:** each heading links to itself.
- **Images as figures:** an image on a line of its own becomes a
  figure, its title the caption.

## Raw HTML

**Raw HTML** is what HTML written in content does on the page, whoever
wrote it: **Allowed** (the default), **Filtered** (script, frames,
forms, and styles show as text, and `javascript:` links lose their
address), or **Shown as Text**. Who may add HTML in the editor is up to
their role (see [Capabilities](../../concepts/accounts/README.md)).

## Embeds

Under **Embeds**, a button for each site whose links play on the page,
A to Z (CodePen, Flickr, Reddit, SoundCloud, Spotify, TED, TikTok,
Twitch, Vimeo, X, YouTube, and any your site adds), with its logo;
press one to turn it on or off, or use **Turn All On** and **Turn All
Off**. Hover a site to see the addresses it embeds. Every site is off
until you turn it on, and a site turned off keeps its links as links.
They're saved together as `embed.enabled`, over `config/embed.php`'s
`enabled`.
