# Pinned entries

Some entries aren't ordinary entries of their type: they introduce an
archive, sit at the top of the site, or stand in for an error. Their
lists pin them at the top, apart from the totals.

## Index pages

A collection's **index page** (the `index.md` in its
folder, which introduces its archive) is pinned at the top of its list
with a pin and an **Index** tag, on the list's first page. It isn't
counted in the list's totals, and it can't be moved to the trash (see
[Editing an index page](editor/index-pages.md)). It still follows the
tabs and search: it shows only when it matches them.

## The root page and the homepage

Pages pin their **root page** (`user/content/index.md`) the same way.
When it's the homepage, it has a house in place of the pin and a
**Homepage** tag. It isn't counted in the totals, isn't offered as a
parent, and can't be duplicated or moved, and the editor leaves out
its slug and position, since it's the top of the tree. It can be moved
to the trash; the site then shows a welcome page at `/`.

When the homepage is set to show a collection (**Settings › Reading**),
that collection's index page gets the house and the **Homepage** tag,
and the root page is tagged **Not shown**, since visitors never see it.
If you can change the site's settings, **Make homepage** beside the tag
(also in its row menu, and in the editor) switches the homepage back to
it.

## List pages

A type with a [relation archive](../concepts/credits-and-profiles.md),
such as its authors', may have a **list page** for it (`_authors.md` or
`_cooks.md` in its folder, which introduces the list). It's pinned under
the index page with the relation's label as its tag (**Authors**), set
apart from the totals the same way, and opens in the editor as **Edit
Authors Page**, without the type's fields or a date, and with its slug
fixed. Unlike the index page, it can be moved to the trash. The pages
written for one target's archive (`_cooks/jane.md`) aren't listed at
all; a profile's are reached from [the profile's screen](users/profiles.md).

## Error pages

Pages' **error pages** (`_errors/404.md`, and 1.x's `_error/` folder,
whose title and text the site shows for that error; see
[Error pages](../concepts/error-pages.md)) are pinned at the
top of Pages with a pin and their status as a tag (**Error 404**), by
status, followed by anything else kept in those folders. The folders'
own pages (`_errors/index.md`) aren't listed. They aren't
counted in the totals, aren't offered as a parent, can't be duplicated,
and keep their slug, since it's the status. Moving one to the trash is
allowed: the theme's own message is shown in its place.
