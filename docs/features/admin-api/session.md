# Sessions and your own account

Signing in and out, and what the signed-in account changes about itself: its password, preferences, name, and email address. Choosing a password and confirming an email address with a link need no account. Every request here is under `/admin/api`, as the [admin's API](README.md) describes.

| Request | What it does |
|---|---|
| `GET session` | The signed-in account (its username, `email`, `emailChange` (a new address waiting on its link, `{"email", "expires"}`, or `null`), `name`, `displayName`, `avatar` (as `GET accounts` has it), `author`, `profile` (as `GET accounts` has it), `created`, `roles` (each `{"name", "label"}`), capabilities, and preferences) and a CSRF token, or `{"account": null}` |
| `POST login` | Sign in with `{"username", "password"}` |
| `POST logout` | Sign out |
| `POST password` | Change the account's own password with `{"current", "password"}`; answers `204`. Other sessions are signed out; this one stays, with a new id. A wrong current password or a short new one is a `422` whose `field` names it |
| `PATCH preferences` | Change the account's own preferences: `colorScheme` (`system`, `light`, or `dark`), `adminTheme` (an admin theme's name, of one that's on, or `null` to follow the site's default), `setupSkipped` (`true` or `false`), and `shortcuts` (a list of up to 30 screen ids, such as `"media"`, `"type:post"`, or `"settings:general"`, or `null` for the default), such as `{"colorScheme": "dark"}`; answers `{"preferences"}`, which also has `lastEdited`, the id of the entry the account last saved |
| `PATCH profile` | Change the account's own `name` (`null` or empty removes it) or `email`, or both; answers `{"name", "email", "displayName", "emailChange"}`. Where the site can send email, a new address isn't saved but emailed a link to confirm it, and `emailChange` is what waits (`{"email", "expires"}`; else `null`). A name over 100 characters, or an email address that's missing, invalid, or another account's, is a `422` naming the `field` |
| `POST confirm-email` | Confirm a new email address with its link: `{"for", "token"}` (`for` is `site` or an account's id); answers `{"for", "email"}` (`for` is `site` or `account`). No account needed. A link that's expired, used, or replaced is a `410`; an address another account has taken since, a `422` |
| `DELETE email-change/{for}` | End a change of email address that waits, so its link stops working: `account` (your own) or `site` (needs `site.settings`); answers `204` |
| `POST set-password` | Choose a password with a link: `{"account", "token", "password"}`; signs in and answers `204`. No account needed. A short password is a `422` (`field` `password`); a link that's expired, used, replaced, or for a suspended account is a `410`, and too many tries a `429` |
