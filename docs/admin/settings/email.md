# Email settings

**Settings → Email** sets who the site's email is from.

- **From name** and **From address**: the site's name and email address
  until you set them (your mail service must allow the address).

Shown, not changed here:

- **Transport:** `MAIL_DSN` in `.env`, without its password, or **PHP's
  own mail (no setup)** when none is set, with a warning on a live
  site, since that mail often lands in spam (see
  [Configuration](../../reference/configuration/README.md)).
- **Send a Test** emails your own address at once and says why if it
  can't.
