# Content and media checks

Site Health's content and media checks each have a screen, where their
problems are [fixed](fixing-problems.md).

## Content Files

**Content Files** lists the problems in content files, as
`bin/blush content:lint` finds them (see
[Checking content](../../concepts/checking-content.md)): front matter that can't be read,
values that don't fit their fields, missing required fields, dates that
aren't real, two files claiming the same entry, parents and
translations that can't be used, and links that can't be followed.
Its notices are keys nothing reads and 1.x field names.

## Entry IDs

**Entry IDs** lists the files missing an [id](../../concepts/ids-and-links.md), or with
one that isn't valid; **Add an ID** gives one a new id. For an id two
or more files share (usually a copied file), choose the file that keeps
it, and **Give the Others New IDs**. A file without an id isn't an
entry, so lists leave it out until it has one.

## Terms and Profiles

**Terms and Profiles** lists each term and profile your entries name
that has no file, which the site leaves out (entries' terms and bylines
too, so an author isn't credited until their profile has a file), with
how many entries name it. **Create** writes its file, published and titled as the entries
name it, for the types you may create and publish.

## Parent Pages

**Parent Pages** lists each folder of pages with no page of its own,
which leaves the pages in it at the top of their tree, with how many
pages it holds. **Create** writes its page as a draft, titled by the
folder, for the types you may create.

## Links Between Entries

**Links Between Entries** lists the files whose links aren't filed with
their ids. **File Links** files them, so they follow what they link to
through a rename or a move.

## Taxonomies

**Taxonomies** lists the types in `user/data/types` still written as
taxonomies, which Blush no longer has. **Migrate** rewrites each as a
collection and its classify relation in `user/data/relations`, as
`bin/blush content:taxonomies --write` does (see
[Relationships](../../concepts/relationships.md)). It
migrates them all at once, and needs Site Health and `site.settings`.

## Type Folders

**Type Folders** lists the types in `user/data/types` that still name
their own folder, with where their entries move. Every type is kept in
`_` and its name, so their entries aren't found until they move.
**Move Types** moves them, as `bin/blush content:type-folders --write`
does (see [Content types](../../concepts/content-types.md)):
each type's files move, and its file is written without the folder,
with the prefix its addresses came from. It moves them all at once,
and needs Site Health and `site.settings`.

## Collection Folders

**Collection Folders** lists the collection entries not in the folders
their [collection](../../concepts/file-names-and-urls.md) keeps them in:
entries kept as folders, files in other folders, and files in a
pattern's folder for another date. It shows where each moves to; no
address changes.

## File Names

**File Names** lists, for each type with a file name
[pattern](../../concepts/file-names-and-urls.md) of its own (not the
default), the entries whose files aren't named by it, such as posts
written before you changed it, and the name each gets. No address
changes. Entries kept as folders keep their names.

## Media Details

**Media Details** lists the problems in media details in
`user/data/media/`: one that can't be read, a value that doesn't fit
its field, or details left for a file that's gone.

## Media IDs

**Media IDs** works
as entry ids do, for [media files](../../concepts/media/README.md), by
their paths in the media folder, changing only files whose details you
may edit. A media file's screen shows its id under **File**.

## Image Sizes

**Image Sizes** lists the images whose details don't list their
[sizes](../../concepts/media/README.md) as they are; **Record Sizes**
lists them. In the Media library, an image's sizes aren't items of
their own: its screen lists them under **Sizes**.
