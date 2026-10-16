# Signing in

How sessions, wrong passwords, and preferences work for accounts that
sign in to the admin.

- **Sessions** last two hours without a request, and twelve hours at most.
  They're kept in `storage/sessions/`, and only the admin starts them, so
  your public pages never set a cookie.
- **Too many wrong passwords** lock out that address and username for 15
  minutes: five tries for one username, or twenty for any.
- **Changing a password** signs that account out everywhere, except
  where you changed your own on **Your account** in the admin.
- **A suspended account** can't sign in; with the right password, it's
  told it's suspended.
- **Preferences**, such as the admin's color scheme, are each person's own,
  set on **Your account** in the admin and kept in their account's file.

Both can be changed in `config/auth.php` and `config/session.php`; see
[Configuration](../../reference/configuration/README.md).

Forgot your password? See [Forgot your password?](forgot-password.md).
