# Finding entries in the API

`/api/v1/entries` takes parameters that narrow and sort the list, and
can answer what's at an address of your site.

## Narrowing and sorting

| Parameter | Example | Finds |
|---|---|---|
| `type` | `?type=post,note` | Entries of those types |
| `rel:{relationship}` | `?rel:tags=cooking,baking` | Entries linked to any of those, by slug or id |
| `parent` | `?parent=0199…` | The pages under a page |
| `date_after`, `date_before` | `?date_after=2026-01-01` | Entries dated from, or before, a date |
| `updated_after` | `?updated_after=2026-10-01T00:00:00Z` | Entries changed since then |
| `search` | `?search=sourdough` | Entries with those words |
| `ids` | `?ids=0199…,0198…` | Those entries |
| `sort` | `?sort=-date` | Sorted by `date`, `updated`, `title`, or `position`; `-` for newest or last first |

A list of one type follows that type's own order; a list of several is
newest first. A list leaves out unlisted entries and the pages
introducing a listing, as your site's listings do. Every list is paged
as [Lists](lists.md) describes, and a [token](tokens.md) can add
`status` to find drafts and other statuses.

## Finding the entry at an address

A front end that owns its own pages can ask what's at an address of
your site:

```
/api/v1/entries?url=/about/biography
```

The answer is a list of that entry, or an empty one. The address can be
a path or a full address on your site. When the address
[redirects](../../concepts/redirects.md), the empty list says where:

```json
{ "items": [], …, "redirect": { "to": "https://example.com/about", "status": 301 } }
```

`url` takes only `body` and `include` beside it (see
[Entries](entries.md)). An archive (a tag's page, a year) isn't an
entry, so it answers an empty list.
