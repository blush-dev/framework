# Archives and feeds

A collection lists its entries on its listing page, and can have date
archives and feeds. Any page can list entries too. This page covers how
those lists are made and ordered.

## What a collection gets

A collection's `index.md` (such as `_post/index.md`) is its listing
page, at its prefix (`/blog`). Its type's options add more:

- **`dateArchives`** (collections only): `year`, `month`, `day`, `hour`,
  `minute`, or `second`, such as `/blog/2026` and `/blog/2026/09` for
  `month`.
- **`feed`**: RSS, Atom, and JSON feeds (`/blog/feed`, `/blog/feed/atom`,
  `/blog/feed/json`): `true`, or a `TypeFeed` with `categories` (the
  [type of terms](relationships.md) used for each item's categories) and
  `listing`.

See [Building a blog](building-a-blog.md) for both together, and
[All type options](content-types.md#all-type-options) for the rest. Terms
and credited people get pages and archives of their own (see
[Relationship archives](relationship-archives.md) and
[Credits and profiles](credits-and-profiles.md)).

## A type's listing page

A type's `listing` option sets how its listing page lists entries:

```php
new Listing(orderBy: 'date', order: Order::Desc, perPage: 20)
```

| Option    | Default         | What it does                                                           |
|-----------|-----------------|------------------------------------------------------------------------|
| `type`    | The type itself | Which type to list                                                     |
| `orderBy` | Its type's order | `date`, `updated`, `title`, `author`, `position` (a tree's, or a collection's that [nests or is ordered by position](relationships.md#nesting-and-order), then title), or any field. A collection lists by its `order`: newest first by date, or by `position`, then title |
| `order`   | Its type's order | `asc` or `desc` (`Order::Asc` or `Order::Desc` in PHP)                 |
| `perPage` | `10`            | How many per page; `0` (`Listing::ALL`) for all of them                |
| `query`   |                 | Any other option from the table [below](#listing-entries-on-any-page), such as `{terms: {tag: [php]}}` |

In JSON, it's the same keys: `"listing": { "orderBy": "date", "order": "desc" }`.

Entries are never sorted by file name, so the order holds when file
names change or content moves to a database. Text sorts without regard
to case; entries without the value come last, whichever way; and ties
go by when each entry was made (its id), earliest first. 1.x's
`orderby: filename` is read as `date`, and `content:lint` warns
where a page still says it.

## Listing entries on any page

To list entries on any page, use `collection` in its front matter:

```yaml
---
title: "Latest recipes"
collection:
  type: recipe
  number: 5
  orderby: date
  order: desc
---
```

| Option                    | What it does                                                                  |
|---------------------------|-------------------------------------------------------------------------------|
| `type`                    | Which type(s) to list                                                         |
| `number`                  | How many per page (default 10; `0` or less for all)                           |
| `offset`                  | Skip this many                                                                |
| `orderby`                 | `date` (default), `updated`, `title`, `author`, `position`, or any field |
| `order`                   | `desc` with no `orderby`, else `asc` (default), or `desc`                     |
| `terms`                   | Only entries in these terms, such as `{tag: [php]}`                           |
| `author`                  | Only entries crediting these profiles, through any credit relation            |
| `names` / `names_exclude` | Only, or never, these slugs                                                   |
| `meta_key` / `meta_value` | Only entries whose field has this value                                       |
| `year` … `second`         | Only entries published in this period: `year`, then `month`, down to `second`, each with the ones before it |

To find entries in PHP, see [Finding entries](../features/finding-entries.md).
