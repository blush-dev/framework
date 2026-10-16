# Email addresses

Every account needs an email address: `account:add`, `init`, and **New
Account** ask for one, and no two accounts may share one (in any
case). It's for the people who manage accounts, shown on the account's
screen and in the Accounts list, and it's where
[password links](password-links.md) are emailed when the site sends
email. Change it with `account:email`, on the account's screen, or on
**Your account**.

## Changing an address

Where the site can send email, a new address is confirmed before it's
used, so an address can't be moved somewhere its owner doesn't know of:

- **Your own**, on **Your account**: the new address is emailed a link
  to confirm it, and the old one is told a change was asked for. Your
  account keeps its address until the link is used, within a day.
  Until then, **Your account** says what's waiting, with **Cancel
  Change**; asking for another address replaces the link.
- **Someone else's**, changed by an administrator on their account's
  screen, or with `account:email`: it changes at once, and the old
  address is told.

Once a new address is confirmed, the old one is told it changed. A site
that can't send email changes addresses at once, as there's nothing to
confirm with.

An account made before email addresses has none until it's given one;
the admin marks it **No email** and asks for one.
