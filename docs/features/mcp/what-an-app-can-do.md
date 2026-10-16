# What an AI app can do

An app connected to the [MCP server](README.md) sees only the tools its
access allows:

| Tool | What it does |
|---|---|
| `list_types` | Lists your content types |
| `get_type` | One content type in full: its fields, relationships, statuses, and workflow |
| `search_entries` | Finds entries by words, type, status, relationship, parent, and dates |
| `get_entry` | One entry in full, by id or address, with its Markdown body and what links to it |
| `search_media` | Finds media by kind, words, and missing alt text |
| `get_media` | One file in full, with the entries that use it |
| `create_entry` | Creates an entry at its workflow's first status, a draft unless its type says otherwise |
| `update_entry` | Changes an entry's fields, body, slug, or parent |
| `edit_body` | Replaces one passage of an entry's body |
| `set_status` | Changes an entry's status: publishes, unpublishes, schedules, or makes another transition of its type's workflow (Submit for Review, say). Offered when the account may publish, or has a transition of its own |
| `trash_entry` | Moves an entry to the trash, after naming what links to it |
| `restore_entry` | Brings an entry back from the trash, at its workflow's first status |
| `update_media` | Changes a file's alt text, caption, or title |
| `upload_media` | Adds a file to the media library |

Plugins can add their own (see [Tools](../tools.md)); **Settings → AI**
lists every tool on your site, with what it needs.

- **What it reads:** what its account may see. Unlike the
  [API](../api/README.md), that includes drafts and scheduled entries
  the account may edit, but not the trash unless it asks.
- **What it writes:** everything goes through the same checks as the
  admin: what the account may do, required fields to publish,
  relationships' limits, raw HTML, and which slugs are free. A change
  names the version of the entry it was made to, so it never overwrites
  someone else's change made meanwhile.
- **What it never does:** delete anything for good, empty the trash, or
  delete media.

## Limits

A token or app may make 600 tool calls an hour, 60 of them changes.
Past that, it's told to try again later. Change them in
`config/mcp.php`:

```php
return new McpConfig(enabled: true, maxCalls: 1200, maxWrites: 120);
```

`bin/blush mcp` has no limits.
