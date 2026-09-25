# CLI

An in-house console framework (D-012). There is no symfony/console dependency.
The binary is `bin/blush` for now and will follow the final product name.

## Console framework (`Blush\Console`)

- **Commands:** classes with an attribute:
  `#[Command(name: 'cache:clear', description: '…')]`. Arguments and options
  are declared as typed, promoted constructor properties (or `#[Argument]`/
  `#[Option]` on an input DTO), so parsing, validation, and help text come
  from types.
- **Discovery:** framework, extension, theme, and site commands are
  registered through the enum + registry pattern and resolved through the
  container.
- **Input:** an argv parser (long and short options, `--opt=value`, flags,
  variadic arguments, `--` terminator).
- **Output:** styled writer (ANSI with automatic detection, `NO_COLOR`),
  verbosity levels, tables, progress bars, and prompts (confirm, ask,
  choice, secret) with a non-interactive fallback.
- **Exit codes:** an enum (`Success`, `Failure`, `Invalid`, …).
- **Boots the same `Application`** as the web, so commands get the container,
  config, content, and `Kernel::handle()`.
- **Testing:** a `CommandTester` that captures output and exit codes.

## Built-in commands (initial list)

| Command | Purpose |
|---|---|
| `serve` | Dev server (`php -S` + router script) |
| `cache:clear [--pages\|--config\|--routes\|--all]` | Clear caches / bump the content version |
| `content:index [--full]` | Build or refresh the content index |
| `content:lint` | Validate front matter against schemas |
| `content:new <type> "<title>"` | Scaffold an entry |
| `content:list [--type] [--status]` | Inspect content |
| `routes:list` | Show the compiled routes |
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
