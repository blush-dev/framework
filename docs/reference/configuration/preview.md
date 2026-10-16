# Preview links

Signed links that show drafts to people who aren't signed in.

`config/preview.php` · `Blush\Preview\PreviewConfig`

| Option | Default | What it does |
|---|---|---|
| `secret` | `null` | Signs preview links; they're off without one |
| `lifetime` | `604800` | How many seconds a link works (a week) |
| `path` | `'/_blush/preview'` | The preview URL |

Without this file, `APP_SECRET` is used. Keep the secret in `.env`
either way. See [Previewing drafts](../../admin/previewing-drafts.md).
