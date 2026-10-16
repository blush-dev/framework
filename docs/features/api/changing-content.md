# Changing content through the API

A request with a [token](tokens.md) whose account may make the change
can create and change entries and media, by the same rules as the
admin: what the account's role allows (narrowed to what the token
allows), required fields, relationship limits, and raw HTML. Each
change is listed in the activity log on **Tools → Activity**, as the
API's, under the token's name, and can be undone there (see
[Activity and Undo](../mcp/activity-and-undo.md)).

Send changes as JSON, with `Content-Type: application/json`. Values are
written the way reads send them: media and a page's `parent` by id,
`fields` only those the type declares, and `relations` by name, as the
targets' ids (or slugs). `null` removes a value.

| Request | What it does |
|---|---|
| `POST /api/v1/entries` | Makes a draft: `type` and `title`, and any of `subtitle`, `slug`, `date`, `expires`, `image`, `parent`, `language`, `fields`, `relations`, and `body` (Markdown). Answers `201` with a `Location` |
| `PATCH /api/v1/entries/{id}` | Changes only what it's sent: any of the above but `type` and `language`, and `status` |
| `DELETE /api/v1/entries/{id}` | Moves it to the trash |
| `POST /api/v1/media` | Uploads a file, as a form does (`multipart/form-data`, the file as `file`), with `alt`, `caption`, and `title` beside it |
| `PATCH /api/v1/media/{id}` | Changes a file's `alt`, `caption`, and `title` |

```sh
curl -X POST https://example.com/api/v1/entries \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"type": "post", "title": "Hello", "body": "First words.", "relations": {"category": ["art"]}}'
```

Each answers what it wrote, as reading it would (`?body=` chooses
`html`, `markdown`, or `none`), with its new `revision`.

- **Revisions:** an entry read with a token that may edit it has a
  `revision`. Send it back in `If-Match` with every `PATCH` and
  `DELETE`. If someone changed the entry since, the change is refused
  (`412`), never saved over: read it again, and make the change on
  what's there now.
- **Publishing:** new entries start at their workflow's first status,
  a draft unless the type says otherwise. `PATCH` with `status`
  publishes (`published`), unpublishes (`draft`), schedules
  (`scheduled`, with a future `date`), or moves it to another
  status of its type's workflow, by the transition to it, when the
  account may make that transition (see
  [Workflows](../workflows.md)). Going live needs publishing, which a
  token made to write drafts never can.
- **Moving:** a new `slug`, or a page's new `parent` (`null` for the
  top), moves an entry; a published one's old address redirects to the
  new one unless you send `redirect: false`.
- **The trash:** `DELETE` moves an entry to the trash, and a `PATCH`
  of `status` alone (one of its workflow's, such as `draft` or
  `published`) brings it back. Nothing is
  ever deleted for good, and media is never deleted.
- **Uploads** follow your Media settings: the kinds and sizes they
  allow, and never SVG.
- **Limits:** a token may make 600 changes an hour (`maxWrites`). Past
  that, a `429` with `Retry-After`.

Types, settings, menus, accounts, and roles are changed only in the
admin.
