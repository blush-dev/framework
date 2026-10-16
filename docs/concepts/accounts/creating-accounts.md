# Creating accounts

`bin/blush init` offers to create the first account, the site's
[owner](owners.md), when there are none. After that, create them in
[the admin](../../admin/README.md) (**Users → Accounts → New
Account**), or with the `account:*` commands:

```sh
bin/blush account:add                                    # asks for everything
bin/blush account:add jane --email=jane@example.com     # the owner, on a site without one
bin/blush account:add sam --email=sam@example.com --role=author --author=sam --name="Sam Smith"
bin/blush account:add lee --email=lee@example.com --link  # a link for Lee to choose a password
bin/blush account:list
```

In a terminal, `account:add` asks for whatever you leave out: the
username, a name (optional), the email address, its roles, the profile
it writes as (none, one no account has yet, or a new one at its
username), and whether to set its password now or make a one-time link.
A password is asked for twice without showing it, so it never ends up
in your shell history. A link (`--link`) is printed for you to send, and
works once, for the same time as the admin's
[links](password-links.md); it needs no terminal, so a script can make
accounts with it. Passwords must be at least 12 characters.

| Command | What it does |
|---|---|
| `account:add [<username>]` | Create an account, asking for what you leave out. `--email=`, `--role=` (repeat for more; owner while the site has none, else administrator), `--author=` (its profile; see [Profiles](profiles.md)), `--name=` (see [Names](names.md)), and `--link` (a one-time link for choosing the password, instead of one) |
| `account:list` | List the accounts, their names, emails, roles, and profiles, and when each last signed in |
| `account:password <username>` | Set a new password, which signs the account out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:name <username> ["name"]` | Name an account, or leave out the name to remove it |
| `account:email <username> <email>` | Change an account's email address |
| `account:author <username> [slug]` | Link an account to a profile, or leave out the slug to unlink it |
| `account:suspend <username>` | Sign an account out and stop it signing in, keeping it |
| `account:reinstate <username>` | Let a suspended account sign in again |
| `account:remove <username>` | Delete an account (`--yes` skips the question) |
| `token:add <username>` | Make an [API token](../../features/api/tokens.md) for an account |

Usernames are lowercase letters, digits, `.`, `_`, and `-`.

An account for an app, a script, or an AI agent is a
[token-only account](token-only-accounts.md).
