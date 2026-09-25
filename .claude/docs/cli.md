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
  verbosity levels, and tables. Progress bars come in M4. Prompts
  (confirm, ask, choice, secret) have a non-interactive fallback.
- **Exit codes:** an enum (`Success`, `Failure`, `Invalid`, …).
- **Boots the same `Application`** as the web, so commands get the container,
  config, content, and `Kernel::handle()`.
- **Testing:** a `CommandTester` that captures output and exit codes.

## Built-in commands (initial list)

| Command | Purpose |
|---|---|
| `list` | List the commands (the default) |
| `help <command>` | Show a command's usage |
| `serve [--host] [-p\|--port]` | Dev server (`php -S` + `resources/server.php`) |
| `cache:clear [--config\|--extensions\|--container\|--routes]` | Clear compiled caches (no flags: all). Pages and the content version join later |
| `cache:compile` | Compile config, extensions, routes, and container plans (D-060, D-066, D-077) |
| `content:index [--full]` | Build or refresh the content index |
| `content:lint [--strict]` | Validate front matter against schemas; `--strict` adds notices for undeclared keys and 1.x aliases (D-081, D-084) |
| `content:new <type> "<title>"` | Scaffold an entry |
| `content:list [--type] [--status]` | Inspect content |
| `routes:list` | Show the routes, redirects, and shadowed routes (M3, D-077) |
| `media:publish` | Symlink or copy media into `public/` |
| `theme:list\|activate\|new\|publish` | Theme management |
| `theme:check` | Validate the manifest, required templates, and accessibility basics (D-030) |
| `theme:why <view>` | Show which file in the theme chain wins for a view |
| `lang:missing [--locale]` | List untranslated message keys |
| `build [--incremental] [--base-url]` | Static export |
| `publish` | Pull content, reindex, and bump the content version (same as the webhook) |
| `extension:list\|new\|check` | Extension management (D-041) |
| `schedule:run` | Optional cron entry: process scheduled go-live times (D-040) |
| `bench` | Run the performance suite (dev only, D-044) |
| `doctor` | Check environment, permissions, extensions, and config |
| `generate:{provider,component,controller,command,type}` | Scaffolding (not `make:`, D-008) |
| `new <dir>` | Create a new site from the skeleton (may live in a global installer) |
