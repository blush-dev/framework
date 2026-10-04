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
| `serve [--host] [-p\|--port] [--static]` | Dev server (`php -S` + `resources/server.php`); `--static` previews the static export through `resources/static-server.php`, applying its `_redirects` (D-138, D-140) |
| `cache:clear [--config\|--plugins\|--container\|--routes\|--types\|--themes\|--icon-packs\|--store]` | Clear compiled caches and the cache store, bumping the content version (no flags: all; `--store`: only the store, D-128) |
| `cache:compile` | Compile config, plugins, themes, icon packs, routes, content types, and container plans, then clear the cache store and bump the content version (D-060, D-066, D-077, D-092, D-115, D-128) |
| `content:index [--full]` | Build or refresh the content index, with a progress bar; `-v` lists changes (M4b, D-087) |
| `content:lint [--strict]` | Validate content against schemas: errors, and warnings for two files claiming one entry; `--strict` adds notices for undeclared keys, 1.x aliases, and virtual terms (D-081, D-084, D-091). Also checks media metadata files in `user/data/media`: unreadable, values that don't fit, hidden by another format, or describing a file that's gone (D-293) |
| `content:new <type> "<title>" [--slug] [--draft]` | Scaffold a Markdown entry (`Y-m-d.slug.md` for dated types) and refresh the index (D-091) |
| `content:list [--type] [--status]` | List every indexed entry (M4b) |
| `content:preview <type> <name> [--hours]` | Print a signed preview link to an entry, whatever its status (D-226) |
| `routes:list` | Show the routes, redirects, and shadowed routes (M3, D-077) |
| `media:index [--full]` | Build or refresh the media index, with a progress bar; `-v` lists changes, and metadata files with no media file are warnings (D-288) |
| `media:publish [--copy]` | Link `user/media` into `public/` at the media URL, or copy the allowed files (M4c, D-099) |
| `theme:list` | List installed themes (framework, Composer, local), the active one, and broken manifests (M5b, D-120) |
| `theme:activate <name>` | Set the active theme (by its `vendor/name`, D-378) in `config/theme.php` (created, or its plain `active` value edited) and clear the config and theme caches (D-120), and a theme the admin saved in `user/data/settings.json` (D-381); refuses a theme whose chain's `require` isn't met (D-431) |
| `theme:new <vendor/name> [--parent] [--label] [--namespace]` | Create a minimal theme (manifest plus stylesheet) in `extensions/{vendor}/{name}` (D-418, D-120, D-378); the namespace defaults to the name, hyphenated (`acme-nova`, D-424), the name and namespace must be free across every installed extension (D-417), and the manifest has a `$schema` key (D-206) |
| `theme:publish [--all]` | Copy servable theme assets to `public/themes/{vendor}/{name}`, removing stale ones; the active chain, or every theme (D-119) |
| `theme:check [name] [--strict]` | Check the chain, manifests, `require` as if active (an error for the active theme, which falls back; a warning for another, D-431), a `version` Composer can't read and an `abandoned` theme (warnings, D-433), provider, settings, components without a template (D-164), component files not named for a component, registered components without a label (notice; D-173), and the base layout's landmarks and skip link (D-030, D-121, D-160) |
| `theme:why <view> [--theme]` | Show which file in the view chain wins for a view, and what it shadows (D-120) |
| `icon:list [--theme]` | List every icon the chain can show: full name, label, and winning file (D-187) |
| `icon-pack:check [name]` | Check every icon pack's manifest, `require` (one that's off as if it were on), and `version`, as `plugin:check` does: a pack that's on but can't load is an error and fails it; one that's off, a broken one, a `version` Composer can't read, and an `abandoned` pack are warnings (D-431, D-433) |
| `menu:list [--theme]` | List the chain's menu locations, the site menu each shows, resolved item counts, and files; site menus no location shows; problems (D-204) |
| `menu:show <location> [--theme] [--locale]` | Print a location's menu resolved as a page sees it (labels and URLs, nested), with its problems; fails when it shows none (D-204) |
| `component:list [--theme]` | List every component the chain can render: full name, label, registered or not, class, variants (D-266), and winning template; warn when one can't render (D-164) and about files not named for a component (D-173) |
| `lang:missing [--locale]` | List untranslated message keys |
| `build [--base-url] [--no-crawl] [--incremental]` | Export the site to static files in `storage/export`, rendered as production for the export's origin, with redirects and host files; `--incremental` keeps the last export's pages when nothing changed; broken links and host-file notices are warnings, failed URLs fail it (M7, D-135 to D-140) |
| `publish [--pull\|--no-pull]` | Pull `user/` (with `PublishConfig::$git`), recompile the content types and routes, reindex, clear the store, and bump the content version, as the webhook does (D-131) |
| `plugin:list` | List installed plugins (name, label, namespace, version, source, and on, off, or can't run, D-385), and broken manifests as warnings (D-394) |
| `plugin:check [name]` | Check every plugin's manifest and `require` (or one plugin's), one that's off as if it were on; a plugin turned on that can't run, or a broken one config turns on, is an error and fails it, one that's off a warning (D-394), and so are a `version` Composer can't normalize (D-430) and an `abandoned` plugin, which still runs (D-433) |
| `plugin:new <vendor/name> [--label] [--namespace] [--php-namespace]` | Create a plugin (manifest plus an empty service provider in `src/`, autoloaded PSR-4) in `extensions/{vendor}/{name}`, off until turned on (D-416, D-418); the namespace defaults to the name, hyphenated (`acme-hello`, D-424), the name and namespace must be free across every installed extension, the PHP namespace defaults to the name in StudlyCase, and the manifest has a `$schema` key (D-206) |
| `schedule:run` | Optional cron entry: move the content version on at go-live times and prune the store (D-040, D-133) |
| `bench` | Run the performance suite (dev only, D-044). For now it's `composer bench` in the framework (D-101) |
| `init [--webhook]` | Create `.env` from `.env.example` (asking for name, URL, timezone, and environment in a terminal), optionally add a `PUBLISH_SECRET`, create the storage folders, and report unwritable ones; idempotent (D-218) |
| `account:add <username> [--email] [--role]... [--author] [--name]` | Create an account, asking for the email when `--email` is left out and twice for the password (needs a terminal); administrator by default (D-219, D-322, D-370) |
| `account:list` | Accounts with name, email, roles (unknown ones flagged), author, status, and last sign-in (D-219, D-312, D-322, D-370) |
| `account:password <username>` | Set a password, which signs the account's sessions out (D-219) |
| `account:roles <username> --role...` | Replace an account's roles (D-219) |
| `account:name <username> [name]` | Name an account, or remove its name (D-322) |
| `account:email <username> <email>` | Change an account's email address, which every account needs (D-370) |
| `account:author <username> [slug]` | Link to an author, or unlink; warns when no such author exists, virtual terms included (D-219) |
| `account:suspend <username>` | Suspend an account: signed out, and no sign-in or password link until reinstated (D-312) |
| `account:reinstate <username>` | Reinstate a suspended account (D-312) |
| `account:remove <username> [--yes]` | Delete an account after confirming (D-219) |
| `doctor` | Run every `SetupChecks` check (PHP, extensions, `.env`, production risks, `public/`, storage) with hints; fails on any failure. Also warns of extensions that are on but can't run (an active theme falling back, plugins, icon packs; D-431). No opcache check, since the CLI's PHP isn't the web server's (D-218) |
| `generate:{provider,component,controller,command,type}` | Scaffolding (not `make:`, D-008) |
| `new <dir>` | Create a new site from the skeleton (may live in a global installer) |
