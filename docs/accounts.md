# Accounts and roles

Accounts are the people who can sign in to [the admin](admin.md). Each
one has a username, a password, one or more **roles**, and optionally an
**author**: the author entry it writes as.

## Creating accounts

`bin/blush init` offers to create the first account, an administrator,
when there are none. After that, use the `account:*` commands:

```sh
bin/blush account:add jane                        # an administrator
bin/blush account:add sam --role=author --author=sam
bin/blush account:list
```

`account:add` asks for the password twice and doesn't show it, so it
never ends up in your shell history. That means it needs a terminal.
Passwords must be at least 12 characters.

| Command | What it does |
|---|---|
| `account:add <username>` | Create an account. `--role=` (repeat for more; administrator by default) and `--author=` (see [Authors](#authors)) |
| `account:list` | List the accounts, their roles and authors, and when each last signed in |
| `account:password <username>` | Set a new password, which signs the account out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:author <username> [slug]` | Link an account to an author, or leave out the slug to unlink it |
| `account:remove <username>` | Delete an account (`--yes` skips the question) |

Usernames are lowercase letters, digits, `.`, `_`, and `-`.

### Where accounts live

Each account is a file in `storage/accounts/`, holding a hash of the
password, never the password itself. Accounts aren't content: they stay
out of `user/` and out of git. Clearing caches never touches them.

If your host has no shell, create accounts on your own computer and
upload `storage/accounts/` with the site.

## Authors

An account can be linked to an author (see
[Content types](content-types.md)): `--author=jane` links it to
`user/content/authors/jane.md`, or to the `jane` author your posts credit
even without that file.

The author entry is the account's public side: its name in bylines, its
bio, and its archive page. The account itself (username, password, roles)
stays private. When the author has no entry yet, `account:add` and
`account:author` offer to create one and ask for the public name; say no,
and bylines show the slug until someone creates it. In the admin, **Your
profile** links to your author page, or creates it.

Entries that credit an account's author are its **own**, and so is the
author's entry. Roles decide what an account can do with its own entries
and with everyone else's. An account with no author owns nothing, which
suits someone who only runs the site.

## Roles

An account can do anything any of its roles allows.

| Role | Can |
|---|---|
| `administrator` | Everything, including managing accounts and site settings |
| `editor` | Create, edit, publish, and delete anyone's entries; manage media, menus, and regions; publish the site; clear caches |
| `author` | Create, edit, publish, and delete their own entries; upload media |
| `contributor` | Create and edit their own drafts, but never publish |

A contributor can't edit an entry once it's live, since that would change
the live site without publishing.

### Capabilities

A role is a list of **capabilities**:

| Capability | Allows |
|---|---|
| `content.create` | Creating entries |
| `content.edit`, `content.edit.others` | Editing your own entries, and others' |
| `content.publish`, `content.publish.others` | Publishing your own entries, and others' |
| `content.delete`, `content.delete.others` | Deleting your own entries, and others' |
| `media.upload`, `media.delete` | Uploading and deleting media |
| `menus.edit`, `regions.edit` | Editing menus and regions |
| `site.publish` | Publishing the site |
| `cache.clear` | Clearing caches |
| `site.settings` | Changing site settings |
| `accounts.manage` | Managing accounts |

Extensions can add their own.

### Your own roles

Add roles, or change the built-in ones, in `config/auth.php`:

```php
<?php

declare(strict_types=1);

use Blush\Auth\AuthConfig;
use Blush\Auth\Role;

return new AuthConfig(roles: [
	new Role('reviewer', 'Reviewer', ['content.edit', 'content.edit.others']),
	new Role('author', 'Author', ['content.create', 'content.edit', 'content.delete'])
]);
```

A role with the same name as a built-in one replaces it. Use `'*'` for a
role that can do everything. Roles live in `config/`, not `user/`, because
they decide who can do what.

## Signing in

- **Sessions** last two hours without a request, and twelve hours at most.
  They're kept in `storage/sessions/`, and only the admin starts them, so
  your public pages never set a cookie.
- **Too many wrong passwords** lock out that address and username for 15
  minutes: five tries for one username, or twenty for any.
- **Changing a password** signs that account out everywhere, except
  where you changed your own on **Your profile** in the admin.
- **Preferences**, such as the admin's color scheme, are each person's own,
  set on **Your profile** in the admin and kept in their account's file.

Both can be changed in `config/auth.php` and `config/session.php`; see
[Configuration](configuration.md#accounts-and-sessions).
