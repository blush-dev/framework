# Suspending and removing accounts

A **suspended** account is signed out at once and can't sign in or use
a password link until it's reinstated; nothing else about it changes.
**Removing** an account deletes its file. Either way, its profile and
the entries crediting it stay.

Suspend, reinstate, and remove accounts on their screens in the admin,
or with `account:suspend`, `account:reinstate`, and `account:remove`
(see [Creating accounts](creating-accounts.md)). Suspending a
[token-only account](token-only-accounts.md) stops its tokens until
it's reinstated. An [owner](owners.md) can only be suspended or
removed by another owner.
