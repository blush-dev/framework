# The API

Blush can serve an API of your site as JSON, for a front end built
with another tool, an app, another site, or an AI tool. It answers with
what your site shows anyone: published pages and posts, your content
types and relationships, your media, your menus, and your site's
details. Drafts, scheduled entries, and hidden pages are left out,
unless a request with an [API token](#tokens) asks for them. With a
token, a script or an app can also [change content](#changing-content):
write drafts, publish, trash, and upload media.

## Turning it on

The API is off until you turn it on, in the admin on **Settings →
API**, or in `config/api.php`:

```php
<?php

declare(strict_types=1);

use Blush\Api\ApiConfig;

return new ApiConfig(enabled: true);
```

It's served at `/api/v1`. To serve it somewhere else, set `path`
(`path: '/data'` serves `/data/v1`). While it's off, every address
under it is your site's ordinary "not found" page. See
[Configuration](configuration.md#the-api) for every option.

## What it serves

| Address | What it answers |
|---|---|
| `/api/v1` | Where each resource is |
| `/api/v1/site` | Your site's name, description, address, language, time zone, and homepage |
| `/api/v1/types` | Your public content types; `/api/v1/types/{name}` for one |
| `/api/v1/entries` | Published entries of every public type; `/api/v1/entries/{id}` for one |
| `/api/v1/relations` | The relationships between types, such as a post's tags or authors |
| `/api/v1/media` | Every file in your media library; `/api/v1/media/{id}` for one |
| `/api/v1/menus` | Your theme's menu locations; `/api/v1/menus/{location}` for one's menu |

Entries and media are found by their id, the `id` in each file's front
matter or metadata, so an address stays the same when a file is renamed
or moved.

## Tokens

An API token lets an app, a script, or an AI agent use the API as one
of your site's accounts. It's sent in a header on each request:

```
Authorization: Bearer blush_0199a1b4-…_9f3c…
```

- **Making one:** on **Your Account** in the admin, under API Tokens,
  with **New Token**, or with [`token:add`](cli.md#accounts). The token
  is shown once; copy it then. Only the owner and administrators can
  make tokens until a role is given **Make API tokens for their own
  account**.
- **What it can do:** what its account may, checked on every request,
  so a role change or a suspension counts at once. A token can be
  narrowed: **Read** (what anyone can read), **Write drafts**, **Same
  as the account**, or capabilities you choose.
- **For an agent:** make it an account of its own, a
  [token-only account](accounts.md#token-only-accounts), with a role
  that fits. One that can edit but not publish only ever writes drafts.
- **How long it lasts:** 30 days, 90 days (the default), a year, or
  never. The dashboard says when one of yours expires within a week.
  Expired tokens stay listed for 30 days, so you can see what stopped.
- **Revoking:** **Revoke** stops a token at once, and **Revoke All**
  stops every one of an account's. Changing a password doesn't revoke
  tokens.

A token also works on the [MCP server](mcp.md), for AI apps. A token
an app gets by connecting through MCP works only there, never on the
API. A token is only accepted in the `Authorization` header, never in the
address, and only over HTTPS (plain HTTP works on `localhost`). After
many requests with a token that isn't one, the address sending them is
refused for a while.

### What a token sees

Without asking, a token gets exactly what anyone gets. With `status`,
it gets entries of those statuses that its account may edit, whatever
their visibility, hidden pages included:

```
/api/v1/entries?type=post&status=draft
/api/v1/entries/0199…?status=draft,scheduled
```

What it may edit follows its roles, as in the admin: an editor sees
everyone's drafts, an author their own. Answers to a request with a
token are never cached or shared (`Cache-Control: private, no-store`).

### A private API

To keep the API to your own front end, apps, and agents, set **Who can
read it** to **Only with a Token** on **Settings → API**, or
`access` in `config/api.php`:

```php
return new ApiConfig(enabled: true, access: ApiAccess::Token);
```

Then a request without a token gets a `401`.

## Lists

Every list has the same shape:

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

## Entries

```json
{
	"id": "0199a1b4-…",
	"type": "post",
	"title": "Hello",
	"subtitle": "",
	"slug": "hello",
	"status": "published",
	"published": "2026-10-01T14:00:00Z",
	"updated": "2026-10-02T09:12:00Z",
	"url": "https://example.com/archives/hello",
	"excerpt": "The first words of the post…",
	"image": { "id": "0198…", "url": "https://example.com/media/2026/10/hello.jpg", "alt": "A lake", "width": 1200, "height": 800 },
	"fields": { "mood": "happy" },
	"relations": {
		"tags": [ { "id": "0199…", "type": "tag", "title": "Cooking", "url": "https://example.com/tags/cooking" } ]
	},
	"body": "<p>…</p>",
	"assets": { "styles": [], "scripts": [] }
}
```

- **Dates** are in UTC. Your site's time zone is in `/api/v1/site`.
- **`url`** is there for types whose entries have pages. **`parent`**
  is there for pages and other types that nest: the page above it, or
  `null`.
- **`excerpt`** is plain text: the summary, or the start of the body.
- **`fields`** holds the fields the entry's [type declares](content-types.md),
  each one, `null` when it's empty. Front matter the type doesn't
  declare isn't sent. A media field is the file, as `image` is.
- **`relations`** lists what the entry links to, by the relationship's
  name, in order. A link to something the site doesn't show (a draft
  tag) is left out.
- **`body`** is the entry rendered as on your site. `assets` lists the
  scripts and stylesheets it needs, such as the audio player's, so a
  front end can load them.

### The body

A single entry has its body as HTML. A list leaves bodies out, unless
you ask with `body`:

- `?body=html`: rendered as on your site, with full addresses.
- `?body=markdown`: as you wrote it, with full addresses for links and
  media (as the [Markdown copies](configuration.md#markdown-pages-and-llmstxt)
  are). Directives stay as written.
- `?body=none`: no body.

### Including what an entry links to

Links to other entries are small: their id, type, title, and address.
Media is its id, address, alt text, and size. Ask for the whole thing
with `include`, and it takes the link's place:

- `?include=media`: every media file in full (caption, sizes, and the
  rest).
- `?include=relations`: every linked entry in full.
- `?include=relations.authors`: one relationship's entries in full.

What's included has links of its own, but no body.

### Finding entries

| Parameter | Example | Finds |
|---|---|---|
| `type` | `?type=post,note` | Entries of those types |
| `rel:{relationship}` | `?rel:tags=cooking,baking` | Entries linked to any of those, by slug or id |
| `parent` | `?parent=0199…` | The pages under a page |
| `published_after`, `published_before` | `?published_after=2026-01-01` | Entries published from, or before, a date |
| `updated_after` | `?updated_after=2026-10-01T00:00:00Z` | Entries changed since then |
| `search` | `?search=sourdough` | Entries with those words |
| `ids` | `?ids=0199…,0198…` | Those entries |
| `sort` | `?sort=-published` | Sorted by `published`, `updated`, `title`, or `position`; `-` for newest or last first |

A list of one type follows that type's own order; a list of several is
newest first. A list leaves out unlisted entries and the pages
introducing a listing, as your site's listings do.

### Finding the entry at an address

A front end that owns its own pages can ask what's at an address of
your site:

```
/api/v1/entries?url=/about/biography
```

The answer is a list of that entry, or an empty one. The address can be
a path or a full address on your site. When the address
[redirects](content.md#redirects), the empty list says where:

```json
{ "items": [], …, "redirect": { "to": "https://example.com/about", "status": 301 } }
```

`url` takes only `body` and `include` beside it. An archive (a tag's
page, a year) isn't an entry, so it answers an empty list.

## Media

Every file in your media library with an id is public, as its file is:

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

## Menus

`/api/v1/menus` lists your theme's menu locations and the menu each one
shows. `/api/v1/menus/primary` is that location's menu as your site
shows it, in your site's language, with each item's `label`, `url`,
`description`, `icon`, `image`, `badge`, `class`, `rel`, the theme's own
`fields`, and its `children`.

## Changing content

A request with a token whose account may make the change can create
and change entries and media, by the same rules as the admin: what the
account's role allows (narrowed to what the token allows), required
fields, relationship limits, and raw HTML. Each change is listed in the
activity log on **Tools → Activity**, as the API's, under the token's
name, and can be undone there.

Send changes as JSON, with `Content-Type: application/json`. Values are
written the way reads send them: media and a page's `parent` by id,
`fields` only those the type declares, and `relations` by name, as the
targets' ids (or slugs). `null` removes a value.

| Request | What it does |
|---|---|
| `POST /api/v1/entries` | Makes a draft: `type` and `title`, and any of `subtitle`, `slug`, `published`, `image`, `parent`, `language`, `fields`, `relations`, and `body` (Markdown). Answers `201` with a `Location` |
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
- **Publishing:** new entries are always drafts. `PATCH` with `status`
  publishes (`published`), unpublishes (`draft`), or schedules
  (`scheduled`, with a future `published` date), when the account may
  publish. A token made to write drafts never can.
- **Moving:** a new `slug`, or a page's new `parent` (`null` for the
  top), moves an entry; a published one's old address redirects to the
  new one unless you send `redirect: false`.
- **The trash:** `DELETE` moves an entry to the trash, and a `PATCH`
  of `status` alone (`draft` or `published`) brings it back. Nothing is
  ever deleted for good, and media is never deleted.
- **Uploads** follow your Media settings: the kinds and sizes they
  allow, and never SVG.
- **Limits:** a token may make 600 changes an hour (`maxWrites`). Past
  that, a `429` with `Retry-After`.

Types, settings, menus, accounts, and roles are changed only in the
admin.

## Errors

A request the API can't answer gets an error with a status, and, for a
change, the `field` it's about, when there is one:

```json
{ "error": { "code": "not_found", "message": "No entry has the id \"…\"." } }
```

| Code | Status | When |
|---|---|---|
| `invalid_query` | 400 | A parameter isn't one the address takes, or has a value it can't, or a token is in the address |
| `invalid_body` | 400 | A change isn't a JSON object, or has a key or value it can't take |
| `token_required` | 401 | The API, `status`, or a change needs a token, and there's none |
| `invalid_token` | 401 | The token is wrong, revoked, or expired, or its account is suspended |
| `https_required` | 403 | A token was sent over plain HTTP |
| `forbidden` | 403 | The token or its account may not make the change |
| `not_found` | 404 | Nothing has that id, or nothing is at that address (or, for a change, nothing the token may change) |
| `revision_mismatch` | 412 | The entry changed since that revision |
| `too_large` | 413 | An upload is larger than the site allows |
| `unprocessable` | 422 | The change can't be made as asked: a slug that's taken, a relationship out of its limits, an entry in the trash |
| `revision_required` | 428 | A change to an entry came without its revision in `If-Match` |
| `too_many_attempts` | 429 | Too many tokens that weren't one came from your address; try later |
| `too_many_writes` | 429 | The token made as many changes this hour as the site allows |

The `code` stays the same; the message may change.

## Caching and other sites

Answers are kept until your content changes, like your pages, and
browsers and caches may keep them for a minute (`maxAge`). Each has an
`ETag`, so a client that asks again with `If-None-Match` gets a quick
`304` when nothing changed. Any site may call the API from a browser
(`Access-Control-Allow-Origin: *`): an answer without a token is
public, and a browser never sends a token on its own, only a script
you give one to. Keep a token out of pages anyone can read; a front
end should use it on its server.
