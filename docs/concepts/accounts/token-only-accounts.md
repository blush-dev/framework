# Token-only accounts

An app, a script, or an AI agent that uses the
[API](../../features/api/README.md) gets an account of its own: a
**token-only** account. It never signs in: it has no password and no
password link, and an email address is optional. It does what its roles
allow through its [API tokens](../../features/api/tokens.md), so an
account with a role that can edit but not publish only ever writes
drafts.

Make one in the admin (**New Account**, then **Token-only**), where its
first token can be made with it, or with `account:add --token-only`,
then `token:add`:

```sh
bin/blush account:add claude --token-only --role=editor --name=Claude
bin/blush token:add claude --name="Claude Desktop"
```

Suspending a token-only account stops its tokens until it's
reinstated.

The same account can connect to the
[MCP server](../../features/mcp/connecting-an-app.md) with its token.
