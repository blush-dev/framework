# API tokens

An API token lets an app, a script, or an AI agent use the
[API](README.md) as one of your site's accounts. It's sent in a header
on each request:

```
Authorization: Bearer blush_0199a1b4-…_9f3c…
```

- **Making one:** on **Your Account** in the admin, under API Tokens,
  with **New Token**, or with [`token:add`](../../reference/cli/README.md).
  The token is shown once; copy it then. Only the owner and
  administrators can make tokens until a role is given **Make API
  tokens for their own account**.
- **What it can do:** what its account may, checked on every request,
  so a role change or a suspension counts at once. A token can be
  narrowed: **Read** (what anyone can read), **Write drafts**, **Same
  as the account**, or capabilities you choose.
- **For an agent:** make it an account of its own, a
  [token-only account](../../concepts/accounts/token-only-accounts.md),
  with a role that fits. One that can edit but not publish only ever
  writes drafts.
- **How long it lasts:** 30 days, 90 days (the default), a year, or
  never. The dashboard says when one of yours expires within a week.
  Expired tokens stay listed for 30 days, so you can see what stopped.
- **Revoking:** **Revoke** stops a token at once, and **Revoke All**
  stops every one of an account's. Changing a password doesn't revoke
  tokens.

A token also works on the [MCP server](../mcp/README.md), for AI apps.
A token an app gets by connecting through MCP works only there, never
on the API. A token is only accepted in the `Authorization` header,
never in the address, and only over HTTPS (plain HTTP works on
`localhost`). After many requests with a token that isn't one, the
address sending them is refused for a while.

## What a token sees

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

## Managing an account's tokens

Each account's tokens are listed on its screen in the admin, under
**API Tokens**: its name, what it can do, who made it, when it was last
used, and when it expires.

- **Your own:** on **Your Account**, with **New Token**. The owner and
  administrators can make them by default; give another role **Make API
  tokens for their own account** (`api.tokens`) to let its accounts
  make them. Anyone can see and revoke their own.
- **Someone else's:** whoever can **Edit accounts** sees and revokes an
  account's tokens, and makes them only for a token-only account, never
  with a capability they don't have themselves. A person who signs in
  makes their own. Only an owner sees or changes an owner's.
- **What it can do:** **Read** (what anyone can read), **Write drafts**,
  **Same as the account**, or capabilities you choose. Whatever it's
  given, a token never does more than its account can now.
- **How long:** 30 days, 90 days (the default), a year, or never. A
  token shows on its account's dashboard a week before it expires, and
  stays listed, marked Expired, for 30 days after.
- **Revoking:** **Revoke** stops one at once; **Revoke All** stops every
  one, for a token that may have leaked. A new password doesn't revoke
  them.

A token is shown once, when it's made. Only a hash of it is kept, in
`storage/tokens/`, beside the accounts (or in the database on the
[SQLite driver](../../running/sqlite.md)).

From the command line, `token:add <username>` makes a token for an
account (see [Creating accounts](../../concepts/accounts/creating-accounts.md)).
