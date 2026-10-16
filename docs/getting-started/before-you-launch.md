# Before you launch

Blush runs as a PHP site on your host, where you publish changes as you
make them. Before the site goes live, set it up for production and check
it.

## The live site's `.env`

In the live site's `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL="https://example.com"
```

For email that arrives (password links), add your host's or a mail
service's SMTP server:

```ini
MAIL_DSN="smtp://user:password@smtp.example.com:587"
```

Without it, the site sends through PHP's own mail, which often lands in
spam (see [Email](../reference/configuration/mail.md)).

The live site needs its own `SECRETS_KEY` (`bin/blush init` adds one).
If you upload `storage/secrets.json` from another copy of the site, its
`SECRETS_KEY` has to come too, or the [secrets](../features/secrets.md)
stored there can't be read; it's simpler to set them again on the live
site.

## Check the site

```sh
bin/blush doctor
```

On a live site, `doctor` also asks the site's own address (`APP_URL`)
for `.env`, `config/app.php`, and a file in `storage/`. If any of them
downloads, your web server is pointed at the site's folder instead of
`public/`, and anyone can read your secrets and accounts; `doctor` fails
until that's fixed. If it can't reach the address (a firewall, or a
certificate it can't check), it only warns. Site Health runs the same
check when you choose Check Again.

Then check your content:

```sh
bin/blush content:lint
```

## After launch

- [Publishing](../running/publishing.md): putting changes live.
- [Caching](../running/caching.md): speeding the site up.
- [Cron and background jobs](../running/cron-and-jobs.md): scheduled
  posts and work that runs in the background.
- [Backups](../running/backups.md).
