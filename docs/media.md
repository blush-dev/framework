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
| `content` | A longer description, for the library (Markdown); the admin calls it **Description** |

Images also have `alt`, which says what the image shows for anyone who
can't see it. Where an image is used, what the entry writes wins; the
library's alt text and caption are filled in when you insert it, and a
page's image without alt text uses the library's.

To add your own, use a [field set](content-types.md#field-sets) aimed
at the kinds of file it's for: `media:image`, `media:video`,
`media:audio`, `media:document` (PDFs, office files, plain text), or
`media:file` (anything else):

`user/data/fields/photo-rights.json`:

```json
{
    "label": "Photo Rights",
    "targets": ["media:image"],
    "fields": {
        "photographer": {},
        "license": {
            "type": "enum",
            "options": ["cc-by", "all-rights-reserved"]
        }
    }
}
```

List all four kinds for fields every file should have. A set's fields
come after the built-in ones, under the set's label in the admin, and
can't reuse a built-in field's name. **Structure → Fields** in the admin
creates and edits sets ([Fields](admin.md#fields)).

A metadata file can point your editor at the built-in fields'
schema, with a `"$schema"` key:
`"$schema": "../../../vendor/blush-dev/framework/resources/schemas/media.schema.json"`
(with as many `../` as it's deep). The admin keeps the key when it saves the file.

## What a file says about itself

Photos, songs, and videos often carry details of their own, written by
the camera, the recorder, or the software that made them. Blush reads
them: from images (EXIF, IPTC, and XMP: the title, description,
creator, copyright, credit, keywords, when it was taken, the camera,
lens, and exposure, and the software); from MP3, MP4, Ogg, WAV, and
WebM files (the title, artist, album, track, genre, date, how long it
lasts, a video's size and frame rate, the format, the sound's bit rate,
sample rate, and channels, and its cover art); and from PDFs (the
title, author, subject, keywords, what made it, the PDF version, how
many pages it has, and their size). An encrypted PDF gives only its
version, pages, and page size. It keeps them in
the media index, and never changes the file or writes them to
`user/data`. On a file's screen in the admin, a value that fits one of
your details is offered under its field ("The file says …"), with **Use
It** to copy it in (a title into the title, a creator into the credit),
or **Fill from the File** to copy every one whose field is empty; the
rest are listed under **Metadata**. A caption only ever comes from your
details, never from the file.

Some photos also carry where they were taken (GPS). Blush reads it but
never shows it, not even in the admin; the file's **Metadata** only warns
that it's there, since anyone who downloads the file can read it. To remove
it, strip the location with your photo software before you upload.

EXIF needs PHP's `exif` extension; without it, IPTC and XMP are still
read. The library shows how long an audio file or video lasts.

## Artwork

A song, an episode, or a video can have artwork: an image shown with
it, on an audio card (see [Media](directives.md#media)) and
as a video's poster where a page doesn't name one. It's an image in
your media library, named by its id in the file's details:

```json
{
    "title": "Morning Song",
    "artwork": "0199c4a2-5b7e-7d31-9f0a-3c2e8d41b7a6"
}
```

Being a library image, the artwork has its own title, alt text, and
sizes, and can be used anywhere else. Without one, the cover art saved
in the file is shown, read from the file each time. Blush never writes
artwork into the file itself.

On a sound's or video's screen in the admin, **Artwork** shows which it
has:

- **Add to Library** saves the cover art in the file as an image beside
  it (`song.mp3` gets `song-artwork.jpg`), titled "Artwork for" the
  file's title, and makes it the artwork. When an image with exactly the
  same bytes is already in the library, that image is used instead, so
  every track of an album shares one. It needs permission to upload
  images.
- **Choose Image** picks an image from the library, or uploads one.
- **Remove** takes the artwork off. The image stays in the library.

Like the file's details, a change to its artwork waits in the save bar
until you choose **Save Changes**, and **Revert** puts it back; nothing
is added to the library until then. The library shows each sound's and
video's artwork as its thumbnail, marked with its kind.

An image's screen lists the files that show it, under **Usage**.
Deleting the image takes it off those files, which then show the cover
art they carry, if any.

To add the cover art to the library as each sound or video is uploaded,
turn on **Artwork from uploads** in the [Media settings](admin.md#settings),
or set `addArtwork` in `config/media.php`:

```php
return new MediaConfig(addArtwork: true);
```

It's off by default. Either way, the files stay as they are; `content:lint`
warns about artwork that names an image that's no longer in the library.

## Ids and image sizes

Every media file has an id, a UUID kept last in its details file, as
entries have (see [Ids](content.md#ids)):

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
listed, never by its name. Until an image's sizes are listed, Blush finds them by their names, and treats a
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

When the whole library has to be read again (the first time, or after
changing the media URL or the file types the library takes), the admin
reads a few hundred files at once and the rest as a
[background job](going-live.md#background-jobs-and-cron), so a large
library never makes a page time out. Meanwhile the Media screen says
it's catching up and shows how far along it is: files not read yet show
their details from before, or aren't listed yet. Publishing from the
admin or the webhook works the same way. `publish` and `media:index` on
the command line always read everything in one go.

## Checking the details

`bin/blush content:lint` (and **Site Health** in the admin) checks
every file in `user/data/media/` along with your content:

- **Errors:** a file that can't be read, such as JSON with a typo, or a
  value that doesn't fit its field. The library treats such a file as
  empty until it's fixed. A media file with no id, or one that isn't a
  UUID, and an id two files share (see [Ids and image
  sizes](#ids-and-image-sizes)).
- **Warnings:** details for a file that's gone, such as after renaming
  or deleting the file by hand (move the details file with it, or
  delete it), or for a file of a type the site doesn't allow; details for an
  image size, which the original's details stand in for; and
  `renditions` listing a file that's gone. (`renditions` that isn't a
  list of files with their width and height is an error.)
- **With `--strict`:** keys that aren't one of the file's fields.

## Allowed file types

Only common image, audio, and video types are served: AVIF, GIF, JPEG, PNG,
SVG, WebP, APNG, MP3, WAV, Ogg, MP4, and WebM, plus WebVTT caption files
(`.vtt`) for videos. You can change the list, or
the `/media` URL, in [`config/media.php`](configuration.md#media).

SVG files are served, but can't be uploaded in the admin, whatever the
list says: an SVG can carry script. For icons, add an
[icon pack](extending.md#icon-packs); for other SVGs, put the file in `user/media` yourself.
For the same reason, the admin never takes HTML, XML, JavaScript, or PHP
files, or Office files with macros (`.docm`, `.xlsm`, `.pptm`), and an
uploaded file's name keeps only its last extension (`shell.php.jpg` is
saved as `shell-php.jpg`).

## Faster media on a live site

By default, Blush's PHP code streams each media file. On a live site, let
your web server hand them out directly instead:

```sh
bin/blush media:publish          # link user/media into public/
bin/blush media:publish --copy   # copy the files, for hosts without symlinks
```

With `--copy`, run it again whenever you add media.

Either way, `media:publish` writes an `.htaccess` in the folder the web
server serves (`user/media/.htaccess` when linked, `public/media/.htaccess`
when copied), so Apache serves media as Blush does: no script ever runs
there, even one named like `shell.php.jpg`; files are sent with
`X-Content-Type-Options: nosniff`; and SVGs are sandboxed. It keeps the
file current each time it runs. If the folder already has an `.htaccess`
of your own, it's left alone and you're warned; to take over the one
`media:publish` wrote, remove its first line.

On nginx, or another server that doesn't read `.htaccess`, add the same
rules to its config. For nginx, with media at `/media`:

```nginx
location ^~ /media/ {
	add_header X-Content-Type-Options nosniff always;

	location ~* \.(php\d*|pht|phtml|phar|phps|cgi|pl|py|sh|shtml|asp|aspx|jsp)(\.|$) {
		deny all;
	}

	location ~* \.svg$ {
		add_header X-Content-Type-Options nosniff always;
		add_header Content-Security-Policy sandbox always;
	}
}
```
