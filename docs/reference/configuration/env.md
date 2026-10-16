# `.env`

`.env` holds what differs from one machine to the next: the site's URL,
whether it's in development or production, and secrets. Never commit it.

| Variable | Default | What it does |
|---|---|---|
| `APP_ENV` | `production` | `development`, `staging`, or `production` (see [What development changes](../../concepts/configuration.md#what-development-changes)) |
| `APP_DEBUG` | `false` | Show detailed error pages. Never turn this on for a live site. |
| `APP_NAME` | `Blush` | The site's name |
| `APP_URL` | `http://localhost` | The site's full URL, such as `https://example.com` |
| `APP_TIMEZONE` | `UTC` | The site's timezone, such as `America/Chicago` |
| `APP_LOCALE` | `en_US` | The site's language and region (see the note below) |
| `APP_EMAIL` | | The site's email address, where notices for its owner are sent. `bin/blush init` writes the owner account's. One saved in the admin wins. |
| `APP_SECRET` | | Signs [preview links](../../admin/previewing-drafts.md); they're off without it. At least 32 characters. `bin/blush init` writes one. |
| `SECRETS_KEY` | | Encrypts the [secrets](../../features/secrets.md) Blush stores, such as a service's API key or token: 32 random bytes in base64. `bin/blush init` writes one. Without it, secrets can be set only in the environment. Keep it out of git, and don't lose it: stored secrets can't be read without it. |
| `SECRETS_KEY_PREVIOUS` | | The key `SECRETS_KEY` replaced, kept only to read secrets stored under it until `bin/blush secrets:rotate` encrypts them again. |
| `PUBLISH_SECRET` | | Turns on the [publish webhook](../../running/publishing.md). At least 32 characters. |
| `PUBLISH_GIT` | `false` | Run `git pull` in `user/` when publishing |
| `PUBLISH_REMOTE`, `PUBLISH_BRANCH` | | The git remote and branch to pull |
| `STORAGE_DRIVER` | `filesystem` | Where the site's data is kept (see [Storage](storage.md)) |
| `MAIL_DSN` | | How the site sends email (see [Email](mail.md)), such as `smtp://user:password@smtp.example.com:587`. Unset, PHP's own mail is used where the server has it; empty, the site sends none. |

Real environment variables (set by your host or server) win over `.env`.

The `APP_*` values (except `APP_SECRET`) reach Blush through
`config/app.php`, which reads them with `$env`. If your `config/app.php` doesn't pass `locale`, add
`locale: $env->string('APP_LOCALE', 'en_US')` to it, and for `email`,
`email: $env->string('APP_EMAIL', '')`.
