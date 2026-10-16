# Media

Images, audio, video, and documents live in your site's media folder,
`user/media/`, and each file can carry details of its own.

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

## More about media

| Page | What it covers |
|---|---|
| [Details about a file](details.md) | Titles, captions, credits, alt text, and fields from plugins |
| [What a file says about itself](embedded-metadata.md) | Details read from images, sound, video, and PDFs |
| [Artwork](artwork.md) | Images shown with a song, an episode, or a video |
| [Ids and image sizes](ids-and-image-sizes.md) | Every file's id, and an image's resized copies |
| [Upload dates](upload-dates.md) | How the library orders files |
| [The media index](media-index.md) | What the admin's library lists and searches |
| [Checking the details](checking-media.md) | What `content:lint` checks in your media's details |
| [Allowed file types](allowed-file-types.md) | Which files are served and uploaded |
| [Faster media on a live site](faster-media.md) | Letting your web server serve media directly |
