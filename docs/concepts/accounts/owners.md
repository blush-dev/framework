# Owners

The **Owner** role always has every [capability](capabilities.md),
including those plugins add later, and only an owner can change an
owner's account (its roles, standing, details, and password links) or
make another owner. Since nobody changes their own account, an owner
can't be suspended, removed, or demoted by anyone but another owner,
and the last one can't be at all. So you can give someone
**Administrator**, to run the site, and still never be locked out of
it. Neither the admin nor `config/auth.php` can change the Owner role.

A new site's first account is its owner. A site from before owners
has none, and `bin/blush doctor` warns of it. Make one with
`bin/blush account:roles jane --role=owner`, or, without a shell, from
the admin: anyone who can do all an Administrator can sees **Make me
the owner** on **Your Account** (see [The admin](../../admin/README.md))
while the site has no owner, and can give the role to others until it
has one.
