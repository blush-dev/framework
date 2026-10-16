# Where accounts live

Each account is a file in `storage/accounts/`, named for its username
and holding a hash of the password, never the password itself, and the
account's `id`. Accounts aren't content: they stay out of `user/` and
out of git. Clearing caches never touches them. On the SQLite driver
they're in the database instead (see [SQLite](../../running/sqlite.md)).

What points at an account (the session it signed in to, the media it
uploaded, the jobs it started, the Site Health problems it ignored)
holds that `id`, not the username. An account file without an `id`
gets one the next time it's saved.

If your host has no shell, create the first account on your own
computer and upload `storage/accounts/` with the site; create the rest
in the admin.
