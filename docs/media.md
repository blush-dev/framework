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

## Next to the entry (bundles)

To keep a page's images with the page, turn the page into a folder with an
`index.md`, and link the files by name:

```
user/content/blog/trip-to-rome/
  index.md
  colosseum.jpg
```

```markdown
![The Colosseum at dusk.](colosseum.jpg)
```

The entry is still `trip-to-rome`, and Blush serves the image for you.

## Allowed file types

Only common image, audio, and video types are served: AVIF, GIF, JPEG, PNG,
SVG, WebP, APNG, MP3, WAV, Ogg, MP4, and WebM. You can change the list, or
the `/media` URL, in [`config/media.php`](configuration.md#media).

## Faster media on a live site

By default, Blush's PHP code streams each media file. On a live site, let
your web server hand them out directly instead:

```sh
bin/blush media:publish          # link user/media into public/
bin/blush media:publish --copy   # copy the files, for hosts without symlinks
```

With `--copy`, run it again whenever you add media. Files in bundles are
always served by Blush, and a [static export](going-live.md#static-export)
includes everything either way.
