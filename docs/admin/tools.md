# Tools

The **Tools** section has the work of looking after the site, a screen
each: [Tools](#the-tools-screen), [Jobs](jobs.md), [Logs](logs.md),
[Activity](activity.md), [Redirects](redirects.md), and
[Backups](backups.md).

The Tools section is on the rail when your account can run a tool, see
the jobs, the activity, the redirects, or the backups, or read the log,
and each screen is in its panel when your account can use it.

## The Tools screen

The **Tools** screen has the tasks you'd otherwise run from the
command line, as buttons, grouped by where they come from
(Blush, the site, or a plugin). The result of each stays beside its
button. A tool that asks first says so.

| Tool | What it does | Who can run it |
|---|---|---|
| Publish | Put content changes live, like `bin/blush publish` | Anyone with `site.publish` (editors) |
| Reindex Content | Bring the content and media indexes up to date with your files | Anyone with `site.publish` |
| Clear Caches | Empty the page, body, and fragment caches | Anyone with `cache.clear` (editors) |

Tools you can't run don't appear. Plugins can add their own (see
[Tools](../features/tools.md)); the same tools can be run
[from the command line](../reference/cli/README.md) and, unless they're for looking
after the site as these three are, by [AI apps](../features/mcp/README.md). Publish and
Reindex Content run as [background jobs](../running/cron-and-jobs.md):
the button shows their progress while they run, and if you leave the
screen, they finish on their own.
