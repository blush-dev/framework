# Trash

Each list has a **Trash** tab (if your account can delete entries) with
the entries moved there, most recent first. The **All** tab never
includes them.

## Restoring and deleting

Each one's **⋯** button has:

- **Restore as a Draft** makes it a draft again, even if it was
  published before; publish it again from the editor when you're
  ready.
- **Preview** (or clicking its title) shows what's in it: the body, as
  the editor shows it but read-only, and its front matter. From there
  you can restore it or delete it permanently. A trashed entry isn't on
  your site, so it can't be viewed there, previewed, or edited until
  it's restored.
- **Delete Permanently** removes its file for good. When other entries
  link to it (as a term, a credit, or any other
  [relation](../concepts/relationships.md)), it lists them
  and offers to remove it from those you can edit, checked at first, so
  nothing names it after; uncheck it to leave their values as they are.

**Empty Trash** deletes everything in that tab permanently. Authors and
contributors see and handle their own trashed entries; editors see
everyone's.

## Entries that link to it

Entries linking to an entry keep their links while it's a draft or in
the trash; the site just stops showing it there, and publishing or
restoring it brings them back. So switching a published entry to draft,
or moving one to the trash, says first how many live entries link to it,
and lists them (up to 8, with what each links through). Only live
entries count: a draft that links loses nothing. When none link, it just
happens, with its toast and Undo. A profile says it's credited, and how
many entries credit no one else, so they'll show no byline.

## Undo

Moving one entry to the trash, from its list, the editor, or a profile,
shows a toast with **Undo**, which puts it back as it was, published or
a draft, and opens it again if you were editing it. Undo needs
permission to publish for an entry that was published, and it's refused
if someone changed the entry in the trash meanwhile; restore it from
the Trash tab instead.

Moving several at once has no Undo. Moving several to the trash or to draft says first which of them live
entries link to, and how many each, most linked first, naming the ones
nothing live links to. Past 8, **Show Only the Linked** cancels and
filters the list to the entries live entries link to.

## The file

Moving an entry to the trash doesn't move its file. The file stays
where it is, with `status: trash` and the time in `trashed` in its front
matter (see [Front matter](../concepts/front-matter.md)), so it keeps its
[id](../concepts/ids-and-links.md) and its address. Nothing new can take that address
while it's there: restore it, or delete it permanently, first. You can
also restore one by hand by changing its `status`.
