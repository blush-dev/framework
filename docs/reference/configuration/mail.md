# Email

How the site sends email, and who it's from.

`config/mail.php` · `Blush\Mail\MailConfig`

| Option | Default | What it does |
|---|---|---|
| `dsn` | `null` | The transport (see below). Unset (`null`), PHP's own mail where the server has it; empty (`''`), no email at all |
| `fromAddress` | `''` | The address email is from; empty, the site's email address (`APP_EMAIL`). Saved on **Settings › Email** |
| `fromName` | `''` | The name email is from; empty, the site's name. Saved on **Settings › Email** |

Without this file, `MAIL_DSN` is used. Keep it in `.env`, since an SMTP
one holds a password; the admin shows the transport without it.

## SMTP

**Use SMTP on a live site.** Send through your host's mail server or a
mail service (most offer SMTP), with the address and password they give
you:

```
MAIL_DSN=smtp://user:password@smtp.example.com:587
```

Mail sent this way is signed for your domain, so it reaches inboxes.
Encode any `@`, `:`, or `/` in the username or password (`%40`, `%3A`,
`%2F`).

## With no setup

**With no setup,** the site sends through PHP's own mail (`mail()`), as
long as the server has a mail program for it (PHP's `sendmail_path`),
which most hosts do. It works, but mail sent straight from a web server
often lands in spam or is refused, so password links may never arrive.
**Settings › Email** says when the site is using it, and Site Health
warns about it on a live site. Use **Send a Test** there to see what
arrives.

## Other transports

| `MAIL_DSN` | What it does |
|---|---|
| `native` | PHP's own mail, as with no setup, but on purpose |
| `sendmail` | The server's `/usr/sbin/sendmail`, run directly. Many servers, DDEV's among them, don't have it there; `native` uses whatever PHP is set up with |
| `log` | Writes each email to the [log](logging.md) instead of sending it (as a warning, so it's kept at the default level). Read it on **Tools › Log** or in `storage/logs/`. For development |
| `null` | Sends nothing anywhere, quietly |
| (empty) | Turns email off: password links are copied from the admin, and sign-in has no **Forgot your password?** |

## In development

**In development**, catch email rather than sending it. On DDEV, its
mail catcher, Mailpit, takes SMTP: set `MAIL_DSN=smtp://localhost:1025`
and open it with `ddev launch -m`. (DDEV's PHP also sends `mail()`
there, so the no-setup fallback lands in Mailpit too.) Anywhere else,
`log` works.

## What the site sends

The site sends email for [password links](../../concepts/accounts/password-links.md),
from a background job, so a page never waits on a mail server and one
that's down is tried again (see [Background jobs](jobs.md)).
Password links are sent right after the page that asked for them is
served, under PHP-FPM; on other servers they wait for the next runner.
A plugin can send email through a service of its own instead of SMTP
(see [Notifications](../../features/notifications.md)).
