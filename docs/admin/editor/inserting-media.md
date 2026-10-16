# Inserting media and icons

The editor's picture button puts media from the library into an entry,
or uploads new files; the shapes button puts in an icon.

## The media picker

The picture button has two ways in: **Media Library** and **Upload a
File**. Both open the same picker, on its **Library** or **Upload** tab.
**Image** in the blocks panel opens it too, showing only images.

### The Library tab

The Library tab has the library in `user/media`, newest first. Search by file name, or show only images,
video, audio, or other files. Choose one or more files, then **Insert**
(or double-click a file). Each file you choose is numbered, in the order
it goes in; choosing it again takes it out, and **Clear** starts over.
Several files go in one after another, each on lines of its own with a
blank line between; inside a gallery, its images go in one to a line.

An image goes in as plain Markdown on a
line of its own, `![](/media/photo.jpg)`, with the cursor where its
description goes (selected text becomes the description), and its
settings open on the element tab (see
[Images](element-settings.md#images)). When the library has alt text and a
caption for the file (see [Media](../media.md)), they're filled in:
`![A lake at dawn](/media/lake.jpg "The lake at dawn")`, with selected
text still winning for the description. On the site it's a figure; a quoted title
after the address, `![A lake](/media/lake.jpg "The lake at dawn")`, is
its caption. A video goes in as a video, an audio file as audio, and anything
else as a download, and their options open in the settings.

### The Upload tab

The Upload tab takes files from your computer: drag them anywhere onto
the picker, or use **Choose Files**. It says how large a file may be and
which types the library takes. Each one goes into `user/media` under the
year and month (`user/media/2026/09/`), with its name made safe for an
address (`My Photo.JPG` becomes `My-Photo.jpg`), or the folder the
[**Media** settings](../settings/media.md) give its kind; a name that's taken gets
`-2`, `-3`, and so on, so nothing is replaced. An upload lands at the top
of the library, chosen (along with any you'd chosen already), so
**Insert** finishes the job; **Show in
library** switches tabs to see it there. Uploading needs the kind's
capability (`media.image.upload`, or `media.*.upload` for every kind),
and the Upload tab takes only the kinds you may upload.
How large a file may be is up to the **Media** settings, within what
PHP takes (`upload_max_filesize` and `post_max_size`).

### Choosing one file

The same picker is **Choose** beside every media field and block
option, such as a video's **Poster image**, where it takes one file. A field or option that takes
one kind of file (a video's file, its poster, the featured image, an
image's **Replace**, or a field with a `kind`) shows only that kind,
without the kind buttons, and its upload refuses a file of another
kind, saying why.

## Icons

The shapes button opens your theme's [icons](../../concepts/blocks/README.md),
grouped: the built-in icons by category (Status, Interface, Arrows, and
so on), then your theme's, your icon packs', and your plugins'. Pick a
group on the left, or search by name or what it shows (`home` finds the
house), then choose one and **Insert** (or double-click it; after a
search, Enter inserts the first match). It goes in at the cursor as
`:core/icon[]{name=house}`; select some text first to give it a label
for screen readers.
