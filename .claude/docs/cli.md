# CLI

An in-house console framework (D-012). There is no symfony/console dependency.
The binary is `bin/blush` for now and will follow the final product name.

## Console framework (`Blush\Console`)

Implemented in M2 (D-065, D-069).

- **Commands:** invokable classes with an attribute:
  `#[Command(name: 'cache:clear', description: '…')]`. The constructor takes
  services. Input is declared as `__invoke()` parameters marked
  `#[Argument]` or `#[Option(short: 'f')]`, so parsing, validation, and help
  text come from their types and defaults. Supported types are string,
  int, float, bool flags, backed enums, nullable types, a variadic argument,
  and repeatable array options. Other parameters (`Output`, `Prompt`,
  services) are resolved from the container. `__invoke()` returns
  `ExitCode`.

  ```php
  #[Command('cache:clear', 'Clear the compiled caches.')]
  final readonly class CacheClear
  {
  	public function __construct(private Bootstrap $bootstrap) {}

  	public function __invoke(
  		Output $output,
  		#[Option('Clear the compiled config.')] bool $config = false
  	): ExitCode {
  		// ...
  	}
  }
  ```
- **Discovery:** extension and site commands are tagged with
  `CommandRegistry::TAG` in a provider's `TAGS` constant. Built-ins come from
  the `BuiltInCommand` enum through `CommandRegistrar` and never overwrite a
  tagged command. Commands are resolved through the container only when
  run.
- **Input:** an argv parser (long and short options, `--opt=value`, flags,
  variadic arguments, `--` terminator).
- **Output:** styled writer (ANSI with automatic detection, `NO_COLOR`),
  verbosity levels, tables, and progress bars (`Output::progress()`,
  drawn only on an ANSI terminal, D-091). Prompts (confirm, ask, choice,
  secret) have a non-interactive fallback.
- **Exit codes:** an enum (`Success`, `Failure`, `Invalid`, …).
- **Boots the same `Application`** as the web, so commands get the container,
  config, content, and `Kernel::handle()`.
- **Testing:** a `CommandTester` that captures output and exit codes.

## Built-in commands (initial list)

| Command | Purpose |
|---|---|
| `list` | List the commands (the default) |
| `help <command>` | Show a command's usage |
| `serve [--host] [-p\|--port]` | Dev server (`php -S` + `resources/server.php`; `--static` removed with static export, D-476) |
| `cache:clear [--config\|--plugins\|--container\|--routes\|--types\|--themes\|--icon-packs\|--store\|--embeds]` | Clear compiled caches and the cache store, bumping the content version (no flags: all; `--store`: only the store, D-128; `--embeds`: the oEmbed answers and the store, D-448) |
| `cache:compile` | Compile config, plugins, themes, icon packs, routes, content types, and container plans, then clear the cache store and bump the content version (D-060, D-066, D-077, D-092, D-115, D-128) |
| `content:index [--full]` | Build or refresh the content index, with a progress bar; `-v` lists changes (M4b, D-087) |
| `content:filenames [--write] [--type=<type>]` | List entries named by another pattern than their type's `filename`, and rename them to it with `--write`: files only, with translations linked by name; a list alone doesn't fail (D-511, D-512, D-514); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:folders [--write]` | List collections' and profiles' entries not in the folders their type keeps them in (folder entries, files outside its folder pattern's folders, or in one for another date) and where they belong; `--write` moves them and removes emptied folders; fails while any are left (D-514, D-629); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:terms [--write]` | List the terms and profiles entries name with no file (lint errors, left out of the site); `--write` writes each, published, titled as entries first wrote it; fails while any are left (D-584); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:taxonomies [--write]` | List the data types in `user/data/types` still written as taxonomies; `--write` rewrites each as a collection (edited in place) and its classify relation (`user/data/relations/{name}.json`); fails while any are left (D-591, D-594) |
| `content:relation <name> [--key=<key> [--rewrite]] [--remove] [--strip]` | A relation's use, a new key (the old kept as an alias unless `--rewrite`), removing its data file, and stripping its values, through `RelationChanges` (D-600) |
| `content:refs [--write]` | List content files whose relations aren't filed in both forms (no `refs`, an id written as a value, a slug a target no longer has); `--write` files them through `ContentWriter::fileRefs()`; fails while any are left (D-596); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:ids [--write] [--keep=<path>…]` | List content files missing a valid id and ids files share; `--write` gives missing ones new ids, `--keep` keeps a shared id on a file and renews the others (D-477, D-480); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:lint [--strict]` | Validate content against schemas: errors, and warnings for two files claiming one entry and dates not on the calendar (D-449); terms and profiles entries name with no file are errors (D-584); `--strict` adds notices for undeclared keys and 1.x aliases (D-081, D-084, D-091). Also reports files in `user/content` in the formats Blush no longer reads (`.markdown`, `.html`, `.json`, `.yaml`, `.yml`; `FormatCheck`, D-501). Also checks media metadata files in `user/data/media`: unreadable, values that don't fit, hidden by another format, or describing a file that's gone (D-293) |
| `content:new <type> "<title>" [--slug] [--draft]` | Scaffold a Markdown entry (`Y-m-d.slug.md` for dated types) and refresh the index (D-091) |
| `content:list [--type] [--status]` | List every indexed entry (M4b) |
| `content:preview <type> <name> [--hours]` | Print a signed preview link to an entry, whatever its status (D-226) |
| `routes:list` | Show the routes, redirects, and shadowed routes (M3, D-077) |
| `media:ids [--write] [--keep=<path>…]` | `content:ids` for media files: originals missing a valid id (counted; `-v` lists them) and shared ids; `--write` writes new ids into `user/data/media`, `--keep` renews the others; sizes of another image need none (D-487) |
| `media:sizes [--write]` | Images whose metadata files don't list their sizes as the index has them (counts; `-v` lists), and lists naming files that aren't their sizes; `--write` writes each image's `sizes` whole (D-488) |
| `media:index [--full]` | Build or refresh the media index, with a progress bar; `-v` lists changes, and metadata files with no media file are warnings (D-288) |
| `media:publish [--copy]` | Link `user/media` into `public/` at the media URL, or copy the allowed files (M4c, D-099); writes the served folder's `.htaccess` (D-499) |
| `theme:list` | List installed themes (framework, Composer, local), the active one, and broken manifests (M5b, D-120) |
| `theme:activate <name>` | Set the active theme (by its `vendor/name`, D-378) in `config/theme.php` (created, or its plain `active` value edited) and clear the config and theme caches (D-120), and a theme the admin saved in the `theme` settings group (D-381, D-673); refuses a theme whose chain's `require` isn't met (D-431) |
| `theme:new <vendor/name> [--parent] [--label] [--namespace]` | Create a minimal theme (manifest, stylesheet unless it has a parent (D-618), and a starter `lang/en.json` with `@@locale` and `@@domain`, D-452) in `extensions/{vendor}/{name}` (D-418, D-120, D-378); the namespace defaults to the name, hyphenated (`acme-nova`, D-424), the name and namespace must be free across every installed extension (D-417), and the manifest has a `$schema` key (D-206) |
| `theme:publish [--all]` | Copy servable theme assets to `public/themes/{vendor}/{name}`, removing stale ones; the active chain, or every theme (D-119) |
| `theme:check [name] [--strict]` | Check the chain, manifests, `require`, `conflict` (D-435), and `replace` (D-436) as if active (an error for the active theme, which falls back; a warning for another, D-431), a `version` Composer can't read and an `abandoned` theme (warnings, D-433), provider, settings, components without a template (D-164), component files not named for a component, registered components without a label (notice; D-173), the base layout's landmarks and skip link (D-030, D-121, D-160), or a notice in place of the header and footer warnings when the theme has no base layout of its own (D-632), and each chain theme's `lang/` catalogs against their `@@locale` and `@@domain` (`Translation\CatalogCheck`: unreadable an error, a mismatch a warning, none a notice; D-454) |
| `theme:why <view> [--theme]` | Show which file in the view chain wins for a view, and what it shadows (D-120) |
| `icon:list [--theme]` | List every icon the chain can show: full name, label, and winning file (D-187) |
| `icon-pack:check [name]` | Check every icon pack's manifest, `require`, `conflict`, and `replace` (D-435, D-436; one that's off as if it were on), and `version`, as `plugin:check` does: a pack that's on but can't load is an error and fails it; one that's off, a broken one, a `version` Composer can't read, an `abandoned` pack, and a mismatched `lang/` catalog are warnings (D-431, D-433, D-454) |
| `menu:list [--theme]` | List the chain's menu locations, the site menu assigned to each or the theme's default (D-676), resolved item counts, and where each is kept; site menus no location shows; problems (D-204) |
| `menu:show <location> [--theme] [--locale]` | Print what a location shows resolved as a page sees it (labels and URLs, nested), with its problems; fails when it shows none (D-204) |
| `menu:assign <location> [menu] [--clear] [--theme]` | Assign a site menu to a theme location, or clear it, in the theme's settings group (D-676) |
| `menu:refs [--write]` | List menus whose `entry`/`term` links lack the target's id in `ref` or name a renamed entry; `--write` files them (D-676) |
| `directive:list [--theme]` | List every registered directive (D-532): full name, label, class, variants under the chain (D-266), and winning template; warn when one can't render and about files in `directives/` for no registered directive |
| `component:list [--theme]` | List every component the chain can render (D-532): full name, class, and winning template, leaving out other themes'; warn when one can't render (D-164) and about files not named for a component (D-173) |
| `lang:missing [--locale]` | List untranslated message keys |
| `publish [--pull\|--no-pull]` | Pull `user/` (with `PublishConfig::$git`), recompile the content types and routes, reindex, clear the store, and bump the content version, as the webhook does (D-131) |
| `plugin:list` | List installed plugins (name, label, namespace, version, source, and on, off, or can't run, D-385), and broken manifests as warnings (D-394) |
| `plugin:check [name]` | Check every plugin's manifest, `require`, `conflict`, and `replace` (D-435, D-436; or one plugin's), one that's off as if it were on; a plugin turned on that can't run, or a broken one config turns on, is an error and fails it, one that's off a warning (D-394), and so are a `version` Composer can't normalize (D-430) an `abandoned` plugin, which still runs (D-433), and a mismatched `lang/` catalog (D-454; an unreadable one is an error) |
| `plugin:new <vendor/name> [--label] [--namespace] [--php-namespace]` | Create a plugin (manifest, an empty service provider in `src/`, autoloaded PSR-4, and a starter `lang/en.json`, D-452) in `extensions/{vendor}/{name}`, off until turned on (D-416, D-418); the namespace defaults to the name, hyphenated (`acme-hello`, D-424), the name and namespace must be free across every installed extension, the PHP namespace defaults to the name in StudlyCase, and the manifest has a `$schema` key (D-206) |
| `schedule:run [--budget]` | The cron entry, every minute: queue the scheduled tasks that are due, then work the queue for `JobConfig::$budget` seconds, recording cron's time; fails when a job failed for good (D-040, D-133, D-621, D-622) |
| `schedule:list` | The scheduled tasks (job, label, frequency in words, last and next run) and each runner's last run (D-622) |
| `jobs:work [--sleep] [--max-time] [--stop-when-empty]` | A long-running worker: tick and work the queue, resting when idle (D-621, D-622) |
| `jobs:list [--status]` | Jobs, newest first: id, label, status, queued, by whom, last message or error (D-622) |
| `jobs:retry [id] [--all]` | Queue a failed job, or every failed job, again (D-622) |
| `jobs:prune` | Remove finished jobs past `keepDone` and `keepFailed` (D-622) |
| `bench` | Run the performance suite (dev only, D-044). For now it's `composer bench` in the framework (D-101) |
| `init [--webhook]` | Create `.env` from `.env.example` (asking for name, URL, timezone, and environment in a terminal), optionally add a `PUBLISH_SECRET`, create the storage folders, and report unwritable ones; idempotent (D-218) |
| `account:add <username> [--email] [--role]... [--author] [--name]` | Create an account, asking for the email when `--email` is left out and twice for the password (needs a terminal); owner while the site has none, else administrator (D-219, D-322, D-370, D-500) |
| `account:list` | Accounts with name, email, roles (unknown ones flagged), author, status, and last sign-in (D-219, D-312, D-322, D-370) |
| `account:password <username>` | Set a password, which signs the account's sessions out (D-219) |
| `account:roles <username> --role...` | Replace an account's roles (D-219) |
| `account:name <username> [name]` | Name an account, or remove its name (D-322) |
| `account:email <username> <email>` | Change an account's email address, which every account needs (D-370) |
| `account:author <username> [slug]` | Link to an author, or unlink; offers to write the author's entry when there's none (D-219, D-259) |
| `account:suspend <username>` | Suspend an account: signed out, and no sign-in or password link until reinstated (D-312) |
| `account:reinstate <username>` | Reinstate a suspended account (D-312) |
| `account:remove <username> [--yes]` | Delete an account after confirming (D-219) |
| `doctor` | Run every `SetupChecks` check (PHP, extensions, `.env`, production risks, `public/`, storage) with hints; fails on any failure. Also warns of extensions that are on but can't run (an active theme falling back, plugins, icon packs; D-431), and of a site with accounts but no owner (D-500). No opcache check, since the CLI's PHP isn't the web server's (D-218) |
| `generate:{provider,component,controller,command,type}` | Scaffolding (not `make:`, D-008) |
| `new <dir>` | Create a new site from the skeleton (may live in a global installer) |
