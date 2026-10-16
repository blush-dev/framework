# Roles

An account can do anything any of its roles allows. Each role is a list
of [capabilities](capabilities.md).

| Role | Can |
|---|---|
| `owner` | Everything, always, including what plugins add; only an owner changes an owner (see [Owners](owners.md)) |
| `administrator` | Everything built in but installing, updating, and deleting plugins and themes, and Site Health: accounts, roles, settings, and every entry |
| `editor` | Create, edit, publish, and delete anyone's entries; manage media, menus, and redirects; publish the site; clear caches |
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

To make roles of your own, or change what the built-in ones can do, see
[Your own roles](your-own-roles.md).
