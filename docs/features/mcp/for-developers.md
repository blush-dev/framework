# The MCP server for developers

How Blush's [MCP server](README.md) speaks the protocol, and how apps
sign in to it.

## Transport and protocol

The server follows the MCP specification's Streamable HTTP transport,
answering each `POST` as JSON: it opens no streams and keeps no
sessions. It speaks protocol versions `2025-11-25`, `2025-06-18`, and
`2025-03-26`, and offers tools only.

## OAuth

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

## Adding tools

To add tools from a plugin, see [Tools](../tools.md).
