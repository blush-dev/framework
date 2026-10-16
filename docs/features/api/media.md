# Media in the API

`/api/v1/media` lists your media library, and `/api/v1/media/{id}`
answers one file. Every file in your media library with an id is
public, as its file is:

```json
{
	"id": "0198…",
	"kind": "image",
	"mime": "image/jpeg",
	"url": "https://example.com/media/2026/10/lake.jpg",
	"size": 482113,
	"width": 2400,
	"height": 1600,
	"duration": null,
	"title": "Lake",
	"alt": "A lake at dawn",
	"caption": "",
	"description": "",
	"uploaded": "2026-10-01T14:00:00Z",
	"sizes": [ { "url": "https://example.com/media/2026/10/lake-600x400.jpg", "width": 600, "height": 400 } ],
	"artwork": null,
	"fields": {}
}
```

Find files with `kind` (`image`, `video`, `audio`, `document`, or
`file`) and `search`. They're newest first.

To upload files and change their details, see
[Changing content](changing-content.md).
