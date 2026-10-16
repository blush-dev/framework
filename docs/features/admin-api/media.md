# Media

Listing, uploading, changing, and deleting the files in the media library. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET media` | The media files an entry can use (see below) |
| `GET media/{path}` | One file in the library, by its path under `user/media` or its id (as media fields name it), with its details (see below) |
| `POST media` | Upload a file to the library (see below) |
| `PATCH media/{path}` | Change a library file's details (see below) |
| `DELETE media/{path}` | Delete a library file, with its details (see below) |

`GET media` lists the library (`user/media`), newest upload first, a page at a
time, for accounts that can edit content. Narrow it with `search` (text
the path must contain), `kind` (`image`, `video`, `audio`, `document`,
`file` for anything else, or `any`),
`page`, and `per` (48 by default, at most 100). The answer has `total`,
`page`, `pages`, `per`, and `files`; each file has its `reference`
(what to write in content: the library's URL path), `name`,
`folder`, `url`, `mime`, `kind`, `size`, `width` and `height` (images),
`modified`, `uploaded` (when it was added to the library, ISO 8601, or `null`), and the library's `alt` and `caption` for it (`""` for
none). Only the file types your site allows are listed. When
the account may upload some kind (`media.{kind}.upload`), `upload` has the largest file
it may upload (`limit`, in bytes, or `null` for none: the upload rules'
largest, within PHP's) and the `extensions` that may be uploaded;
otherwise it's `null`.

`POST media` uploads one file, sent as the multipart field `file`, and
needs the capability to upload its kind (`media.{kind}.upload`, a 403
otherwise). Its uploader is recorded in its details (`owner`). It goes in the folder the upload rules give its
kind (`user/media/{year}/{month}/` by default; see
[`MediaConfig`'s `uploads`](../../reference/configuration/README.md)) with a
name safe for a URL (dots before its extension become hyphens, so
`shell.php.jpg` is saved as `shell-php.jpg`), and `-2`, `-3`, and so on
when the name is taken. Its extension must be one the library lists, and
its contents must be of a type your site allows
([`MediaConfig`](../../reference/configuration/README.md)). SVG, HTML, XML, JavaScript, PHP, and
Office files with macros are never taken, whatever your site allows. The
answer is a 201 with the file, as `GET media` describes one; a file too
large (for PHP or its kind) is a 413, one of the wrong type or a kind
turned off a 422, and any upload while uploads are off a 403.

`GET media` lists from the media index (see
[The media index](../../concepts/media/README.md)): `search` matches a file's
path or details, `missing=alt` narrows it to images without alt
text, and `mine=1` to the account's own uploads. It's for accounts that
can edit entries, or have a media capability.

Every file the media API answers has its `title`, `alt`, and
`caption` (`''` for none), its uploader's account id (`owner`, `''` for
none), an audio file's or video's `duration` in
seconds (`null` when unknown), and its `artworkUrl`, for a thumbnail
(its artwork image's URL, else where the cover art it carries is
served, else `null`). `GET media/{path}` adds what the file says about itself, `embedded`:
its `values` (read from its EXIF, IPTC, and XMP: `title`,
`description`, `creator`, `copyright`, `credit`, `keywords`, `created`,
`camera`, `lens`, `focalLength`, `aperture`, `exposure`, `iso`,
`orientation`, `software`, and for audio and video `album`, `track`,
`genre`, `duration` (seconds), `artwork`, `width`, and `height`,
whichever it has) and `location`, whether
it carries one (never where). It also adds the file's details: the `fields` its kind has
(described as a content type's are), their `values`, keys its metadata
file keeps that aren't fields (`extra`), `violations` for what
doesn't fit, its `uploader` (`{"username", "name"}`, or `null`), what
the account `may` do to it (`{"edit", "delete", "addArtwork"}`), and the entries
that use it (`usedIn`: `{"path", "title", "type"}`, `path` being the
document's path). A sound or video has its `artwork` (`{"id",
"image"}`, the image being `{"path", "reference", "url", "name",
"title"}`, or `null` when the id names no image in the library; `null`
for none), and an image the files that show it (`artworkFor`:
`{"path", "name", "title", "kind"}`). `PATCH media/{path}` changes them: `{"set": {field:
value}, "remove": [field]}`, for a file in the library, needs
`media.edit` (and `media.edit.others` for a file that isn't the
account's, or has no uploader), and answers with the file. An empty value removes a
field, each value is checked by its field (a 422 naming the `field`
when it doesn't fit), and `alt` and `caption` may still be sent on
their own.

`PUT media-artwork/{path}` sets a sound's or video's artwork: `{"image":
"{id}"}` names a library image, and `{"from": "file"}` adds the cover
art it carries to the library first (or finds the image with the same
bytes), which also needs uploading images (`may.addArtwork`).
`DELETE media-artwork/{path}` takes it off, leaving the image. Both
need what changing the file's details does, and answer with the file;
`GET media-artwork/{path}` answers with the cover art the file carries.

`DELETE media/{path}` deletes the file, its details, and the copy
`media:publish --copy` made, and answers `{"deleted"}` with its path.
It needs `media.delete`, and `media.delete.others` for a file that
isn't the account's or has no uploader. Deleting an image takes it off
the files that show it as their artwork. It doesn't check where the file
is used; `GET media/{path}`'s `usedIn` is for asking first.
