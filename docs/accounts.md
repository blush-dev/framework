# Accounts and roles

Accounts are the people who can sign in to [the admin](admin.md). Each
one has a username, an **email address** (every account needs one; see
[Email addresses](#email-addresses)), a password, one or more
**roles**, and optionally a **display name** (see [Names](#names)) and
a **profile**: its public side on the site.

## Creating accounts

`bin/blush init` offers to create the first account, an administrator,
when there are none. After that, create them in [the admin](admin.md#accounts-and-roles)
(**Users → Accounts → New Account**), or with the `account:*`
commands:

```sh
bin/blush account:add jane --email=jane@example.com     # an administrator
bin/blush account:add sam --email=sam@example.com --role=author --author=sam --name="Sam Smith"
bin/blush account:list
```

`account:add` asks for the email address when `--email` is left out,
and for the password twice without showing it, so it never ends up in
your shell history. That means it needs a terminal.
Passwords must be at least 12 characters.

| Command | What it does |
|---|---|
| `account:add <username>` | Create an account. `--email=` (asked for when left out), `--role=` (repeat for more; administrator by default), `--author=` (its profile; see [Profiles](#profiles)), and `--name=` (see [Names](#names)) |
| `account:list` | List the accounts, their names, emails, roles, and authors, and when each last signed in |
| `account:password <username>` | Set a new password, which signs the account out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:name <username> ["name"]` | Name an account, or leave out the name to remove it |
| `account:email <username> <email>` | Change an account's email address |
| `account:author <username> [slug]` | Link an account to a profile, or leave out the slug to unlink it |
| `account:suspend <username>` | Sign an account out and stop it signing in, keeping it |
| `account:reinstate <username>` | Let a suspended account sign in again |
| `account:remove <username>` | Delete an account (`--yes` skips the question) |

Usernames are lowercase letters, digits, `.`, `_`, and `-`.

### Where accounts live

Each account is a file in `storage/accounts/`, holding a hash of the
password, never the password itself. Accounts aren't content: they stay
out of `user/` and out of git. Clearing caches never touches them.

If your host has no shell, create the first account on your own
computer and upload `storage/accounts/` with the site; create the rest
in the admin.

### Password links

Blush doesn't send email. When you create an account in the admin, it
gets a **password link** instead of a password: copy it from the
account's screen and send it however you like. The person opens it,
chooses a password, and is signed in. A link works once and lasts a
week (`passwordLinkLifetime` in `config/auth.php`); only a hash of it is
kept, so it can't be shown again. Until it's used, the account is
**Invited**.

For a forgotten password, **Make a password link** on the account's
screen. The old password keeps working until the link is used, and a new
link replaces the old one.

### Suspending and removing

A **suspended** account is signed out at once and can't sign in or use
a password link until it's reinstated; nothing else about it changes.
**Removing** an account deletes its file. Either way, its profile and
the entries crediting it stay.

## Email addresses

Every account needs an email address: `account:add`, `init`, and **New
Account** ask for one, and no two accounts may share one (in any
case). It's for the people who manage accounts, shown on the account's
screen and in the Accounts list; Blush sends no email, so password
links are still yours to send. Change it with `account:email`, on the
account's screen, or on **Your account**.

An account made before email addresses has none until it's given one;
the admin marks it **No email** and asks for one.

## Names

An account's **display name** is what the admin calls the person: in the
account menu, the account lists, and messages like "Suspended Sam
Smith." It's any text up to 100 characters on one line, so write it the
way the person writes it ("Sam Smith", "Dr. Ana María Ruiz", "李明").
Only people who sign in see it.

Set it when creating an account (`--name=`, or **Display name** on
**New Account**), change it with `account:name`, on the account's
screen, or on **Your account**, where everyone can change their own.

The display name is the account's own, in the admin; on the site, the
person's name is their profile's title. An account without a display
name goes by its profile's title, then its username.

## Profiles

An account is someone who can sign in. Their public side is a separate
thing, a **profile** (see [Content types](content-types.md#built-in-types)):
the name in bylines, a bio, and a page on the site. An account can be
linked to one: `--author=jane` links it to `user/content/profiles/jane.md`,
or to the `jane` your entries credit even without that file. A profile
belongs to one account: linking one that another account has is
refused (unlink it there first). A profile
with no account is a guest profile, and an account with no profile
doesn't appear on the site.

The account itself (username, password, roles) stays private. When the
profile has no file yet, `account:add` and `account:author` offer to
create one and ask for the public name; say no, and bylines show the
slug until someone creates it. In the admin, an account's **Public
Profile** panel links, unlinks, creates, or publishes its profile, and
**Your account** links to your own (see
[The admin](admin.md#profiles)). Unlinking leaves the profile and its
bylines in place.

Entries that credit an account's profile are its **own** (through the
type's main byline, its first people field), and so is the profile
itself. Roles decide what an account can do with its own entries and
with everyone else's. An account with no profile owns nothing, which
suits someone who only runs the site.

## Roles

An account can do anything any of its roles allows.

| Role | Can |
|---|---|
| `administrator` | Everything, including managing accounts and site settings |
| `editor` | Create, edit, publish, and delete anyone's entries; manage media, menus, and regions; publish the site; clear caches |
| `author` | Create, edit, publish, and delete their own entries; upload media |
| `contributor` | Create and edit their own drafts, but never publish |
| `member` | Sign in and look after their own account, nothing else |

A contributor can't edit an entry once it's live, since that would change
the live site without publishing.

**Member** is what an account holds when it holds no other role: a new
account starts as one, and taking someone's last role leaves them a
member. It's held only on its own (giving any other role takes it
away), and it never has a capability: neither the admin nor
`config/auth.php` can change it. So someone who can create accounts
but not give roles (`accounts.create` without `accounts.roles`) can
only make members.

### Capabilities

A role is a list of **capabilities**. What it may do to entries is set
for each content type, with the type's name in the middle
(`content.post.edit` is editing your own posts):

| Capability | Allows |
|---|---|
| `content.{type}.create` | Creating entries of the type |
| `content.{type}.edit`, `content.{type}.edit.others` | Editing your own entries, and others' |
| `content.{type}.publish`, `content.{type}.publish.others` | Publishing your own entries, and others' |
| `content.{type}.delete`, `content.{type}.delete.others` | Deleting your own entries, and others' |

Use `*` for the type to grant it on every type, including types added
later: `content.*.edit`. The built-in roles do. A `.others` capability
only works alongside its own (`content.post.edit.others` needs
`content.post.edit`). The admin shows a type only to accounts that can
edit its entries.

The rest are for the whole site:

| Capability | Allows |
|---|---|
| `media.upload`, `media.delete` | Uploading and deleting media |
| `menus.edit`, `regions.edit` | Editing menus and regions |
| `site.publish` | Publishing the site |
| `cache.clear` | Clearing caches |
| `site.settings` | Changing site settings |
| `accounts.view` | Seeing accounts and roles (each account action below also needs it) |
| `accounts.create` | Creating accounts, with their first roles |
| `accounts.edit` | Changing an account's name and profile, and making password links |
| `accounts.roles` | Giving and taking roles |
| `accounts.suspend` | Suspending and reinstating accounts |
| `accounts.delete` | Removing accounts |
| `roles.manage` | Making, changing, resetting, and deleting roles |

Each kind of extension has its own, with the kind in the middle:
`themes`, `plugins`, or `icon-packs` (`extensions.plugins.delete` is
deleting plugins):

| Capability | Allows |
|---|---|
| `extensions.{kind}.view` | Seeing the kind's screen (each action below also needs it) |
| `extensions.{kind}.install` | Installing them (not in the admin yet) |
| `extensions.{kind}.update` | Updating them (not in the admin yet) |
| `extensions.{kind}.activate` | Activating a theme, or turning plugins and icon packs on and off |
| `extensions.{kind}.delete` | Deleting them |

Use `*` for the kind to grant it on every kind: `extensions.*.view`.
Only the Administrator has them built in.

Plugins can add their own.

### Your own roles

Make roles in the admin (**Users → Roles → New Role**), or start one
from an existing role with **Duplicate**. The admin can also change
what the built-in Editor, Author, and Contributor can do (and reset
them), but never the Administrator, who can always do everything. Roles
made or changed in the admin are kept in `storage/roles.json`, beside
the accounts and, like them, out of git.

Some rules keep this safe: nobody can give a role or a capability they
don't have themselves, change an account that can do more than they
can, or change their own account; and a change that would leave no
account (that isn't suspended) with all seven `accounts.*` and
`roles.manage` capabilities is refused.

Developers can also define roles in `config/auth.php`:

```php
<?php

declare(strict_types=1);

use Blush\Auth\AuthConfig;
use Blush\Auth\Role;

return new AuthConfig(roles: [
	new Role('reviewer', 'Reviewer', ['content.*.edit', 'content.*.edit.others'], 'Reads and fixes drafts.'),
	new Role('cook', 'Cook', ['content.recipe.create', 'content.recipe.edit', 'content.recipe.publish'], 'Writes recipes.')
]);
```

A role there with the same name as a built-in one, or one made in the
admin, replaces it, and the admin shows it read-only. Use `'*'` for a
role that can do everything. Roles live in `config/` or `storage/`,
never `user/`, because they decide who can do what: pulling content
changes from git can't change them.

## Signing in

- **Sessions** last two hours without a request, and twelve hours at most.
  They're kept in `storage/sessions/`, and only the admin starts them, so
  your public pages never set a cookie.
- **Too many wrong passwords** lock out that address and username for 15
  minutes: five tries for one username, or twenty for any.
- **Changing a password** signs that account out everywhere, except
  where you changed your own on **Your account** in the admin.
- **A suspended account** can't sign in; with the right password, it's
  told it's suspended.
- **Preferences**, such as the admin's color scheme, are each person's own,
  set on **Your account** in the admin and kept in their account's file.

Both can be changed in `config/auth.php` and `config/session.php`; see
[Configuration](configuration.md#accounts-and-sessions).
