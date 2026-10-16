# Command line

Blush comes with a command-line tool. Run it from your site's folder:

```sh
bin/blush                   # list every command
bin/blush help content:new  # how to use one command
bin/blush help --global     # the options every command takes
```

To type just `blush` from anywhere inside your site, see
[Installation](../../getting-started/installation.md).

## Commands

| Page | Commands |
|---|---|
| [Setting up](setup.md) | `init`, `doctor` |
| [Accounts](accounts.md) | `account:*`, `token:add` |
| [AI apps](ai-apps.md) | `mcp` |
| [Tools](tools.md) | `tool` |
| [Serving and routes](serve.md) | `serve`, `routes:list` |
| [Content](content.md) | `content:*` (but `content:index`) |
| [Publishing and caches](publishing.md) | `publish`, `cache:compile`, `cache:clear`, `content:index` |
| [Media](media.md) | `media:*` |
| [Background jobs](jobs.md) | `schedule:*`, `jobs:*` |
| [Storage](storage.md) | `storage:copy`, `storage:sync` |
| [Backups](backups.md) | `backup:*` |
| [Secrets](secrets.md) | `secrets:*` |
| [Plugins](plugins.md) | `plugin:list`, `plugin:new`, `plugin:check` |
| [Themes](themes.md) | `theme:*` |
| [Menus](menus.md) | `menu:*` |
| [Icons and icon packs](icons.md) | `icon:list`, `icon-pack:check` |
| [Admin themes](admin-themes.md) | `admin-theme:check` |
| [Blocks and components](blocks-and-components.md) | `block:list`, `component:list` |
| [Translations](translations.md) | `lang:extract` |

## Options every command takes

Options that work with every command: `-v` for more detail (`-vv`, `-vvv`
for even more), `-q` for errors only, `-n` to never ask questions, and
`--no-ansi` to turn off colors. In a terminal, long lines wrap to its
width and results are marked `✓`, `!`, or `✗`; piped output doesn't
wrap, and says `ok`, `warning`, or `error` instead. Colors follow
[NO_COLOR](https://no-color.org). In a terminal that shows exact colors
(`COLORTERM=truecolor`) and says whether its background is dark or
light, Blush uses its own colors, tuned for that background; otherwise
it uses your terminal's colors. If yours guesses wrong, set
`COLORFGBG=15;0` for a dark background or `COLORFGBG=0;15` for a light
one.

## Answering questions

When a command asks you something in a terminal, answer in place: type
text (a dim answer is the default, and Enter takes it), pick from a list
with the arrow keys (type to narrow it), pick several with Space, and
press Enter when you're done. Ctrl+C stops the command. When input isn't
a terminal, questions are asked a line at a time; with `-n`, every
question takes its default, and one without a default stops the command.

## Progress

Commands that take a while (indexing, checking, and fixing many files,
copying storage, publishing) show what they're doing as they go: a bar
for each step, with how far along it is (`1,203 / 4,009  30%`), or a
spinner until its size is known, and a checked line for each step done
with its count and how long it took (`4,009 · 2.6s`). These are drawn
only in a terminal (with `NO_COLOR` too, just without colors), so piped
output and logs show just the result.

A plugin can add commands of its own; see [Commands](../../features/commands.md).
