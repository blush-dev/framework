---
name: blush-code-style-php
description: >
  PHP coding standards for Blush (the flat-file CMS framework) and sites built
  on it. Use before writing, editing, or reviewing any PHP in this project:
  classes, templates, config files, tests, CLI commands. Takes precedence over
  x3p0-code-style-php wherever they conflict (PHP version, WordPress rules).
---

# Blush PHP style guide

This is based on the x3p0 PHP style, adapted for a **non-WordPress, PHP 8.5**
codebase. When it conflicts with `x3p0-code-style-php`, this skill wins.
PHPCS (`.phpcs.xml`) enforces the mechanical rules.

---

## The non-negotiables

**Tabs, not spaces.**

**No spaces inside parentheses** in calls, control structures, and
declarations:

```php
// Correct
if ($condition) {
}

$this->render($view, $data);

// Wrong
if ( $condition ) {
$this->render( $view, $data );
```

**Spaces around operators**, and a space after `!`:

```php
$total = $a + $b;
$valid = $count > 0 && ! $disabled;
```

**One space after control keywords** (`if`, `elseif`, `foreach`, `while`,
`for`, `switch`, `match`, `try`, `catch`).

**No Yoda conditions.** Write `$status === Status::Draft`.

---

## File structure

- UTF-8, Unix line endings, one blank line at EOF, no closing `?>` in class
  files.
- File docblock, then `declare(strict_types=1);`, then the namespace, then
  imports.
- `declare(strict_types=1);` in **every** PHP file, including templates and
  config.

```php
<?php

/**
 * Content repository.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Blush\Content\Index\ContentIndex;
```

Group imports in this order: PHP built-ins → PSR → other vendors → Blush.
Put a blank line between groups only when a file has many imports; otherwise
keep them compact. Never use a leading backslash in `use` statements. One
class per `use` line (no grouped `{}` imports).

---

## Classes

- Opening brace on its own line for classes and methods.
- Visibility on everything. `abstract`/`final` go before visibility, `static`
  after.
- **Concrete classes are `final`** unless they're designed for extension.
  Extension points are abstract bases or interfaces.
- **Constructor property promotion** with `readonly`: `private` on final
  classes, `protected` on abstract bases. No setters; use `with*()` methods
  that return clones.
- Prefer `readonly class` for value objects.
- Empty constructors may be written as `{}` on the signature line.
- Use `#[\Override]` on every method that overrides or implements a parent or
  interface method.
- Use typed class constants: `protected const array PROVIDERS = [];`.
- Prefer enums over string constants for any closed set (status, order,
  granularity, exit codes). Use backed enums when the value is serialized.

```php
final readonly class Entry
{
	public function __construct(
		public EntryId $id,
		public string $title,
		public Status $status,
		public DateTimeImmutable $published,
		private Body $body
	) {}

	public function body(): string
	{
		return $this->body->html();
	}
}
```

---

## Modern PHP (8.4 / 8.5): use freely

- **Property hooks and asymmetric visibility** (`public private(set) int $count`)
  instead of trivial getters and setters.
- **Lazy objects** (`ReflectionClass::newLazyGhost()`/`newLazyProxy()`) for
  expensive values such as entry bodies and deferred services.
- **`clone()` with properties** for immutable `with*()` methods:
  `return clone($this, ['limit' => $limit]);`
- **`#[\NoDiscard]`** on methods whose return value must be used (immutable
  builders, `with*()`).
- **Pipe operator** (`|>`) for linear transformations when it reads better
  than nesting:
  `$slug = $title |> trim(...) |> strtolower(...) |> $this->slugify(...);`
- **`Uri\Rfc3986\Uri`** for all URI parsing and building; never `parse_url()`.
- **`array_first()`, `array_last()`, `array_find()`, `array_any()`,
  `array_all()`** instead of hand-written loops.
- **`new` without wrapping parentheses** when chaining: `new Foo()->bar()`.
- **`match`** instead of `switch`; **first-class callables** (`strlen(...)`).
- **`Dom\HTMLDocument`** for HTML manipulation; never regex over HTML.
- **Named arguments** for constructors with many parameters and for config
  objects.

Check PHPCS tokenizer support for `|>` and `clone()` with properties (see
open questions). If PHPCS can't parse them, follow the decision recorded in
`decisions.md`.

---

## Naming

- Classes are `PascalCase` nouns. Interfaces and abstract bases get the plain
  name (`ContentSource`), and implementations are qualified
  (`FilesystemSource`). No `Interface`/`Abstract` suffixes or prefixes.
- Methods are `camelCase` verbs. Boolean methods start with `is`, `has`, `can`,
  or `should`.
- Properties and variables are `camelCase` (not `snake_case`).
- Enum cases are `PascalCase`.
- **Avoid Laravel-isms:** no facades, `Illuminate`-style naming, "Foundation",
  `make:` commands, or "composers". Use `Core`, `generate:`, "context
  providers", and so on.

---

## Architecture rules

- **No global state:** no static facades, service locators, `define()`
  constants, or `$GLOBALS`. Inject dependencies through constructors.
- **Only template escaping helpers may be global functions.**
- Never read `$_GET`, `$_POST`, `$_SERVER`, and so on outside
  `RequestFactory`.
- Configuration is typed, immutable config objects, not arrays.
- Throw specific exceptions that extend the subsystem's base exception, and
  never die or exit.
- Extensible subsystems use the Type enum + Registry + Factory + Registrar
  pattern.

---

## Templates (plain PHP views)

- Short echo tags for output: `<?= e($entry->title) ?>`.
- Use alternative syntax for control structures in templates:
  `<?php if ($entry->hasTerms()) : ?> … <?php endif ?>`.
- **Escape everything at the point of output** with the most specific helper:
  `e()` (HTML text), `attr()`, `url()`, `js()`, `css()`. Use `raw()` only for
  trusted, already-rendered HTML (for example, an entry body).
- No business logic or queries in templates. Controllers and context
  providers supply typed view data.

---

## Docblocks

- Every class and every non-trivial method gets a docblock explaining *why*
  and *how*, not restating the signature.
- Only use `@param`/`@return`/`@var` when they add information beyond native
  types (generics, array shapes, `class-string<T>`).
- Use `@throws` for exceptions callers should handle.
- No `@since` tags during 2.x development.

---

## Security

- Resolve and confine every filesystem path to its root
  (content, media, views, themes) before reading or writing.
- Parse YAML without objects. Never `unserialize()` untrusted data.
- Compare secrets with `hash_equals()`. Hash passwords with
  `password_hash()`.
- Write files atomically (temp file + `rename()`).

---

## Line length

Not enforced. Wrap only when it helps readability.
