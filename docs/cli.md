# Command line

Blush comes with a command-line tool. Run it from your site's folder:

```sh
bin/blush                   # list every command
bin/blush help content:new  # how to use one command
```

Options that work with every command: `-v` for more detail (`-vv`, `-vvv`
for even more), `-q` for errors only, `-n` to never ask questions, and
`--no-ansi` to turn off colors.

## Setting up

| Command | What it does |
|---|---|
| `init` | Set up a new site: create `.env` (asking for the basics), create the `storage/` folders, report any Blush can't write to, and offer to create the first admin account. `--webhook` adds a `PUBLISH_SECRET`, which turns on the publish webhook. Safe to run again; it never changes an existing `.env` except to add that secret. |
| `doctor` | Check that the site is set up to run: PHP and its extensions, `.env`, risky production settings, `public/`, and writable storage. Fails when something needs fixing. |

## Accounts

See [Accounts and roles](accounts.md).

| Command | What it does |
|---|---|
| `account:add <username>` | Create an admin account, asking for its password. `--role=` (repeat for more; administrator by default) and `--author=` |
| `account:list` | List the accounts with their roles, authors, and last sign-in |
| `account:password <username>` | Set an account's password, signing it out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:author <username> [slug]` | Link an account to an author entry, or unlink it |
| `account:remove <username>` | Delete an account. `--yes` skips the question. |

## Everyday

| Command | What it does |
|---|---|
| `serve` | Run the site at http://127.0.0.1:8000. `--port=8080` and `--host=0.0.0.0` change where. `--static` previews the [static export](going-live.md#static-export) instead. |
| `content:new <type> "<title>"` | Create an entry. `--slug=` sets its URL name; `--draft` makes it a draft. Dated types get a date in the file name. |
| `content:list` | List every entry. `--type=post` and `--status=draft` (or `published`, `scheduled`) narrow it down. |
| `content:lint` | Check front matter for problems. `--strict` also reports unknown keys and 1.x names. |
| `routes:list` | Show every URL pattern and redirect, and which one wins when two overlap |

## Publishing and caches

| Command | What it does |
|---|---|
| `publish` | Put content changes live: reindex, refresh, and clear the caches. `--pull` runs `git pull` in `user/` first; `--no-pull` skips it. See [Going live](going-live.md#publishing-changes). |
| `cache:compile` | Precompile config, routes, content types, themes, and extensions for speed |
| `cache:clear` | Clear every compiled file and cache. Flags clear just one: `--config`, `--extensions`, `--container`, `--routes`, `--types`, `--themes`, `--store`. |
| `content:index` | Update the content index. `--full` rebuilds it from scratch. (`publish` does this for you.) |
| `schedule:run` | For cron: puts scheduled posts live on time, and prunes the cache and idle admin sessions |
| `build` | Export the site to static files in `storage/export/`. Takes `--base-url=`, `--incremental`, and `--no-crawl`. |
| `media:publish` | Link `user/media` into `public/` so the web server serves it. `--copy` copies instead, for hosts without symlinks. |

## Themes

| Command | What it does |
|---|---|
| `theme:list` | List installed themes, and which is active |
| `theme:activate <slug>` | Switch themes |
| `theme:new <slug>` | Create a theme in `user/themes/`. `--name=` names it; `--parent=` builds it on another theme. Its `theme.json` points editors at the [schema](themes.md#autocomplete-in-your-editor). |
| `theme:check [slug]` | Check a theme's manifest, settings, components, menus and regions, and accessibility basics. `--strict` shows notices too. |
| `theme:why <view>` | Show which file a template name uses, such as `theme:why single-post` |
| `menu:list` | List your theme's menu locations, the [menu](menus.md) each shows, and any items that can't be shown. `--theme=` lists another theme's. |
| `menu:show <location>` | Show a location's menu as a page sees it, with every URL. `--locale=fr` shows it in another language. |
| `icon:list` | List the icons your theme can use: each one's full name, its label, and the file that draws it. `--theme=` lists another theme's. |
| `component:list` | List the components your theme can use: each one's full name, its label, whether it's registered, its class (if it has one), and the file that draws it. Also points out files in `components/` that aren't named for a component. `--theme=` lists another theme's. |
| `theme:publish` | Copy the active theme's files (and its parents') into `public/`. `--all` copies every theme's. |
