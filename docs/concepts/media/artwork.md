# Artwork

A song, an episode, or a video can have artwork: an image shown with
it, on an audio card (see [Media blocks](../blocks/media.md)) and
as a video's poster where a page doesn't name one.

## Naming the artwork

Artwork is an image in your media library, named by its id in the
file's [details](details.md):

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

## In the admin

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

## Artwork from uploads

To add the cover art to the library as each sound or video is uploaded,
turn on **Artwork from uploads** in the [Media settings](../../admin/settings/README.md),
or set `addArtwork` in `config/media.php`:

```php
return new MediaConfig(addArtwork: true);
```

It's off by default. Either way, the files stay as they are; `content:lint`
warns about artwork that names an image that's no longer in the library.
