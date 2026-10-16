# The API

Blush can serve an API of your site as JSON, for a front end built
with another tool, an app, another site, or an AI tool. It answers with
what your site shows anyone: published pages and posts, your content
types and relationships, your media, your menus, and your site's
details. Drafts, scheduled entries, and hidden pages are left out,
unless a request with an [API token](tokens.md) asks for them. With a
token, a script or an app can also [change content](changing-content.md):
write drafts, publish, trash, and upload media.

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

## Pages

| Page | What it covers |
|---|---|
| [Turning it on](turning-it-on.md) | Turning the API on, where it's served, and keeping it private |
| [Tokens](tokens.md) | Using the API as an account, and managing an account's tokens |
| [Lists](lists.md) | The shape every list has, and paging |
| [Entries](entries.md) | An entry's fields, its body, and including what it links to |
| [Finding entries](finding-entries.md) | Narrowing and sorting lists, and finding the entry at an address |
| [Media](media.md) | Files in your media library |
| [Menus](menus.md) | Your theme's menu locations and their menus |
| [Changing content](changing-content.md) | Creating, changing, publishing, and trashing entries, and uploading media |
| [Errors](errors.md) | Every error code |
| [Caching and other sites](caching.md) | `ETag`s, cache times, and calling the API from a browser |

The [MCP server](../mcp/README.md) offers the same site to AI apps, and
the [admin's API](../admin-api/README.md) is a separate, private API for
the admin itself.
