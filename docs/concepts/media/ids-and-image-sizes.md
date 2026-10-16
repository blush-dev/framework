# Ids and image sizes

Every media file has an id, and an image's resized copies are kept with
the original rather than as files of their own.

## Ids

Every media file has an id, a UUID kept last in its details file, as
entries have (see [Ids](../ids-and-links.md)):

`user/data/media/2026/10/sunset.jpg.json`:

```json
{
    "alt": "The sun going down over the lake",
    "id": "0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74"
}
```

Uploading a file gives it one. Blush writes the id, so leave it alone,
and don't copy it to another file. Details without an id of their own,
or sharing one with another file, are skipped until they have one: the
library and the admin don't read them. For files you added by hand, or
before ids, add them in one go:

```sh
bin/blush media:ids            # say how many files are missing an id, and which ids are shared
bin/blush media:ids -v         # and list each file
bin/blush media:ids --write    # give each file missing one a new id
```

This writes a details file for each file that doesn't have one. When
two files share an id (a details file copied by hand), say which keeps
it, and the others get new ones:

```sh
bin/blush media:ids --keep=2026/10/sunset.jpg
```

**Site Health** in the admin does the same, under Media Files' **Media IDs**.

## Renditions and image sizes

**Renditions** are an image's other files: resized copies, and copies
in other formats. **Image sizes** aren't media of their own. Media brought from another
system often has resized copies of each image (`photo-300x200.jpg`,
`photo-1024x683.jpg` beside `photo.jpg`). The library shows one item for
the original, says how many sizes it has, and lists them on its screen.
A size has no id and no details of its own: it goes by its original's
(its screen shows them, read-only). It's still served, so old links to
it keep working. Deleting an image deletes its sizes too.

An image's details list its renditions, its sizes among them, each
file with its width and height:

`user/data/media/2019/photo.jpg.json`:

```json
{
    "alt": "The lake at dawn",
    "renditions": {
        "2019/photo-150x100.jpg": { "width": 150, "height": 100 },
        "2019/photo-300x200.jpg": { "width": 300, "height": 200 }
    },
    "id": "0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74"
}
```

A file listed there is a rendition, whatever it's named; a copy in
another format (`photo.webp` beside `photo.jpg`) is one only when it's
listed, never by its name.

## How sizes are found

Until an image's sizes are listed, Blush finds them by their names, and treats a
file as a size only when all of these are true:

- its name ends in `-{width}x{height}`;
- the original (the same name without that ending) is beside it;
- it really is that many pixels, and no larger than the original;
- it has no id of its own.

So a name like `daisy-3x4.jpg` that's really a crop, not that many
pixels, stays an image of its own; to keep a real size apart from its
original, give it an id in its own details file. To list the sizes
Blush found in each image's details:

```sh
bin/blush media:sizes           # say how many sizes aren't listed yet
bin/blush media:sizes --write   # list them
```

This also takes out listed files that are gone. **Site Health** in
the admin does the same, under **Image Sizes**.
