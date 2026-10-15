# AI apps and MCP

Blush has an MCP server, so AI apps can work on your site with you.
MCP (the Model Context Protocol) is how apps such as Claude, ChatGPT,
Cursor, and Claude Code use another program's tools. Once you connect
one, you can ask it things like:

- "What did I write about sourdough last year?"
- "Draft a post from these notes, and tag it with tags I already use."
- "Find images missing alt text and fill it in."
- "Fix the typo in the third paragraph of the About page."
- "Which posts link to the page I'm about to delete?"

An app acts as the person who connected it, with what that person may
do on the site, or less if they choose. New entries are always drafts,
nothing is ever deleted for good, and every change it makes is listed
in the admin with **Undo**.

## Turning it on

The MCP server is off until you turn it on, in the admin on
**Settings → AI**, or in `config/mcp.php`:

```php
<?php

declare(strict_types=1);

use Blush\Mcp\McpConfig;

return new McpConfig(enabled: true);
```

It's served at `/api/mcp`, beside the [API](api.md), and moves with the
API's `path` (`path: '/data'` serves `/data/mcp`). It doesn't need the
API turned on. While it's off, its addresses are your site's ordinary
"not found" page. It needs HTTPS, except on `localhost`.

## Connecting an app

**Settings → AI** shows the server's address with a **Copy** button.
Paste it into the app's connector settings (in Claude, **Settings →
Connectors → Add custom connector**). The app sends you to your site to
approve it:

1. Sign in, if you aren't already.
2. Check which site it's for, which app is asking, and which account it
   will act as. An app that only says where it's from, rather than
   proving it, is marked as such.
3. Choose what it may do:
   - **Read:** read what you can see, drafts included. Change nothing.
   - **Write drafts:** read, and create and edit drafts, and upload
     media. Never publish, trash, or change what's live.
   - **Same as your account:** everything you can do, publishing
     included.
   - **Choose:** some of what you may do, picked one by one.

   The app's own request is chosen at first. You can pick less than it
   asked for, never more.
4. **Allow** sends you back to the app, connected. **Deny** sends you
   back without connecting it.

Connecting an app takes **Connect apps** (`api.connect`), which the
Owner, Administrator, Editor, Author, and Contributor roles have; a
Member doesn't. An app you approved before, with as much, connects
again without asking.

### Your connected apps

Each app you've connected is listed on **Your Account → API Tokens**,
under **Connected Apps**: what it may do, when you approved it, when it
last connected, and a link to its changes. **Disconnect** stops it at
once; connecting it again asks for approval again. **Revoke All**
disconnects every app along with your tokens.

An app's access lasts an hour at a time and renews itself for as long
as you leave it connected. If its renewal key is ever used twice,
which only happens when it's been copied, the app is disconnected.

### Scripts and apps with a token

An app or script that takes a token instead of signing in can use an
[API token](api.md#tokens), sent as `Authorization: Bearer`. In Claude
Code:

```
claude mcp add --transport http blush https://example.com/api/mcp --header "Authorization: Bearer blush_0199…"
```

For an agent, make it an account of its own, a
[token-only account](accounts.md#token-only-accounts), with a role
that fits.

### On your own computer

An agent running on the same computer as your site can start the MCP
server itself, as one account, with no token:

```
bin/blush mcp --account=jane
```

In Claude Code:

```
claude mcp add blush -- /path/to/site/bin/blush mcp --account=jane
```

It works whether or not the MCP server is on for the web, since whoever
can run `bin/blush` has the site already. A suspended account can't
use it. Prefer it to letting an agent edit your files directly: it
writes through the same checks as the admin, keeps ids and
relationships right, and lists every change in the activity log.

## What an app can do

An app sees only the tools its access allows:

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

Plugins can add their own; **Settings → AI** lists every tool on your
site, with what it needs.

- **What it reads:** what its account may see. Unlike the API, that
  includes drafts and scheduled entries the account may edit, but not
  the trash unless it asks.
- **What it writes:** everything goes through the same checks as the
  admin: what the account may do, required fields to publish,
  relationships' limits, raw HTML, and which slugs are free. A change
  names the version of the entry it was made to, so it never overwrites
  someone else's change made meanwhile.
- **What it never does:** delete anything for good, empty the trash, or
  delete media.

### Limits

A token or app may make 600 tool calls an hour, 60 of them changes.
Past that, it's told to try again later. Change them in
`config/mcp.php`:

```php
return new McpConfig(enabled: true, maxCalls: 1200, maxWrites: 120);
```

`bin/blush mcp` has no limits.

## Activity and Undo

Every change to entries and media is listed under **Tools → Activity**,
whoever made it and however: in the admin, by a connected app, on the
command line (`content:new` and `bin/blush mcp`; a command that names
no account is listed as **Someone on the command line**), or through
the API. Each row says what happened, who did
it, how (an app's changes show its name), and when, and has **Undo**
where it can be undone. Filter by who, how, and what changed.

- **Undo** puts back what the change replaced, only while nothing has
  changed since. An undo is a change of its own, listed like any other.
- A new entry is undone by moving it to the trash, and an upload by
  deleting the file.
- What a change replaced is kept for 30 days; the change stays listed
  for 90.

An entry's own changes are on its **Document** tab, in the **Activity**
row, which says when an app made the latest one. Seeing all of the
activity takes **See activity and undo changes** (`site.activity`),
which the Owner, Administrator, and Editor have; anyone may see and
undo the changes to an entry they may edit.

## For developers

The server follows the MCP specification's Streamable HTTP transport,
answering each `POST` as JSON: it opens no streams and keeps no
sessions. It speaks protocol versions `2025-11-25`, `2025-06-18`, and
`2025-03-26`, and offers tools only.

Apps sign in with OAuth 2.1, with Blush as the authorization server:

| Address | What it is |
|---|---|
| `/.well-known/oauth-protected-resource/api/mcp` | The MCP server's resource metadata |
| `/.well-known/oauth-authorization-server` | The authorization server's metadata |
| `/admin/authorize` | Where a person approves an app |
| `/oauth/token` | Codes and refresh tokens for access tokens |
| `/oauth/register` | Dynamic client registration |
| `/oauth/revoke` | Revoking a token |

- **Clients** are public: they prove themselves with PKCE (`S256`
  only). A client may register itself, or go by the address of its own
  metadata document (a `client_id` that's an HTTPS address), which
  proves where it's from. One nobody approves within a day is removed.
- **Scopes** are `read`, `drafts`, and `account`. A request without
  one asks for `account`.
- **Access tokens** last an hour and work only on the MCP server, never
  on `/api/v1`. **Refresh tokens** last 90 days and are replaced each
  time they're used.

To add tools from a plugin, see [Extending](extending.md#tools).
