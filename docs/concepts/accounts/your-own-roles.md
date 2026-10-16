# Your own roles

Make roles in the admin (**Users → Roles → New Role**), or start one
from an existing role with **Duplicate**. The admin can also change
what the built-in Administrator, Editor, Author, and Contributor can
do (and reset them), but never the Owner, who can always do
everything. Roles made or changed in the admin are kept in
`storage/roles.json`, beside the accounts and, like them, out of git.

Some rules keep this safe: nobody can give a role or a capability they
don't have themselves, change an account that can do more than they
can, or change their own account; only an owner can change an owner's
account or give the Owner role; and a change that would leave no
account (that isn't suspended) with all seven `accounts.*` and
`roles.manage` capabilities is refused.

## Roles in config

Developers can also define roles in `config/auth.php`, from the
[capabilities](capabilities.md) there are:

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
