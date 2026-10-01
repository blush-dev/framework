# Media

Images, audio, and video go in one of two places.

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
to an entry, isn't media: Blush doesn't serve it, list it in the
library, or export it.

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

To add your own, for every file or for one kind (`image`, `video`,
`audio`, or `file` for anything else), list them in
`user/data/media-fields.yml` (or `.yaml` or `.json`), defined the way a
[content type's fields](content-types.md) are:

```yaml
all:
  - name: license
    type: enum
    options: [cc-by, all-rights-reserved]
image:
  - name: photographer
```

or in [`config/media.php`](configuration.md#media), which wins over the
data file:

```php
use Blush\Content\Schema\Fields\TextField;
use Blush\Media\MediaConfig;
use Blush\Media\MediaFieldSet;
use Blush\Media\MediaKind;

return new MediaConfig(
	fields: [
		new MediaFieldSet([new TextField('photographer')], MediaKind::Image)
	]
);
```

A field with a built-in's name replaces it, such as `credit` with
`required: true`. The admin's form for a file follows its fields.

A metadata file can point your editor at the built-in fields'
schema: `# yaml-language-server: $schema=../../../vendor/blush-dev/framework/resources/schemas/media.schema.json`
(with as many `../` as it's deep).

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
read. The library shows how long a sound or video lasts.

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
  empty until it's fixed.
- **Warnings:** details for a file that's gone, such as after renaming
  or deleting the file by hand (move the details file with it, or
  delete it), or for a file of a type the site doesn't allow; and a
  file hidden by another in a different format (`sunset.jpg.json`
  is read, so `sunset.jpg.yml` beside it isn't).
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

With `--copy`, run it again whenever you add media. A [static export](going-live.md#static-export)
includes everything either way.
