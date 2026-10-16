# Allowed file types

Only common image, audio, and video types are served, and some files are
never taken by the admin's uploader.

## What's served

AVIF, GIF, JPEG, PNG, SVG, WebP, APNG, MP3, WAV, Ogg, MP4, and WebM,
plus WebVTT caption files (`.vtt`) for videos. You can change the list,
or the `/media` URL, in
[`config/media.php`](../../reference/configuration/media.md).

## What's never uploaded

SVG files are served, but can't be uploaded in the admin, whatever the
list says: an SVG can carry script. For icons, add an
[icon pack](../../icon-packs/README.md); for other SVGs, put the file in `user/media` yourself.
For the same reason, the admin never takes HTML, XML, JavaScript, or PHP
files, or Office files with macros (`.docm`, `.xlsm`, `.pptm`), and an
uploaded file's name keeps only its last extension (`shell.php.jpg` is
saved as `shell-php.jpg`).
