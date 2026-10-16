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

It's served at `/api/mcp`, beside the [API](../api/README.md), and
moves with the API's `path` (`path: '/data'` serves `/data/mcp`). It
doesn't need the API turned on. While it's off, its addresses are your
site's ordinary "not found" page. It needs HTTPS, except on
`localhost`.

## Pages

| Page | What it covers |
|---|---|
| [Connecting an app](connecting-an-app.md) | Approving an app, your connected apps, tokens, and running the server on your own computer |
| [What an app can do](what-an-app-can-do.md) | The tools an app sees, what it reads and writes, and its limits |
| [Activity and Undo](activity-and-undo.md) | Every change listed on Tools → Activity, and undoing it |
| [For developers](for-developers.md) | The transport, protocol versions, and OAuth |

To add tools of your own from a plugin, see [Tools](../tools.md).
