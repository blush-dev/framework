# Relationship archives

A relation's `inverse` says where the entries linking to one are listed:
on the linked entry's own page (a term's page, a profile's page), in
archives under the linking type's address, or both.

## Term pages

A type of terms with URLs gets a page for each term, listing the
entries filed under it, at `/{prefix}/{slug}` (nested for a
[nesting collection](relationships.md#nesting-and-order)). Term pages are paged
(`/topics/painting/page/2`), and have feeds (`/topics/painting/feed`)
when the type of terms has `feed`. The type's own listing page (such as
`/topics`) lists its terms, as its `listing` says.

A term's page lists only the entries filed under that term, not those
under its child terms. The relation's `inverse` sets the term side:

| Option            | Default                 | What it does                                                                    |
|-------------------|-------------------------|---------------------------------------------------------------------------------|
| `page`            | `true` for `classify` and `credit` | Whether each target's own page lists what links to it                |
| `archive`         | The name for `credit`, else `false` | A word for archives under each linking type (`/movies/directors/penny`), or `false` |
| `types`           | The relation's `from`   | The types a term's page lists                                                   |
| `listing`         |                         | How a term's page lists entries, with a type's [`listing`](archives-and-feeds.md#a-types-listing-page) keys, such as `order` and `perPage` |
| `label`           |                         | What the term side is called                                                    |
| `max`             |                         | How many entries may be filed under one term; the admin won't publish one more  |

For example, twenty entries to a term's page:

```json
{
	"kind": "classify",
	"from": ["post"],
	"to": ["category"],
	"inverse": {"listing": {"perPage": 20}}
}
```

`"page": false`, or `inverse: false`, gives the terms no pages of
their own.

## Archives for references

A [reference](relationships.md#linking-entries-to-other-entries) relation's
`inverse` lists the entries linking to one, paged with a feed. Its two
sides can be on at once, and a reference has neither by default, so a
template lists them with `$entry->referencedBy()`:

- `"page": true` lists them on the linked entry's own page, as a term's
  page lists what's filed under it. With `actors` linking movies to
  people, `/people/tom` lists Tom's movies.
- `"archive"`, a word such as `"directors"`, gives archives under the
  linking type's address, as [credits](credits-and-profiles.md#people-archives) have:
  `/movies/directors/penny` lists Penny's movies, and `/movies/directors`
  lists everyone a movie names. `_directors.md` in the movie type's
  folder introduces the list, and `_directors/penny.md` Penny's archive
  in place of her own page's text.

```json
{
	"kind": "reference",
	"from": ["movie"],
	"to": ["person"],
	"inverse": {"archive": "directors"}
}
```

Every relation with an archive word takes the same two `_` pages, and
adds route keys of its own (see
[Custom URLs](file-names-and-urls.md#custom-urls)).
