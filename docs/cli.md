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
| `doctor` | Check that the site is set up to run: PHP and its extensions, `.env`, risky production settings, `public/`, and writable storage. Fails when something needs fixing. It also warns when cron hasn't run [background jobs](going-live.md#background-jobs-and-cron) in the last ten minutes. It also warns of extensions that are on but can't run: an active theme whose [requirements](extending.md#requirements) aren't met (so the default theme shows), and plugins and icon packs that are on but don't run, and a site with accounts but no [owner](accounts.md#owners). |

## Accounts

See [Accounts and roles](accounts.md) and [The admin](admin.md).

| Command | What it does |
|---|---|
| `account:add <username>` | Create an admin account, asking for its email address (unless `--email=` gives it) and password. `--role=` (repeat for more; owner while the site has none, else administrator) `--author=` (its profile, which no other account may have), offering to create the profile when it has none, and `--name=` |
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
| `serve` | Run the site at http://127.0.0.1:8000. `--port=8080` and `--host=0.0.0.0` change where. |
| `content:new <type> "<title>"` | Create an entry. `--slug=` sets its URL name; `--draft` makes it a draft. It gets today's publish date, and a file name by its type's [pattern](content-types.md#naming-new-files). |
| `content:list` | List every entry. `--type=post` and `--status=draft` (or `published`, `scheduled`) narrow it down. |
| `content:preview <type> <name>` | Print a [preview link](admin.md#previewing-drafts) to an entry, even a draft. `--hours=` sets how long it works. |
| `content:lint` | Check front matter, and media details in `user/data/media/`, for problems, and list files in `user/content/` that aren't `.md`, so aren't read. It reports terms and profiles entries name with no file as errors (`content:terms` writes them). `--strict` also reports unknown keys and 1.x names. |
| `content:filenames` | List entries whose files aren't named by their type's own [pattern](content-types.md#naming-new-files) (types that set `filename`), and what they'd be renamed to. `--write` renames them; `--type=<type>` limits it to one. No address changes. |
| `content:folders` | List collection entries not in the folders their [collection](content-types.md#collections-are-flat) keeps them in (entries kept as folders, and files outside its [folder pattern's](content-types.md#folders-for-many-files) folders), and where they belong. `--write` moves them and removes folders left empty. No address changes. |
| `content:terms` | List the terms and profiles your entries name that have no file, which the site leaves out. `--write` writes each, published, titled as the entries name it. |
| `content:taxonomies` | List types in `user/data/types/` still written as taxonomies, which Blush no longer has, and fail while any are left. `--write` [migrates](content-types.md#moving-from-taxonomies) each to a collection and a classify relation in `user/data/relations/`. |
| `content:relation <name>` | Say how many entries have values in a relation. `--key=<key>` gives it a new front matter key, keeping the old one as an alias unless `--rewrite` moves the values; `--remove` deletes its file in `user/data/relations`; `--strip` removes its values, with their ids, from entries' files. |
| `content:refs` | List content files whose [links to other entries](content.md#links-between-entries) aren't filed with their ids under `refs`. `--write` files them, writing each value as the slug its entry has now. |
| `content:parents` | List the folders of pages that have no page of their own, which leaves the pages in them at the top of the tree. `--write` writes each as a draft, titled by its folder. |
| `content:ids` | List content files missing an [id](content.md#ids), and ids files share. `--write` gives each file missing one a new id; `--keep=<path>` keeps a shared id on that file and gives the others new ones (repeat it for more). |
| `routes:list` | Show every URL pattern and redirect, and which one wins when two overlap |

`content:filenames`, `content:folders`, `content:terms`, `content:parents`,
`content:refs`, and `content:ids` fix things only content files have, so they're there
while your content is kept as files, as it is by default.

## Publishing and caches

| Command | What it does |
|---|---|
| `publish` | Put content changes live: reindex, refresh, and clear the caches. `--pull` runs `git pull` in `user/` first; `--no-pull` skips it. See [Going live](going-live.md#publishing-changes). |
| `cache:compile` | Precompile config, routes, content types, themes, plugins, and icon packs for speed |
| `cache:clear` | Clear every compiled file and cache. Flags clear just one: `--config`, `--plugins`, `--container`, `--routes`, `--types`, `--themes`, `--icon-packs`, `--store`. `--embeds` also clears the saved oEmbed answers (which nothing else clears) and the cache store, so providers are asked again. |
| `content:index` | Update the content index. `--full` rebuilds it from scratch. (`publish` does this for you.) |
| `media:ids` | Say how many media files are missing an [id](media.md#ids-and-image-sizes), and which ids files share (`-v` lists each file). `--write` gives each file missing one a new id; `--keep=<path>` (in the media folder) keeps a shared id on that file and gives the others new ones. An image's other sizes don't need one. |
| `media:sizes` | Say how many [image sizes](media.md#ids-and-image-sizes) aren't listed in their images' details yet (`-v` lists them). `--write` lists them, and takes out listed files that are gone. |
| `media:index` | Update the media index, which the admin's library lists and searches. `--full` rebuilds it; it also warns of metadata files whose media file is gone. (`publish` does this for you.) |
| `media:publish` | Link `user/media` into `public/` so the web server serves it. `--copy` copies instead, for hosts without symlinks. Writes an `.htaccess` there so no script runs and SVGs are sandboxed. |

## Background jobs

See [Going live](going-live.md#background-jobs-and-cron) for setting up cron.

| Command | What it does |
|---|---|
| `schedule:run` | For cron, every minute: queue the scheduled tasks that are due (putting scheduled posts live, pruning the cache, idle admin sessions, and old jobs), then run queued jobs for up to 50 seconds. `--budget=` changes the seconds. Fails when a job failed for good. `-v` lists each job it ran. |
| `schedule:list` | List the scheduled tasks, how often each runs, and when it last ran and runs next; then when cron, a worker, page visits, and the admin last ran jobs |
| `jobs:work` | Keep running jobs and scheduled tasks until stopped, for a server that keeps a process going (then cron isn't needed). `--sleep=` sets the seconds to rest when there's nothing to do (3), `--max-time=` stops after that many seconds, and `--stop-when-empty` stops once the queue is empty. Restart it after updating code. |
| `jobs:list` | List the background jobs, newest first, with their ids, status, who queued them, and what they last said. `--status=` shows one: `queued`, `running`, `done`, or `failed`. |
| `jobs:retry <id>` | Queue a failed job again. `--all` queues every failed job. |
| `jobs:prune` | Remove finished jobs kept past their time (a day for done, a week for failed). A scheduled task does this daily. |

## Plugins

| Command | What it does |
|---|---|
| `plugin:list` | List installed [plugins](extending.md#plugins) (name, label, namespace, version, source) and whether each is on, off, or turned on but unable to run, and any that are broken |
| `plugin:new <name>` | Create a plugin named `vendor/name` in `extensions/{vendor}/{name}`: a `plugin.json`, an empty service provider in `src/`, and a starter `lang/en.json`. `--label=` titles it (default: made from the part after `/`), `--namespace=` sets its namespace (default: the name, hyphenated, so `acme/hello` is `acme-hello`), and `--php-namespace=` sets its classes' PHP namespace (default: the name in StudlyCase, so `acme/hello-world` is `Acme\HelloWorld`). Its name and namespace can't be any installed plugin's, theme's, or icon pack's. It's off until you [turn it on](extending.md#turning-extensions-on). |
| `plugin:check [name]` | Check every plugin's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), or one plugin's, by name. One that's off is checked as if it were on. A plugin that's turned on but can't run fails the command; one that's off is a warning, and so is a `version` Composer can't read (such as `1.0-final`), which only `*` matches when another extension requires it, and an [abandoned](extending.md#plugins) plugin, which still runs, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match its file or the plugin. |

## Themes

| Command | What it does |
|---|---|
| `theme:list` | List installed themes (name, label, namespace, version, parent, source), which is active, and any that are broken |
| `theme:activate <name>` | Switch themes, by name (`theme:activate acme/notebook`), in `config/theme.php`; clears a theme activated in the admin. Refuses a theme whose [requirements](extending.md#requirements) (or those of a theme it falls back to) aren't met. |
| `theme:new <name>` | Create a theme named `vendor/name` in `extensions/{vendor}/{name}`, with a `theme.json`, a `style.css` (not for a child theme, which uses its parent's), and a starter `lang/en.json`. `--label=` titles it (default: made from the part after `/`), `--namespace=` sets its namespace (default: the name, hyphenated, so `acme/nova` is `acme-nova`), and `--parent=` builds it on another theme, by name. Its name and namespace can't be any installed plugin's, theme's, or icon pack's. Its `theme.json` points editors at the [schema](themes.md#autocomplete-in-your-editor). |
| `theme:check [name]` | Check a theme's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), settings, directives and components, menus and regions, and accessibility basics. The active theme's requirements not being met is an error (the default theme shows in its place); another theme's is a warning. So are a `version` Composer can't read, a base layout that prints a tag `head()` prints (the charset, `viewport`, `generator`, or `<title>`), an abandoned theme, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match. `--strict` shows notices too, such as a catalog without them. |
| `theme:why <view>` | Show which file a template name uses, such as `theme:why single-post` |
| `menu:list` | List your theme's menu locations, the [menu](menus.md) each shows, and any items that can't be shown. `--theme=` lists another theme's, by name. |
| `menu:show <location>` | Show a location's menu as a page sees it, with every URL. `--locale=fr` shows it in another language. |
| `icon:list` | List the icons your theme can use: each one's full name, its label, and the file that draws it. `--theme=` lists another theme's, by name. |
| `icon-pack:check [name]` | Check every icon pack's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), or one pack's, by name, as `plugin:check` checks plugins. One that's off is checked as if it were on. A pack that's turned on but can't load fails the command; one that's off, a broken one, a `version` Composer can't read, an abandoned pack, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match are warnings. |
| `directive:list` | List the [directives](directives.md) your content can use: each one's full name, its label, its class (if it has one), its [variants](directives.md#variants) under your theme, and the file that draws it. Also points out files in `directives/` that aren't for a registered directive. `--theme=` lists for another theme, by name. |
| `component:list` | List the [components](components.md) your theme can use: each one's full name, its class (if it has one), and the file that draws it. Also points out files in `components/` that aren't named for a component. `--theme=` lists another theme's, by name. |
| `theme:publish` | Copy the active theme's files (and its parents') into `public/`. `--all` copies every theme's. |
