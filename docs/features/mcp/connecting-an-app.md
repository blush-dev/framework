# Connecting an AI app

An AI app connects to your site's [MCP server](README.md) by asking you
to approve it, with an [API token](../api/tokens.md), or, on the
computer the site runs on, by starting the server itself.

## Approving an app

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

## Your connected apps

Each app you've connected is listed on **Your Account → API Tokens**,
under **Connected Apps**: what it may do, when you approved it, when it
last connected, and a link to its changes. **Disconnect** stops it at
once; connecting it again asks for approval again. **Revoke All**
disconnects every app along with your tokens.

An app's access lasts an hour at a time and renews itself for as long
as you leave it connected. If its renewal key is ever used twice,
which only happens when it's been copied, the app is disconnected.

## Scripts and apps with a token

An app or script that takes a token instead of signing in can use an
[API token](../api/tokens.md), sent as `Authorization: Bearer`. In
Claude Code:

```
claude mcp add --transport http blush https://example.com/api/mcp --header "Authorization: Bearer blush_0199…"
```

For an agent, make it an account of its own, a
[token-only account](../../concepts/accounts/token-only-accounts.md),
with a role that fits.

## On your own computer

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
