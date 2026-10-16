# Forgot your password?

Where the site sends email, the sign-in screen has **Forgot your
password?**: enter your username or email address, and the account is
emailed a link to choose a new password. That link lasts an hour
(`resetLinkLifetime` in `config/auth.php`). The screen says the same
thing whether or not the account exists, so it never tells anyone
which accounts there are. A suspended account is sent nothing, an
account is sent one link every five minutes at most, and one address
may ask five times in fifteen minutes (`maxAttempts` and `lockout`).

Someone who manages accounts can also make or email a
[password link](password-links.md) for you.
