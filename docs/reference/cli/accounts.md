# Accounts

Commands for admin accounts and API tokens. See
[Accounts](../../concepts/accounts/README.md) and
[The admin](../../admin/README.md).

| Command | What it does |
|---|---|
| `account:add [<username>]` | Create an admin account. In a terminal it asks for what you leave out: the username, a name, the email address, roles, the profile it writes as, and its password, or a one-time link to send instead (`--link`, which needs no terminal). `--role=` (repeat for more; owner while the site has none, else administrator) `--author=` (its profile, which no other account may have; a slug with no profile makes one, a draft, asking for its public name), `--name=`, and `--link`. `--token-only` makes a [token-only account](../../concepts/accounts/token-only-accounts.md) instead, for an app or agent: no password or profile, and an email address is optional. |
| `account:list` | List the accounts with their names, emails, roles, profiles, and last sign-in, marking token-only ones |
| `account:password <username>` | Set an account's password, signing it out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:name <username> ["name"]` | Set the name the admin calls an account by, or remove it |
| `account:email <username> <email>` | Change an account's email address, which every account needs; it changes at once, and the old address is told |
| `account:author <username> [slug]` | Link an account to a profile (making it, a draft, when there's none; refused when another account has it), or unlink it |
| `account:remove <username>` | Delete an account. `--yes` skips the question. |
| `token:add <username>` | Make an [API token](../../features/api/tokens.md) for an account and print it once (with `--quiet`, alone on its line). `--name=` says what it's for (asked for when left out), `--expires=` is `30`, `90` (the default), `365`, or `never` days, and `--capabilities=` narrows it, separated by commas (`--capabilities=content.*.edit,content.*.create`). |
