# Media

Where media is served from, which types it may be, and the rules for
uploads.

`config/media.php` · `Blush\Media\MediaConfig`

| Option | Default | What it does |
|---|---|---|
| `url` | `'/media'` | The URL `user/media` is served from |
| `types` | Images, audio, video, WebVTT captions, and PDFs | The MIME types that may be served and uploaded |
| `autoIndex` | `true` | Whether development requests pick up media changes in the library (elsewhere, `media:index` or publishing does) |
| `uploads` | Every kind, any size PHP takes, in `{year}/{month}` | What the admin may upload, how large, and where it goes (a `MediaUploads`; below) |

Documents besides PDFs (`text/plain`, `text/csv`, `text/markdown`,
`application/epub+zip`, `application/rtf`, and Word, Excel,
PowerPoint, and OpenDocument files) can be served and uploaded once
you add their types to `types`.

## Uploads

`uploads` is what the Media settings screen edits, and what it saves
in `user/data/settings/media.json` wins over this file:

```php
use Blush\Media\MediaConfig;
use Blush\Media\MediaUploadRule;
use Blush\Media\MediaUploads;

return new MediaConfig(
	url: '/media',
	uploads: new MediaUploads(maxSize: 24, kinds: [
		'audio' => new MediaUploadRule(maxSize: 40, path: 'audio'),
		'file'  => new MediaUploadRule(enabled: false)
	])
);
```

| Option | Default | What it does |
|---|---|---|
| `enabled` | `true` | Whether anything may be uploaded. Files already in `user/media` are served either way |
| `maxSize` | `null` | The largest file, in megabytes; `null` for whatever PHP takes (`upload_max_filesize`, `post_max_size`), which is always the most |
| `path` | `'{year}/{month}'` | The folder under `user/media` a file goes in: a plain folder (`uploads`), or a pattern with `{year}`, `{month}`, `{day}`, and `{kind}` (`images`, `videos`, `audio`, `documents`, or `files`). Empty puts files straight in `user/media`. Not `{ext}`: an image's renditions in other formats go beside it |
| `kinds` | `[]` | Rules for one kind (`image`, `video`, `audio`, `document`, `file`), each a `MediaUploadRule` with `enabled`, and its own `maxSize` and `path` (`null` takes the ones above) |

Changing a path doesn't move anything: a file keeps the address it was
uploaded at.

See [Media](../../concepts/media/README.md).
