# Callouts, galleries, figures, and embeds

Four built-in [blocks](README.md) for setting things apart in your
content: a note, a set of images, a captioned figure, and a video or
post from another site.

| Block | Example                                                | Props                                                                                                                                                                                                                                                                  |
|-----------|--------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `callout` | `:::callout[Title]{variant=info}` … `:::`              | [Variants](../../features/blocks/variants.md): `info`, `tip`, `warning`, or `danger`; without one, it's a plain note. The label is the title.                                                                                                                                                  |
| `gallery` | `:::gallery{columns=3}` … `:::`                        | `columns`: 1 to 6 (default 3). `layout`: `flex` (default; rows that grow to fill the width), `grid` (even columns), or `masonry` (columns that pack images of any height; an even grid in browsers without CSS grid lanes). Wrap images in it, one to a line; each is a figure. |
| `figure`  | `:::figure[Caption]` … `:::`                           | Sets anything apart with a caption (the label): an image, a table, a code block, a quote. An image on its own line inside it is just the image. For a lone image, you don't need it: an image on its own line is already a figure, with its quoted title as the caption. |
| `embed`   | `::embed[Caption]{url="https://youtu.be/…" title="…"}` | `url`, `title`, `alt`. See below. |

## Embeds

`::embed` takes `url`, `title`, and `alt`. YouTube, Vimeo, TED, CodePen,
Spotify, SoundCloud, Twitch, and TikTok (and
[providers you add](../../reference/configuration/embeds.md)) play in a
frame at the embed's real shape (Spotify's and SoundCloud's players,
full width at their own height; TikTok's tall), named by its own title;
YouTube and Vimeo in privacy-friendly mode, from the URL's start time
(`?t=90`).

- A Flickr photo shows as the image, linked to its page, with `alt` as
  its alt text (else its title) and "Photo by … on Flickr" after the
  caption.
- A post on X or Reddit shows as a quote of it, which the site's script
  (loaded once on the page) draws as the post; in feeds, or without
  JavaScript, the quote stays.
- Twitch plays only on the site's own address (`APP_URL`).

Each site plays only once it's turned on (Settings → Writing, or
`enabled` in [`config/embed.php`](../../reference/configuration/embeds.md));
none is by default. Any other URL, or one from a site that's off,
becomes a link.

For a provider that needs code, see
[Embed providers](../../features/embed-providers.md).
