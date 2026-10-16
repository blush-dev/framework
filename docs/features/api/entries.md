# Entries in the API

`/api/v1/entries` lists published entries of every public type, and
`/api/v1/entries/{id}` answers one. Each entry looks like this:

```json
{
	"id": "0199a1b4-…",
	"type": "post",
	"title": "Hello",
	"subtitle": "",
	"slug": "hello",
	"status": "published",
	"date": "2026-10-01T14:00:00Z",
	"updated": "2026-10-02T09:12:00Z",
	"expires": null,
	"url": "https://example.com/archives/hello",
	"excerpt": "The first words of the post…",
	"image": { "id": "0198…", "url": "https://example.com/media/2026/10/hello.jpg", "alt": "A lake", "width": 1200, "height": 800 },
	"fields": { "mood": "happy" },
	"relations": {
		"tags": [ { "id": "0199…", "type": "tag", "title": "Cooking", "url": "https://example.com/tags/cooking" } ]
	},
	"body": "<p>…</p>",
	"assets": { "styles": [], "scripts": [] }
}
```

- **Dates** are in UTC. Your site's time zone is in `/api/v1/site`.
  **`expires`** is there for types with
  [expiry](../../concepts/status-and-visibility.md): when the entry
  comes off the site, or `null`. An expired entry is off the site, so
  only a [token](tokens.md) that may edit it reads it
  (`?status=expired`).
- **`url`** is there for types whose entries have pages. **`parent`**
  is there for pages and other types that nest: the page above it, or
  `null`.
- **`excerpt`** is plain text: the summary, or the start of the body.
- **`fields`** holds the fields the entry's
  [type declares](../../concepts/custom-fields.md), each one, `null`
  when it's empty. Front matter the type doesn't declare isn't sent. A
  media field is the file, as `image` is; writes take its id, and the
  entry keeps the id.
- **`relations`** lists what the entry links to, by the relationship's
  name, in order. A link to something the site doesn't show (a draft
  tag) is left out.
- **`body`** is the entry rendered as on your site. `assets` lists the
  scripts and stylesheets it needs, such as the audio player's, so a
  front end can load them.

To narrow a list, or find the entry at an address, see
[Finding entries](finding-entries.md).

## The body

A single entry has its body as HTML. A list leaves bodies out, unless
you ask with `body`:

- `?body=html`: rendered as on your site, with full addresses.
- `?body=markdown`: as you wrote it, with full addresses for links and
  media (as the
  [Markdown copies](../../reference/configuration/README.md) are).
  Blocks stay as written.
- `?body=none`: no body.

## Including what an entry links to

Links to other entries are small: their id, type, title, and address.
Media is its id, address, alt text, and size. Ask for the whole thing
with `include`, and it takes the link's place:

- `?include=media`: every media file in full (caption, sizes, and the
  rest).
- `?include=relations`: every linked entry in full.
- `?include=relations.authors`: one relationship's entries in full.

What's included has links of its own, but no body.
