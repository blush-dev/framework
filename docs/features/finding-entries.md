# Finding entries

`Blush\Content\Entries` finds entries. Ask for it in a constructor (a
command's, a block's, a component's), and build a query from it:

```php
$recent = $this->content->query()
	->type('post')                // a type's name, or the type itself
	->whereTerm('category', 'news')
	->limit(5)
	->get();                      // or ->first(), ->count(), ->paginate(10, $page)
```

A query finds published, listed entries in the site's language, newest
first, unless it says otherwise (`orderBy()`, `status()`, `language()`).
A few things to know:

- **`updatedTimes()`** gives when each entry the query finds last
  changed, in its order, as Unix timestamps, without loading the
  entries: for a list of thousands, such as a sitemap's dates, where
  loading every entry would be slow.
- **`search('grid')`** matches entries whose title or slug contains
  the text, in any case. It doesn't search what an entry says.
- **`termCounts('category')`** gives how many entries name each term, by
  slug, in slug order. A parent term counts only the entries that name
  it, not those filed under its children.
- **`neighbors($entry)`** gives the entries just `before` and `after`
  one in its type's listing, in that listing's order, so for a
  collection (newest first) `before` is newer and `after` older. Either
  is `null` at an end. Give a query to walk another listing:
  `neighbors($entry, $this->content->query()->type('post')->orderBy('title'))`.
- **`whereParent($entry)`** finds the entries under a page or a term,
  by the entry or its key (`whereParent('about')`), as a query you can
  sort, page, and count; **`whereParent(null)`** finds the top level: a
  tree's top pages, or a nesting collection's top entries (such as top
  categories).
- **`parent($entry)`** and **`children($entry)`** give a page's or a
  nesting collection entry's parent and children, whatever their status,
  so filter with `isLinkable()` where you link to them.

## What an entry is

An entry can tell you what it is:

| Check | True when | For example |
|---|---|---|
| `isLive()` | It's on the site: its status is a live one, its date has come, and it hasn't expired | Not a draft, a post scheduled for tomorrow, an expired one, or one in the trash |
| `isLinkable()` | You can link to it: it's live, it isn't hidden, and its type is on the site | A live unlisted page is linkable; a draft or a hidden page isn't |
| `isListed()` | It's in collections, feeds, and sitemaps: live, public, and not a landing page | An unlisted page is linkable but not listed |
| `isRoutable()` | It has an address: it isn't hidden. Status doesn't count | A draft is routable; its page is served once it's live |
| `hasStatus('in_review')` | Its status is that one, as it is now | A published post dated tomorrow has `scheduled`, not `published` |
| `isPublished()` | Its status is `published` and its date has come | A post with a plugin's live status is live, but not published |
| `isDraft()`, `isScheduled()`, `isExpired()`, `isTrashed()` | Its status is that one | |

`$entry->link()` prints a link only when the entry is linkable, and its
title alone otherwise, so a template can print it without checking.
`$entry->url()` is its address whatever its status, so check
`isLinkable()` before you link to it yourself.

To create and change entries, see [Changing content](changing-content.md).
