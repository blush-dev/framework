# Publishing

The publish webhook, and pulling content with git.

`config/publish.php` · `Blush\Publish\PublishConfig`

| Option | Default | What it does |
|---|---|---|
| `secret` | `null` | The webhook secret; the webhook is off without one |
| `git` | `false` | Run `git pull` in `user/` when publishing |
| `remote`, `branch` | `null` | What to pull |
| `path` | `'/_blush/publish'` | The webhook's URL |
| `tolerance` | `300` | How many seconds a webhook request's timestamp may be off |
| `maxAttempts` | `10` | Badly signed webhook requests from one address before it's locked out |
| `lockout` | `900` | How many seconds a lockout lasts, from the first failed request |
| `gitBinary` | `'git'` | The `git` command to run |

Without this file, the `PUBLISH_*` variables are used (see
[`.env`](env.md)). Keep the secret in `.env` either way. See
[Publishing](../../running/publishing.md).
