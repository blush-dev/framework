# The MCP server

Turning on the MCP server for AI apps, and its limits.

`config/mcp.php` · `Blush\Mcp\McpConfig`

| Option | Default | What it does |
|---|---|---|
| `enabled` | `false` | Serve the [MCP server](../../features/mcp/README.md) for AI apps, at the API's `path` with `/mcp` (`/api/mcp`), with OAuth for apps to connect |
| `maxCalls` | `600` | How many tool calls a token or app may make in an hour |
| `maxWrites` | `60` | How many of those may change something |

Off, its addresses and OAuth's are the site's own "not found" page;
`bin/blush mcp` works either way. `enabled` can also be changed in the
admin, on **Settings → AI**.
