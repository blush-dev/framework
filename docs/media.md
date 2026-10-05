# Media

Images, audio, video, and documents go in one of two places.

## The media folder

Put files in `user/media/`. They're served at `/media/`:

| File | URL |
|---|---|
| `user/media/sunflower.jpg` | `/media/sunflower.jpg` |
| `user/media/2026/talk.mp4` | `/media/2026/talk.mp4` |

Use those URLs in your writing:

```markdown
![A yellow sunflower.](/media/sunflower.jpg)
```

Paths written the 1.x way, `/user/media/sunflower.jpg`, work too.

Media paths in Markdown work with or without the first `/`: a path is
looked for from the site's root, so `user/media/photo.jpg` and
`/user/media/photo.jpg` are the same file.

Media always lives in `user/media`. A file kept in `user/content`, next
to an entry, isn't media: Blush doesn't serve it or list it in the
library.

## Details about a file

Each file can carry details, kept apart from it in `user/data/media/`
(see [Media in the admin](admin.md#media)). Every file has these:

| Field | What it's for |
|---|---|
| `title` | What the library calls it, in place of its file name |
| `caption` | Shown with the file where it's used, such as under an image |
| `credit` | Who made it, or where it's from |
| `description` | A longer description, for the library (Markdown) |

Images also have `alt`, which says what the image shows for anyone who
can't see it. Where an image is used, what the entry writes wins; the
library's alt text and caption are filled in when you insert it, and a
page's image without alt text uses the library's.

To add your own, use a [field set](content-types.md#field-sets) aimed
at the kinds of file it's for: `media:image`, `media:video`,
`media:audio`, `media:document` (PDFs, office files, plain text), or
`media:file` (anything else):

```yaml
# user/data/fields/photo-rights.yaml
label: Photo Rights
targets: [media:image]
fields:
  photographer: {}
  license:
    type: enum
    options: [cc-by, all-rights-reserved]
```

List all four kinds for fields every file should have. A set's fields
come after the built-in ones, under the set's label in the admin, and
can't reuse a built-in field's name. **Config → Fields** in the admin
creates and edits sets ([Fields](admin.md#fields)).

A metadata file can point your editor at the built-in fields'
schema, with a `"$schema"` key in JSON:
`"$schema": "../../../vendor/blush-dev/framework/resources/schemas/media.schema.json"`
(with as many `../` as it's deep), or in YAML, a first-line comment:
`# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/media.schema.json`.
The admin keeps the key when it saves the file.

## What a file says about itself

Photos, songs, and videos often carry details of their own, written by
the camera, the recorder, or the software that made them. Blush reads
them: from images (EXIF, IPTC, and XMP: the title, description,
creator, copyright, credit, keywords, when it was taken, the camera,
lens, and exposure, and the software), and from MP3, MP4, Ogg, WAV, and
WebM files (the title, artist, album, track, genre, date, how long it
lasts, a video's size, and whether it has cover art). It keeps them in
the media index, and never changes the file or writes them to
`user/data`. The admin shows them on a file's screen, under **From the
File**, with **Use** to copy one into your own details (a title into the
title, a creator into the credit). A caption only ever comes from your
details, never from the file.

Some photos also carry where they were taken (GPS). Blush reads it but
never shows it, not even in the admin; the file's screen only warns that
it's there, since anyone who downloads the file can read it. To remove
it, strip the location with your photo software before you upload.

EXIF needs PHP's `exif` extension; without it, IPTC and XMP are still
read. The library shows how long an audio file or video lasts.

## Ids and image sizes

Every media file has an id, a UUID kept last in its details file, as
entries have (see [Ids](content.md#ids)):

```yaml
# user/data/media/2026/10/sunset.jpg.yml
alt: The sun going down over the lake
id: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
```

Uploading a file gives it one. Blush writes the id, so leave it alone,
and don't copy it to another file. For files you added by hand, or
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

**Content health** in the admin does the same, under **Media IDs**.

**Image sizes** aren't media of their own. Media brought from another
system often has resized copies of each image (`photo-300x200.jpg`,
`photo-1024x683.jpg` beside `photo.jpg`). The library shows one item for
the original, says how many sizes it has, and lists them on its screen.
A size has no id and no details of its own: it goes by its original's
(its screen shows them, read-only). It's still served, so old links to
it keep working. Deleting an image deletes its sizes too.

An image's details list its sizes, each file with its width and
height:

```yaml
# user/data/media/2019/photo.jpg.yml
alt: The lake at dawn
sizes:
  2019/photo-150x100.jpg: { width: 150, height: 100 }
  2019/photo-300x200.jpg: { width: 300, height: 200 }
id: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74
```

A file listed there is a size, whatever it's named. Until an image's
sizes are listed, Blush finds them by their names, and treats a
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

This also takes out listed files that are gone. **Content health** in
the admin does the same, under **Image Sizes**.

## The media index

The admin's library lists and searches a media index
(`storage/index/media.php`) of every file in `user/media`: its type, size, dimensions, and details. It only reads files
that changed, so it stays quick with thousands of files. In development
it updates as you work; on a live site, `publish` (and the admin's
**Reindex content** action) updates it, as does uploading a file or
saving its details in the admin. To update it yourself:

```sh
bin/blush media:index          # only what changed
bin/blush media:index --full   # read every file again
```

It also warns of details left in `user/data/media/` for a file that's
gone: move them with the file, or delete them.

## Checking the details

`bin/blush content:lint` (and **Content health** in the admin) checks
every file in `user/data/media/` along with your content:

- **Errors:** a file that can't be read, such as YAML with a typo, or a
  value that doesn't fit its field. The library treats such a file as
  empty until it's fixed. A media file with no id, or one that isn't a
  UUID, and an id two files share (see [Ids and image
  sizes](#ids-and-image-sizes)).
- **Warnings:** details for a file that's gone, such as after renaming
  or deleting the file by hand (move the details file with it, or
  delete it), or for a file of a type the site doesn't allow; and a
  file hidden by another in a different format (`sunset.jpg.json`
  is read, so `sunset.jpg.yml` beside it isn't); details for an
  image size, which the original's details stand in for; and `sizes`
  listing a file that's gone. (`sizes` that isn't a list of files with
  their width and height is an error.)
- **With `--strict`:** keys that aren't one of the file's fields.

## Allowed file types

Only common image, audio, and video types are served: AVIF, GIF, JPEG, PNG,
SVG, WebP, APNG, MP3, WAV, Ogg, MP4, and WebM, plus WebVTT caption files
(`.vtt`) for videos. You can change the list, or
the `/media` URL, in [`config/media.php`](configuration.md#media).

## Faster media on a live site

By default, Blush's PHP code streams each media file. On a live site, let
your web server hand them out directly instead:

```sh
bin/blush media:publish          # link user/media into public/
bin/blush media:publish --copy   # copy the files, for hosts without symlinks
```

With `--copy`, run it again whenever you add media.
