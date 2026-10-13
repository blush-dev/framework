# Command line

Blush comes with a command-line tool. Run it from your site's folder:

```sh
bin/blush                   # list every command
bin/blush help content:new  # how to use one command
bin/blush help --global     # the options every command takes
```

To type just `blush` from anywhere inside your site, see
[the launcher script](installation.md#run-blush-from-anywhere-in-your-site-optional).

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

When a command asks you something in a terminal, answer in place: type
text (a dim answer is the default, and Enter takes it), pick from a list
with the arrow keys (type to narrow it), pick several with Space, and
press Enter when you're done. Ctrl+C stops the command. When input isn't
a terminal, questions are asked a line at a time; with `-n`, every
question takes its default, and one without a default stops the command.

Commands that take a while (indexing, checking, and fixing many files,
copying storage, publishing) show what they're doing as they go: a bar
for each step, with how far along it is (`1,203 / 4,009  30%`), or a
spinner until its size is known, and a checked line for each step done
with its count and how long it took (`4,009 · 2.6s`). These are drawn
only in a terminal (with `NO_COLOR` too, just without colors), so piped
output and logs show just the result.

## Setting up

| Command | What it does |
|---|---|
| `init` | Set up a new site: create `.env` (asking for the basics) with an `APP_SECRET` and a `SECRETS_KEY`, create the `storage/` folders, report any Blush can't write to, and offer to create the first admin account, whose email address becomes the site's (`APP_EMAIL`) when `.env` has none. `--webhook` adds a `PUBLISH_SECRET`, which turns on the publish webhook. Safe to run again; it never changes an existing `.env` except to add missing secrets and `APP_EMAIL`. |
| `doctor` | Check that the site is set up to run: PHP and its extensions, `.env`, risky production settings, `public/`, writable storage, and [secrets](extending.md#secrets). In production it also asks the site's own address for `.env`, `config/app.php`, and a file it writes in `storage/`, and fails when any of them is served, which happens when the web server points at the site's folder instead of `public/`. Fails when something needs fixing. It also warns when cron hasn't run [background jobs](going-live.md#background-jobs-and-cron) in the last ten minutes. It also warns of extensions that are on but can't run: an active theme whose [requirements](extending.md#requirements) aren't met (so the default theme shows), and plugins, icon packs, and admin themes that are on but don't run, and a site with accounts but no [owner](accounts.md#owners). |

## Accounts

See [Accounts and roles](accounts.md) and [The admin](admin.md).

| Command | What it does |
|---|---|
| `account:add [<username>]` | Create an admin account. In a terminal it asks for what you leave out: the username, a name, the email address, roles, the profile it writes as, and its password, or a one-time link to send instead (`--link`, which needs no terminal). `--role=` (repeat for more; owner while the site has none, else administrator) `--author=` (its profile, which no other account may have; a slug with no profile makes one, a draft, asking for its public name), `--name=`, and `--link`. `--token-only` makes a [token-only account](accounts.md#token-only-accounts) instead, for an app or agent: no password or profile, and an email address is optional. |
| `account:list` | List the accounts with their names, emails, roles, profiles, and last sign-in, marking token-only ones |
| `account:password <username>` | Set an account's password, signing it out everywhere |
| `account:roles <username> --role=…` | Replace an account's roles |
| `account:name <username> ["name"]` | Set the name the admin calls an account by, or remove it |
| `account:email <username> <email>` | Change an account's email address, which every account needs |
| `account:author <username> [slug]` | Link an account to a profile (making it, a draft, when there's none; refused when another account has it), or unlink it |
| `account:remove <username>` | Delete an account. `--yes` skips the question. |
| `token:add <username>` | Make an [API token](api.md#tokens) for an account and print it once (with `--quiet`, alone on its line). `--name=` says what it's for (asked for when left out), `--expires=` is `30`, `90` (the default), `365`, or `never` days, and `--capabilities=` narrows it, separated by commas (`--capabilities=content.*.edit,content.*.create`). |

## AI apps

| Command | What it does |
|---|---|
| `mcp --account=<username>` | Serve the [MCP server](mcp.md#on-your-own-computer) over standard input and output, as an account, for an AI agent on the same computer. It works whether or not the MCP server is on for the web, needs no token, and refuses a suspended account. |

## Tools

| Command | What it does |
|---|---|
| `tool` | List the [tools](extending.md#tools) you can run: Blush's own (reading and changing entries and media, publishing, reindexing, clearing caches) and your plugins'. |
| `tool <key> [name=value …]` | Run a tool, such as `tool core/reindex`. Give its input as `name=value`, read by what the tool takes: numbers, `true` or `false`, a list by naming it again (`type=post type=page`, or as JSON), and an object as JSON. Run without input, a tool that needs some says what it takes. It runs as the command line, with every permission; `--account=` runs it as an account instead, with its permissions. `--json` writes only what it answers, as JSON. A tool that runs in the background from the admin runs straight away here. |

## Everyday

| Command | What it does |
|---|---|
| `serve` | Run the site at http://127.0.0.1:8000. `--port=8080` and `--host=0.0.0.0` change where. |
| `content:new [<type>] ["<title>"]` | Create an entry. In a terminal it asks for what you leave out: the type, the title, the slug, the parent page (for pages), and whether it's published or a draft. `--slug=` sets its URL name; `--draft` makes it a draft; `--parent=` puts a page under another, by its path (`--parent=about`). It gets today's publish date, and a file name by its type's [pattern](content-types.md#naming-new-files), and ends with where to edit it in the admin. It's listed under [Tools → Activity](mcp.md#activity-and-undo) as made on the command line; `--account=` makes it as an account instead, with its permissions and its byline (`--account=jane`). |
| `content:list` | List every entry. `--type=post` and `--status=draft` (or `published`, `scheduled`) narrow it down. |
| `content:preview <type> <name>` | Print a [preview link](admin.md#previewing-drafts) to an entry, even a draft. `--hours=` sets how long it works. |
| `content:lint` | Check front matter, and media details in `user/data/media/`, for problems, and list files in `user/content/` that aren't `.md`, so aren't read. It reports terms and profiles entries name with no file as errors (`content:terms` writes them). `--strict` also reports unknown keys and 1.x names. |
| `content:filenames` | List entries whose files aren't named by their type's own [pattern](content-types.md#naming-new-files) (types that set `filename`), and what they'd be renamed to. `--write` renames them; `--type=<type>` limits it to one. No address changes. |
| `content:folders` | List collection entries not in the folders their [collection](content-types.md#collections-are-flat) keeps them in (entries kept as folders, and files outside its [folder pattern's](content-types.md#folders-for-many-files) folders), and where they belong. `--write` moves them and removes folders left empty. No address changes. |
| `content:terms` | List the terms and profiles your entries name that have no file, which the site leaves out. `--write` writes each, published, titled as the entries name it. |
| `content:type-folders` | List types in `user/data/types/` that still name their own folder (`folder`, or 1.x's `path`), and fail while any are left. `--write` [moves](content-types.md#moving-a-type-into-its-folder) each type's files into `_` and its name, and writes it without the folder, with the prefix its URLs came from. |
| `content:taxonomies` | List types in `user/data/types/` still written as taxonomies, which Blush no longer has, and fail while any are left. `--write` [migrates](content-types.md#moving-from-taxonomies) each to a collection and a classify relation in `user/data/relations/`. |
| `content:relation <name>` | Say how many entries have values in a relation. `--key=<key>` gives it a new front matter key, keeping the old one as an alias unless `--rewrite` moves the values; `--remove` deletes its file in `user/data/relations`; `--strip` removes its values, with their ids, from entries' files. |
| `content:media-ids` | List the media fields still written as a path, such as `image: /media/2026/10/photo.jpg`. `--write` rewrites each to its [file's id](content-types.md#custom-fields) (a path to an image's size becomes the image's id); a path that names no file in the library is left and listed. Give files ids first with `media:ids`. |
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
| `cache:compile` | Precompile config, routes, content types, themes, plugins, icon packs, and admin themes for speed |
| `cache:clear` | Clear every compiled file and cache. Flags clear just one: `--config`, `--plugins`, `--container`, `--routes`, `--types`, `--themes`, `--icon-packs`, `--store`. `--embeds` also clears the saved oEmbed answers (which nothing else clears) and the cache store, so providers are asked again. |
| `content:index` | Update the content index. `--full` rebuilds it from scratch. (`publish` does this for you.) |
| `media:dates` | Say how many media files have no [upload date](media.md#upload-dates), which orders the library, or one not in UTC (`-v` lists each, with the date it would be given). `--write` gives each its likely date, from its dated folder and its file, and writes the others in UTC. |
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

## Storage

A site keeps its data as files by default. These commands move it into a
SQLite database (`storage/database/site.sqlite`); see [Large sites](going-live.md#large-sites-sqlite).

| Command | What it does |
|---|---|
| `storage:copy` | Copy the site's content (with the links between entries), data, accounts, roles, secrets (still encrypted), and jobs from files into SQLite, ids kept, each entry's front matter as written. Sessions aren't copied, so everyone signs in again. Content files without an id aren't copied (run `content:ids --write` first). It refuses a database that already holds records; `--replace` replaces them. `--from=` and `--to=` name the drivers (`filesystem` and `sqlite`, the only way for now). The site keeps using files until `STORAGE_DRIVER=sqlite` is set. A large site may need more memory: `php -d memory_limit=1G bin/blush storage:copy`. |
| `storage:sync` | Make or update the tables the database keeps, with their columns and indexes, and refresh what queries plan by. Tables are made when first used, so this is for deploys. On files it does nothing. |

## Secrets

[Secrets](extending.md#secrets) are credentials Blush and plugins use to
reach other services. None of these commands shows a value.

| Command | What it does |
|---|---|
| `secrets:list` | List the secrets core and plugins declare: where each is set (`environment`, `stored`, `missing`, or `unreadable`), the environment variable that sets it, and when it was stored. Stored secrets nothing declares any more are listed too. |
| `secrets:set <name>` | Store a secret's value, encrypted with `SECRETS_KEY`, asked for without showing it: `secrets:set acme/analytics.ga_refresh_token`. A secret set in the environment can't be stored over. |
| `secrets:remove <name>` | Remove a secret's stored value, declared or left behind by a removed plugin. One set in the environment stays set. |
| `secrets:rotate` | Encrypt every stored secret again under the current `SECRETS_KEY`. To change the key: put the old one in `SECRETS_KEY_PREVIOUS`, a new one in `SECRETS_KEY` (`php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`), run this, then remove `SECRETS_KEY_PREVIOUS`. |

## Plugins

| Command | What it does |
|---|---|
| `plugin:list` | List installed [plugins](extending.md#plugins) (name, label, namespace, version, source) and whether each is on, off, or turned on but unable to run, and any that are broken |
| `plugin:new <name>` | Create a plugin named `vendor/name` in `extensions/{vendor}/{name}`: a `plugin.json`, an empty service provider in `src/`, and a starter `lang/en.json`. `--label=` titles it (default: made from the part after `/`), `--namespace=` sets its namespace (default: the name, hyphenated, so `acme/hello` is `acme-hello`), and `--php-namespace=` sets its classes' PHP namespace (default: the name in StudlyCase, so `acme/hello-world` is `Acme\HelloWorld`). Its name and namespace can't be any installed plugin's, theme's, or icon pack's. It's off until you [turn it on](extending.md#turning-extensions-on). |
| `plugin:check [name]` | Check every plugin's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), or one plugin's, by name. One that's off is checked as if it were on. A plugin that's turned on but can't run fails the command; one that's off is a warning, and so is a `version` Composer can't read (such as `1.0-final`), which only `*` matches when another extension requires it, and an [abandoned](extending.md#plugins) plugin, which still runs, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match its file or the plugin, and text in its code that `lang/en.json` reads otherwise, since the catalog's wins (see `lang:extract`). A catalog isn't required: text the code writes shows without one. |

## Themes

| Command | What it does |
|---|---|
| `theme:list` | List installed themes (name, label, namespace, version, parent, source), which is active, and any that are broken |
| `theme:activate <name>` | Switch themes, by name (`theme:activate acme/notebook`), in `config/theme.php`; clears a theme activated in the admin. Refuses a theme whose [requirements](extending.md#requirements) (or those of a theme it falls back to) aren't met. |
| `theme:new [<name>]` | Create a theme named `vendor/name` in `extensions/{vendor}/{name}`, with a `theme.json`, a `style.css` (not for a child theme, which uses its parent's), and a starter `lang/en.json`. In a terminal it asks for what you don't pass: its name, label, parent, description, and whether to activate it. `--label=` titles it (default: made from the part after `/`), `--namespace=` sets its namespace (default: the name, hyphenated, so `acme/nova` is `acme-nova`; asked for when that one is taken), `--parent=` builds it on another theme, by name, `--description=` says what it's for, and `--activate` makes it the active theme. Its name and namespace can't be any installed plugin's, theme's, or icon pack's. Its `theme.json` points editors at the [schema](themes.md#autocomplete-in-your-editor). |
| `theme:check [name]` | Check a theme's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), settings, blocks and components, menus, and accessibility basics. The active theme's requirements not being met is an error (the default theme shows in its place); another theme's is a warning. So are a `version` Composer can't read, a base layout that prints a tag `head()` prints (the charset, `viewport`, `generator`, or `<title>`), an abandoned theme, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match, and text in its code that `lang/en.json` reads otherwise (the catalog's wins), or that can't be collected (see `lang:extract`). A catalog isn't required: text the code writes shows without one. `--strict` shows notices too, such as a catalog without `@@locale` and `@@domain`. |
| `theme:why <view>` | Show which file a template name uses, such as `theme:why single-post` |
| `menu:list` | List your theme's menu locations, the [menu](menus.md) assigned to each (or the theme's default), where each menu is kept, and any problems: items that can't be shown, or a location assigned a menu you don't have. `--theme=` lists another theme's, by name. |
| `menu:show <location>` | Show what a location shows as a page sees it, with every URL. `--locale=fr` shows it in another language. |
| `menu:assign <location> [menu]` | Assign a menu to one of your theme's locations, by the menu's name (`menu:assign primary main`), or with `--clear` take it away, so the location shows the theme's default. Each theme keeps its own; `--theme=` assigns for another theme, by name. |
| `menu:refs` | List the menus with `entry` and `term` links not filed with the entry's id, or naming an entry that was renamed. `--write` files them. Fails when any are listed. |
| `icon:list` | List the icons your theme can use: each one's full name, its label, and the file that draws it. `--theme=` lists another theme's, by name. |
| `admin-theme:check [name]` | Check every [admin theme](admin-themes.md#checking-a-theme)'s manifest, `tokens.json`, [requirements](extending.md#requirements), and contrast, or one theme's, by name. One that's off is checked as if it were on. A theme that's turned on but can't be offered fails the command; one that's off, a broken one (a `tokens.json` that sets something it can't included), a `version` Composer can't read, an abandoned theme, text that's hard to read in either color scheme, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match are warnings. |
| `icon-pack:check [name]` | Check every icon pack's manifest, [requirements](extending.md#requirements), [conflicts](extending.md#conflicts), and [replaces](extending.md#replacing-another-extension), or one pack's, by name, as `plugin:check` checks plugins. One that's off is checked as if it were on. A pack that's turned on but can't load fails the command; one that's off, a broken one, a `version` Composer can't read, an abandoned pack, and a `lang/` catalog whose `@@locale` or `@@domain` doesn't match are warnings. |
| `lang:extract <name> [--locale] [--dry-run]` | Write the text a theme's, plugin's, or icon pack's code and manifest write (`t()` calls with text beside the key, and the manifest's [label, description, menu locations, and settings](themes.md#your-manifests-text) under `manifest.` keys) to its `lang/en.json` (or `--locale`'s), for translators: new keys are added, text changed in the code is replaced (translators should recheck it), each `context:` is written beside its message, and everything else is kept. `core` does Blush's own. Warns about a key or text that isn't plain text, a `key:` or `text:` parameter beside the key and text (which PHP refuses), one key with two texts, and text that isn't a valid ICU message. See [Translations](themes.md#translations). |
| `block:list` | List the [blocks](blocks.md) your content can use: each one's full name, its label, its class (if it has one), its [variants](blocks.md#variants) under your theme, and the file that draws it. Also points out files in `blocks/` that aren't for a registered block. `--theme=` lists for another theme, by name. |
| `component:list` | List the [components](components.md) your theme can use: each one's full name, its class (if it has one), and the file that draws it. Also points out files in `components/` that aren't named for a component. `--theme=` lists another theme's, by name. |
| `theme:publish` | Copy the active theme's files (and its parents') into `public/`. `--all` copies every theme's. |
