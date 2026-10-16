# Media

**Content → Media** shows the files in `user/media`, the site's
[media library](../concepts/media/README.md), and each file's details.

## The library

Files are listed newest
[upload](../concepts/media/README.md) first, each
by its title (or its file name, until it has one), laid out like the
entries lists: **All** and **Mine** tabs with their counts (**Mine** is
what you uploaded), then a search by name or details and a filter for
images, video, audio, documents, or other files. A sound or video
shows its [artwork](../concepts/media/README.md) as its thumbnail, marked with its
kind. In the library, an image's sizes aren't items of their own: its
screen lists them.

**Upload** opens the same picker the editor uses, with only its
Upload panel (the library is the screen behind it; see
[Inserting media](editor/inserting-media.md#the-upload-tab)); **Open** goes to the
file you uploaded. You can also put files in
`user/media` yourself.

## A file's screen

Choose a file for its screen, in two columns. On the left: a preview (an image or video; a
sound's artwork, title, artist, and album with a player; or a PDF's
page with its page count); a sound's or video's **Artwork** (see
[Artwork](../concepts/media/README.md)); **Usage**, its address
and what an entry writes to show it, each with a Copy button, then the
entries that use it (the first five of a long list, with **Show All**),
and for an image, the sounds and videos that show it as their artwork;
**Storage**, where it lives, its type, size, length or pages, and who
uploaded it; and an image's **Other Sizes**. A media file's screen shows
its id under **File**.

On the right: its **Details** (the
fields files of its kind have: **Title**, **Alt text** for an image,
**Caption**, **Credit**, and **Description**, and any your site adds;
see [Details about a file](../concepts/media/README.md)), the only
part that saves, and its **Metadata**, what the file says about itself,
with a warning if it carries its location. A value the file holds that
fits a detail is offered under that field, with **Use It**, and **Fill
from the File** fills every empty one (see
[What a file says about itself](../concepts/media/README.md)).

The heading is the file's title, else its file name, and follows the
Title field as you type. An image without alt text says so under the
field.

## Alt text and captions

Alt text describes the file for anyone
who can't see it, and the caption goes under it; both are filled in when
it's inserted as an image. After that, the entry's copy is its own: the
page shows what the entry wrote, and an image with empty brackets,
`![](/media/rule.png)`, has empty alt text (`alt=""`), which marks it
decorative. Changing an image's alt text or caption in the editor never
changes the library's, and changing the library's never changes an
entry.

## Who can change details

Details can be changed by whoever uploaded the file (`media.edit`), or by anyone
with `media.edit.others`, which files with no uploader recorded need;
otherwise they're shown read-only. Changes wait in the **save bar** at
the bottom, which counts them: **Save Changes** saves them, and
**Revert** puts them back.

## Where details are kept

Details are kept apart from the file, in `user/data/media/`, which mirrors
the media paths: `user/media/2026/09/lake.jpg` has
`user/data/media/2026/09/lake.jpg.json`:

```json
{
    "alt": "A lake at dawn, with mist on the water.",
    "caption": "The lake at dawn"
}
```

You can write these by hand too. Any JSON
file in `user/data` may start with a `"$schema"` key for your editor;
it isn't read as data, and the admin keeps it when it saves. Saving
from the admin edits the file that's there, changing
only the fields you changed and keeping the rest (keys that aren't
fields are listed, as they are), and
removes a file left with nothing in it. If you rename or
delete a media file by hand, move or delete its metadata file too.

## Deleting a file

The entries a file's screen lists use it by any of its addresses, in
their text or front matter. **Delete** (your own files with
`media.delete`, anyone's with `media.delete.others`) asks first, saying
how many entries use it, which will be left pointing at an address that
no longer resolves until they're changed, and which files it's the
artwork of, which lose it; it removes the file,
its details, and the copy `media:publish --copy` made.
