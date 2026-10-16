# What a file says about itself

Photos, songs, and videos often carry details of their own, written by
the camera, the recorder, or the software that made them. Blush reads
them and offers them to you, without ever changing the file.

## What's read

- **Images** (EXIF, IPTC, and XMP): the title, description, creator,
  copyright, credit, keywords, when it was taken, the camera, lens, and
  exposure, and the software.
- **MP3, MP4, Ogg, WAV, and WebM files:** the title, artist, album,
  track, genre, date, how long it lasts, a video's size and frame rate,
  the format, the sound's bit rate, sample rate, and channels, and its
  cover art.
- **PDFs:** the title, author, subject, keywords, what made it, the PDF
  version, how many pages it has, and their size. An encrypted PDF
  gives only its version, pages, and page size.

Blush keeps them in the [media index](media-index.md), and never
changes the file or writes them to `user/data`.

## Using them

On a file's screen in the admin, a value that fits one of your
[details](details.md) is offered under its field ("The file says …"),
with **Use It** to copy it in (a title into the title, a creator into
the credit), or **Fill from the File** to copy every one whose field is
empty; the rest are listed under **Metadata**. A caption only ever
comes from your details, never from the file.

## Locations in photos

Some photos also carry where they were taken (GPS). Blush reads it but
never shows it, not even in the admin; the file's **Metadata** only warns
that it's there, since anyone who downloads the file can read it. To remove
it, strip the location with your photo software before you upload.

## Requirements

EXIF needs PHP's `exif` extension; without it, IPTC and XMP are still
read. The library shows how long an audio file or video lasts.

Plugins can read more from files: see
[Reading more from media files](../../features/media-readers.md).
