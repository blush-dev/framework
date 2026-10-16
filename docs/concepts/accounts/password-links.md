# Password links

When you create an account in the admin, it gets a **password link**
instead of a password. The person opens it, chooses a password, and is
signed in. A link works once and lasts a week (`passwordLinkLifetime`
in `config/auth.php`); only a hash of it is kept, so it can't be shown
again. Until it's used, the account is **Invited**.

If your site [sends email](../../reference/configuration/README.md)
(it does with no setup on most servers, though SMTP is more reliable),
**New Account** emails the link to the account's address (untick
**Email them a link to choose a password** to copy it instead).
Otherwise, copy it from the account's screen and send it however you
like.

For a forgotten password, **Make a password link** on the account's
screen, or **Email a password link** where the site sends email. The old
password keeps working until the link is used, and a new link replaces
the old one. People can also ask for one themselves from the sign-in
screen (see [Forgot your password?](forgot-password.md)).

From the command line, `account:add --link` prints a link for a new
account (see [Creating accounts](creating-accounts.md)).
