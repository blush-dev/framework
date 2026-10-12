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
  drawn on a terminal, in color only with ANSI, D-091, D-722: a spinner,
  the label, a thin `━╸─` line 20 to 40 wide, and counts with
  separators; a step's done line has its count and time), named and with a spinner
  until the total is known, and steps (`Output::steps()`,
  `ProgressSteps`): a long-running service takes
  `?Closure(string $step, int $done, int $total)`, wrapped in
  `Support\Progress`, and the command passes `$steps(...)`; each step
  done leaves a `✓` line (D-710). Prompts (confirm, ask, select,
  multiselect, secret) have a non-interactive fallback; on a terminal
  they're drawn in place (D-719): `KeyReader` reads keys in raw mode
  (tests feed it a stream), `Frame` redraws, `PromptState` holds where
  a prompt is, each leaves one `✓ Question  answer` line, and Ctrl+C
  throws `PromptCancelled` (exit 130). Off a terminal, a line at a time.
- **Shared pieces (D-717):** commands say what they show, and `Output`
  says how it looks: `section()` (bold, a blank line before),
  `status()` (`StatusLine` rows: an `Outcome` mark, aligned labels,
  details, dim hints), `definitions()` (aligned key and value),
  `table()` (bold header, dim rule, no borders), `summary()` (by
  `Outcome`, parts joined with `·`; a failure on the error stream),
  `next()` and `error($text, $fix)` (with `` `code` `` spans in the
  accent), `code()`, `join()`, and `Output::count()`. Colors come from
  `Tone` (code, heading, muted, pass, warning, failure), drawn from a
  `Palette`: the terminal's own 16 colors, or x3p0.com's for a dark or
  light background when `COLORTERM` says it shows exact colors and
  `COLORFGBG` or an OSC 11 query says what its background is (D-718). With a known
  `$width` (a terminal: `COLUMNS`, else `stty size`, else 80), text
  wraps and outcomes are symbols; piped, nothing wraps and outcomes are
  words. `OutputState` tracks the last lines written, so blank lines
  never double. Built for `list`, `help` (`help --global` shows the
  global options, left out of each command's help), and `doctor`,
  then every command (stage 2): `success()`, `warning()`, and `error()`
  marked `✓ ! ✗` on a terminal, `warning()` and `error()` with a fix
  line, checks and fixers as status rows (`Outcome::of(Severity)`,
  `Output::problems()`), and no `Style` in a command. Arrow-key prompts
  are stage 3.
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
| `content:type-folders [--write]` | List the data types in `user/data/types` that still name their folder (`folder`, 1.x's `path`), from and to; `--write` moves each type's files into `_{name}` (on files) and writes it without the folder, its pattern as `folders` and its old path as a prefix where its addresses came from it; fails while any are left (D-683, `TypeFolderMigration`) |
| `content:taxonomies [--write]` | List the data types in `user/data/types` still written as taxonomies; `--write` rewrites each as a collection (edited in place) and its classify relation (`user/data/relations/{name}.json`); fails while any are left (D-591, D-594) |
| `content:relation <name> [--key=<key> [--rewrite]] [--remove] [--strip]` | A relation's use, a new key (the old kept as an alias unless `--rewrite`), removing its data file, and stripping its values, through `RelationChanges` (D-600) |
| `content:refs [--write]` | List content files whose relations aren't filed in both forms (no `refs`, an id written as a value, a slug a target no longer has); `--write` files them through `ContentWriter::fileRefs()`; fails while any are left (D-596); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:ids [--write] [--keep=<path>…]` | List content files missing a valid id and ids files share; `--write` gives missing ones new ids, `--keep` keeps a shared id on a file and renews the others (D-477, D-480); the filesystem driver's own, registered while content is kept as files (`Storage::commands()`, D-654) |
| `content:lint [--strict]` | Validate content against schemas: errors, and warnings for two files claiming one entry and dates not on the calendar (D-449); terms and profiles entries name with no file are errors (D-584); `--strict` adds notices for undeclared keys and 1.x aliases (D-081, D-084, D-091). Also reports files in `user/content` in the formats Blush no longer reads (`.markdown`, `.html`, `.json`, `.yaml`, `.yml`; `FormatCheck`, D-501). Also checks media metadata files in `user/data/media`: unreadable, values that don't fit, hidden by another format, or describing a file that's gone (D-293) |
| `content:new [<type>] ["<title>"] [--slug] [--draft] [--parent] [--account]` | Writes through `EntryEditor`, logged as the command line (`Actor::commandLine()`, trusted) or, with `--account`, as that account, with its permissions and byline (D-760). Asks for what isn't given (type, title, slug, a tree's parent, status; D-721), and ends with the admin's edit address (`AdminLinks::entry()`). Scaffold a Markdown entry (`Y-m-d.slug.md` for dated types) and refresh the index (D-091) |
| `content:list [--type] [--status]` | List every indexed entry (M4b) |
| `content:preview <type> <name> [--hours]` | Print a signed preview link to an entry, whatever its status (D-226) |
| `routes:list` | Show the routes, redirects, and shadowed routes (M3, D-077) |
| `media:dates [--write]` | Originals without an `uploaded` date, or with one not in UTC (counted; `-v` lists them with the likely date); `--write` writes the likely date (`MediaDates::guess()`: dated folder, then modified time or embedded `created` inside it, else the folder's start; no folder, modified time) into `user/data/media`, and rewrites dates with another offset in UTC (D-709, D-711) |
| `media:ids [--write] [--keep=<path>…]` | `content:ids` for media files: originals missing a valid id (counted; `-v` lists them) and shared ids; `--write` writes new ids into `user/data/media`, `--keep` renews the others; sizes of another image need none (D-487) |
| `media:sizes [--write]` | Images whose metadata files don't list their sizes as the index has them (counts; `-v` lists), and lists naming files that aren't their sizes; `--write` writes each image's `sizes` whole (D-488) |
| `media:index [--full]` | Build or refresh the media index, with a progress bar; `-v` lists changes, and metadata files with no media file are warnings (D-288) |
| `media:publish [--copy]` | Link `user/media` into `public/` at the media URL, or copy the allowed files (M4c, D-099); writes the served folder's `.htaccess` (D-499) |
| `theme:list` | List installed themes (framework, Composer, local), the active one, and broken manifests (M5b, D-120) |
| `theme:activate <name>` | Set the active theme (by its `vendor/name`, D-378) in `config/theme.php` (created, or its plain `active` value edited) and clear the config and theme caches (D-120), and a theme the admin saved in the `theme` settings group (D-381, D-673); refuses a theme whose chain's `require` isn't met (D-431) |
| `theme:new [<vendor/name>] [--parent] [--label] [--namespace] [--description] [--activate]` | Asks for what isn't given (name, label, parent, description, activate; the namespace only when the derived one can't be used; D-719). Create a minimal theme (manifest, stylesheet unless it has a parent (D-618), and a starter `lang/en.json` with `@@locale` and `@@domain`, D-452) in `extensions/{vendor}/{name}` (D-418, D-120, D-378); the namespace defaults to the name, hyphenated (`acme-nova`, D-424), the name and namespace must be free across every installed extension (D-417), and the manifest has a `$schema` key (D-206) |
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
| `lang:extract <name> [--locale] [--dry-run]` | Write the text an extension's code writes (`->t()` calls with text, read by `MessageExtractor`) to its source catalog, `lang/en.json` or `--locale`'s (`SourceCatalog`): adds new keys, replaces changed text, writes `context` as `@{key}`, keeps the rest; `core` for the framework's `src` and `resources/views`/`directives`; warns about calls it can't collect, one key with two texts, and invalid ICU (D-784) |
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
| `init [--webhook]` | Create `.env` from `.env.example` (asking for name, URL, timezone, and environment in a terminal), add an `APP_SECRET` and a `SECRETS_KEY` (D-715) when missing, optionally add a `PUBLISH_SECRET`, create the storage folders, and report unwritable ones; idempotent (D-218) |
| `secrets:list` | Declared secrets (and stored ones nothing declares): status, environment variable, when stored; never a value (D-715) |
| `secrets:set <name>` | Store a declared secret's value, asked for hidden (needs a terminal); refused when the environment sets it (D-715) |
| `secrets:remove <name>` | Remove a stored value, declared or left behind (D-715) |
| `secrets:rotate` | Encrypt every stored secret again under `SECRETS_KEY`, reading old ones with `SECRETS_KEY_PREVIOUS`; fails when one won't decrypt (D-715) |
| `account:add [<username>] [--email] [--role]... [--author] [--name] [--link] [--token-only]` | Create an account, asking for what isn't given (D-720: username, name, email, roles, profile, password or link) and twice for the password (needs a terminal), or with `--link` a one-time link printed instead (no terminal needed); owner while the site has none, else administrator (D-219, D-322, D-370, D-500). `--token-only` makes a token-only account: no password, link, or profile, an optional email (D-744, D-750) |
| `account:list` | Accounts with name, email, roles (unknown ones flagged), author, status, and last sign-in (D-219, D-312, D-322, D-370) |
| `account:password <username>` | Set a password, which signs the account's sessions out (D-219) |
| `account:roles <username> --role...` | Replace an account's roles (D-219) |
| `account:name <username> [name]` | Name an account, or remove its name (D-322) |
| `account:email <username> <email>` | Change an account's email address, which every account needs (D-370) |
| `account:author <username> [slug]` | Link to an author, or unlink; offers to write the author's entry when there's none (D-219, D-259) |
| `account:suspend <username>` | Suspend an account: signed out, and no sign-in or password link until reinstated (D-312) |
| `account:reinstate <username>` | Reinstate a suspended account (D-312) |
| `account:remove <username> [--yes]` | Delete an account after confirming (D-219) |
| `mcp --account=<username>` | Serve the MCP server over stdio as an account, for a local agent: one JSON-RPC message a line in and out, nothing else on stdout, problems on stderr; works with the MCP server off for the web, refuses a suspended account (D-751, D-754) |
| `tool [<key>] [name=value …]` | List the tools the actor may use, or run one (`Console\Commands\RunTool`, D-761): input as `name=value` read by the tool's schema (integers, numbers, booleans, a list by repeating the name or as JSON, an object as JSON); a tool that needs input and gets none prints its fields first; as the command line (`Actor::commandLine()`) or `--account=`; `--json` writes only its data; `queued()` tools run straight away |
| `token:add <username> [--name] [--expires] [--capabilities]` | Make an API token for an account and print it once, alone on its line with `--quiet`; asks for a name on a terminal; `--expires` is 30, 90 (default), 365, or `never`; `--capabilities` narrows it, comma-separated (D-749, D-750) |
| `doctor` | Run every `SetupChecks` check (PHP, extensions, `.env`, production risks, `public/`, storage) with hints; fails on any failure. Also warns of extensions that are on but can't run (an active theme falling back, plugins, icon packs; D-431), and of a site with accounts but no owner (D-500), and checks the secrets: the key, required ones unset, stored ones that won't decrypt or nothing declares (D-715). No opcache check, since the CLI's PHP isn't the web server's (D-218) |
| `generate:{provider,component,controller,command,type}` | Scaffolding (not `make:`, D-008) |
| `new <dir>` | Create a new site from the skeleton (may live in a global installer) |
