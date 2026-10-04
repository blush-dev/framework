# Command line

Blush comes with a command-line tool. Run it from your site's folder:

```sh
bin/blush                   # list every command
bin/blush help content:new  # how to use one command
```

To type just `blush` from anywhere inside your site, see
[the launcher script](installation.md#run-blush-from-anywhere-in-your-site-optional).

Options that work with every command: `-v` for more detail (`-vv`, `-vvv`
for even more), `-q` for errors only, `-n` to never ask questions, and
`--no-ansi` to turn off colors.

## Setting up

| Command | What it does |
|---|---|
| `init` | Set up a new site: create `.env` (asking for the basics) with an `APP_SECRET`, create the `storage/` folders, report any Blush can't write to, and offer to create the first admin account. `--webhook` adds a `PUBLISH_SECRET`, which turns on the publish webhook. Safe to run again; it never changes an existing `.env` except to add missing secrets. |
| `doctor` | Check that the site is set up to run: PHP and its extensions, `.env`, risky production settings, `public/`, and writable storage. Fails when something needs fixing. It also warns of extensions that are on but can't run: an active theme whose [requirements](extending.md#requirements) aren't met (so the default theme shows), and plugins and icon packs that are on but don't run. |

## Accounts

See [Accounts and roles](accounts.md) and [The admin](admin.md).

| Command | What it does |
|---|---|
| `account:add <username>` | Create an admin account, asking for its email address (unless `--email=` gives it) and password. `--role=` (repeat for more; administrator by default) `--author=` (its profile, which no other account may have), offering to create the profile when it has none, and `--name=` |
| `account:list` | List the accounts with their names, emails, roles, authors, and last sign-in |
| `account:password <username>` | Set an account's password, signing it out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:name <username> ["name"]` | Set the name the admin calls an account by, or remove it |
| `account:email <username> <email>` | Change an account's email address, which every account needs |
| `account:author <username> [slug]` | Link an account to a profile (offering to create it when there's none; refused when another account has it), or unlink it |
| `account:remove <username>` | Delete an account. `--yes` skips the question. |

## Everyday

| Command | What it does |
|---|---|
| `serve` | Run the site at http://127.0.0.1:8000. `--port=8080` and `--host=0.0.0.0` change where. `--static` previews the [static export](going-live.md#static-export) instead. |
| `content:new <type> "<title>"` | Create an entry. `--slug=` sets its URL name; `--draft` makes it a draft. Dated types get a date in the file name. |
| `content:list` | List every entry. `--type=post` and `--status=draft` (or `published`, `scheduled`) narrow it down. |
| `content:preview <type> <name>` | Print a [preview link](admin.md#previewing-drafts) to an entry, even a draft. `--hours=` sets how long it works. |
| `content:lint` | Check front matter, and media details in `user/data/media/`, for problems. `--strict` also reports unknown keys and 1.x names. |
| `routes:list` | Show every URL pattern and redirect, and which one wins when two overlap |

## Publishing and caches

| Command | What it does |
|---|---|
| `publish` | Put content changes live: reindex, refresh, and clear the caches. `--pull` runs `git pull` in `user/` first; `--no-pull` skips it. See [Going live](going-live.md#publishing-changes). |
| `cache:compile` | Precompile config, routes, content types, themes, plugins, and icon packs for speed |
| `cache:clear` | Clear every compiled file and cache. Flags clear just one: `--config`, `--plugins`, `--container`, `--routes`, `--types`, `--themes`, `--icon-packs`, `--store`. `--embeds` also clears the saved oEmbed answers (which nothing else clears) and the cache store, so providers are asked again. |
| `content:index` | Update the content index. `--full` rebuilds it from scratch. (`publish` does this for you.) |
| `schedule:run` | For cron: puts scheduled posts live on time, and prunes the cache and idle admin sessions |
| `build` | Export the site to static files in `storage/export/`. Takes `--base-url=`, `--incremental`, and `--no-crawl`. |
| `media:index` | Update the media index, which the admin's library lists and searches. `--full` rebuilds it; it also warns of metadata files whose media file is gone. (`publish` does this for you.) |
| `media:publish` | Link `user/media` into `public/` so the web server serves it. `--copy` copies instead, for hosts without symlinks. |

## Plugins

| Command | What it does |
|---|---|
| `plugin:list` | List installed [plugins](extending.md#plugins) (name, label, namespace, version, source) and whether each is on, off, or turned on but unable to run, and any that are broken |
| `plugin:new <name>` | Create a plugin named `vendor/name` in `extensions/{vendor}/{name}`: a `plugin.json`, an empty service provider in `src/`, and a starter `lang/en.json`. `--label=` titles it (default: made from the part after `/`), `--namespace=` sets its namespace (default: the name, hyphenated, so `acme/hello` is `acme-hello`), and `--php-namespace=` sets its classes' PHP namespace (default: the name in StudlyCase, so `acme/hello-world` is `Acme\HelloWorld`). Its name and namespace can't be any installed plugin's, theme's, or icon pack's. It's off until you [turn it on](extending.md#turning-extensions-on). |
| `plugin:check [name]` | Check every plugin's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), or one plugin's, by name. One that's off is checked as if it were on. A plugin that's turned on but can't run fails the command; one that's off is a warning, and so is a `version` Composer can't read (such as `1.0-final`), which only `*` matches when another extension requires it, and an [abandoned](extending.md#plugins) plugin, which still runs. |

## Themes

| Command | What it does |
|---|---|
| `theme:list` | List installed themes (name, label, namespace, version, parent, source), which is active, and any that are broken |
| `theme:activate <name>` | Switch themes, by name (`theme:activate acme/notebook`), in `config/theme.php`; clears a theme activated in the admin. Refuses a theme whose [requirements](extending.md#requirements) (or those of a theme it falls back to) aren't met. |
| `theme:new <name>` | Create a theme named `vendor/name` in `extensions/{vendor}/{name}`, with a `theme.json`, a `style.css`, and a starter `lang/en.json`. `--label=` titles it (default: made from the part after `/`), `--namespace=` sets its namespace (default: the name, hyphenated, so `acme/nova` is `acme-nova`), and `--parent=` builds it on another theme, by name. Its name and namespace can't be any installed plugin's, theme's, or icon pack's. Its `theme.json` points editors at the [schema](themes.md#autocomplete-in-your-editor). |
| `theme:check [name]` | Check a theme's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), settings, components, menus and regions, and accessibility basics. The active theme's requirements not being met is an error (the default theme shows in its place); another theme's is a warning. So are a `version` Composer can't read and an abandoned theme. `--strict` shows notices too. |
| `theme:why <view>` | Show which file a template name uses, such as `theme:why single-post` |
| `menu:list` | List your theme's menu locations, the [menu](menus.md) each shows, and any items that can't be shown. `--theme=` lists another theme's, by name. |
| `menu:show <location>` | Show a location's menu as a page sees it, with every URL. `--locale=fr` shows it in another language. |
| `icon:list` | List the icons your theme can use: each one's full name, its label, and the file that draws it. `--theme=` lists another theme's, by name. |
| `icon-pack:check [name]` | Check every icon pack's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), or one pack's, by name, as `plugin:check` checks plugins. One that's off is checked as if it were on. A pack that's turned on but can't load fails the command; one that's off, a broken one, a `version` Composer can't read, and an abandoned pack are warnings. |
| `component:list` | List the components your theme can use: each one's full name, its label, whether it's registered, its class (if it has one), its [variants](components.md#variants), and the file that draws it. Also points out files in `components/` that aren't named for a component. `--theme=` lists another theme's, by name. |
| `theme:publish` | Copy the active theme's files (and its parents') into `public/`. `--all` copies every theme's. |
