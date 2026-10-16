# Lists in the API

Every list the [API](README.md) answers, of entries, media, types, or
anything else, has the same shape:

```json
{
	"items": [ … ],
	"total": 214,
	"page": 2,
	"pages": 11,
	"per_page": 20,
	"next": "https://example.com/api/v1/entries?page=3",
	"prev": "https://example.com/api/v1/entries"
}
```

`page` and `per_page` (20 by default, 100 at most) choose the page.
`next` and `prev` are full addresses, or `null` at either end.
