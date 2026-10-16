# Accounts and roles

Accounts are the people who can sign in to [the admin](../../admin/README.md).
Each one has a username, an **email address** (every account needs
one; see [Email addresses](email-addresses.md)), a password, one or
more **roles**, and optionally a **display name** (see
[Names](names.md)) and a **profile**: its public side on the site.

| Page | What it covers |
|---|---|
| [Creating accounts](creating-accounts.md) | The first account, New Account, and every `account:*` command |
| [Where accounts live](where-accounts-live.md) | `storage/accounts/`, ids, and hosts without a shell |
| [Password links](password-links.md) | One-time links for choosing a password |
| [Forgot your password?](forgot-password.md) | Emailed links from the sign-in screen |
| [Suspending and removing](suspending-and-removing.md) | Stopping an account signing in, or deleting it |
| [Token-only accounts](token-only-accounts.md) | Accounts for apps, scripts, and AI agents |
| [Email addresses](email-addresses.md) | Every account's address, and changing it |
| [Names](names.md) | What the admin calls each person |
| [Profiles](profiles.md) | An account's public side on the site |
| [Roles](roles.md) | The built-in roles, and Member |
| [Capabilities](capabilities.md) | Every capability a role can hold |
| [Your own roles](your-own-roles.md) | Making and changing roles, in the admin or in `config/auth.php` |
| [Owners](owners.md) | The role that can always do everything |
| [Signing in](signing-in.md) | Sessions, lockouts, and preferences |

API tokens, which let something use the API as an account, are on
[API tokens](../../features/api/tokens.md).
