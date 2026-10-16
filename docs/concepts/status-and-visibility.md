# Status and visibility

An entry's status says whether and when it's on your site, and its
visibility says where it appears. This page covers drafts, scheduling,
expiry, unlisted and hidden entries, and the trash.

## Drafts, scheduling, and hiding

| You want to... | Do this |
|---|---|
| Keep an entry unpublished | Add `status: draft` |
| Publish it later | Set `date` to a future date. It goes live by itself at that time. |
| Take it down later | Set `expires`, in a type with [expiry](#expiry). It comes off the site by itself at that time, and comes back if you move or remove the date. |
| Reach it only by its link | Add `visibility: unlisted`. It has a URL, but no list shows it. |
| Hide it completely | Add `visibility: hidden`. It has no URL, but your theme can still show it. |
| Throw it away, but keep it for now | Add `status: trash` (the admin's **Move to trash** does this). It's off your site, and the admin lists it under **Trash** until you restore it or delete it. |

A file or folder name never hides an entry or makes it a draft: its
front matter does. (In 1.x, a `_` at the start of a name did;
`content:lint` warns of a name that still starts with `_`, since its
entry has no address. Rename it, and add `visibility: hidden` or
`status: draft`.) The only `_` names Blush keeps are its own: each
type's folder (`_post`), a relation archive's pages (`_post/_authors.md`),
and the error pages (`_errors/404.md`).

Plugins can add statuses of their own, and a type's `workflow` says
which statuses its entries move through (see
[Statuses](../features/statuses.md) and
[Workflows](../features/workflows.md)).

## Who sees an entry

Two things decide whether an entry is on your site: its **status**
says whether and when, and its **visibility** says where.

An entry is **live** when its status is a live one (`published`, or
one a plugin adds), its `date` has come, and its `expires` date (if it
has one) hasn't passed. Drafts, scheduled and expired entries, and the
trash are never shown.

A live entry's visibility then says where it appears:

| Visibility | Its own address | In lists | |
|---|---|---|---|
| `public` (the default) | Yes | Yes | |
| `unlisted` | Yes | No | Its page, its Markdown copy (see [Configuration](../reference/configuration/README.md)), and a read of it by the [API](../features/api/README.md) or an AI app all work. It's left out of listings, feeds, the sitemap, `llms.txt`, related entries, and the API's lists. Its page and its Markdown copy ask search engines not to index them. Anyone with the link can read it: unlisted isn't private. |
| `hidden` | No | No | Your theme can still show it, by asking for it by name. |

Visibility is a [type option](#visibility), on by
default. A type with it off doesn't offer it in the admin, and every
one of its entries is public. A type that isn't on the site (`public:
false`) has no addresses at all, so its entries have no visibility.

## Expiry

A type with `expires: true` (in [Supports](content-types.md#supports),
**Expiration**) gives each entry an `expires` date. Once it passes, the
entry is expired: off the site, as a draft is, so its address is a 404
and no list, feed, sitemap, or API read shows it. Nothing in its file
changes; it keeps its status, and moving the date later, or removing it,
brings it back. The admin lists expired entries under **Expired**, and
plugins hear of them with `EntriesExpired` (see
[Events](../features/events.md)).

`expires` has to come after the entry's `date`. Index, archive, and
error pages, and a folder's landing page, never expire. A type without
expiry ignores the date, and `content:lint` says so.

## Visibility

A type that's on the site (`public`, the default) and has
`visibility: true` (in [Supports](content-types.md#supports),
**Visibility**) lets each entry choose its visibility: `public`,
`unlisted` (an address, but in no list), or `hidden` (no address). See
[Who sees an entry](#who-sees-an-entry). Without it, every entry is
public: the admin doesn't offer it, and a `visibility` in an entry's
front matter is ignored. A type with `public: false` has no addresses,
so its entries have no visibility either way.
