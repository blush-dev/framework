# Accounts and sessions

Roles, passwords, sign-in lockouts, sign-ups, and the session cookie.

## Accounts

`config/auth.php` · `Blush\Auth\AuthConfig`

| Option | Default | What it does |
|---|---|---|
| `roles` | `[]` | Your own roles (`Blush\Auth\Role` objects), which can replace built-in ones; see [Your own roles](../../concepts/accounts/your-own-roles.md) |
| `minPasswordLength` | `12` | The shortest password accepted (at least 8) |
| `maxAttempts` | `5` | Wrong passwords allowed for one username and address before a lockout |
| `lockout` | `900` | How many seconds a lockout lasts |
| `passwordLinkLifetime` | `604800` | How many seconds a [password link](../../concepts/accounts/password-links.md) lasts (a week; at least 60) |
| `resetLinkLifetime` | `3600` | How many seconds a link asked for with **Forgot your password?** lasts (an hour; at least 60) |
| `signups` | `false` | Whether anyone can make an account on the site. Saved for now: there's no sign-up form yet |
| `signupRole` | `'member'` | The role an account made by signing up holds; never `owner` or `administrator` |

## Sessions

`config/session.php` · `Blush\Session\SessionConfig`

| Option | Default | What it does |
|---|---|---|
| `cookie` | `'blush_session'` | The session cookie's name; over HTTPS it gets the `__Host-` prefix |
| `idle` | `7200` | How many seconds a session lasts without a request |
| `lifetime` | `43200` | How many seconds a session lasts at most |
| `secure` | `null` | Force the cookie's `Secure` flag on or off; by default it's on over HTTPS |
