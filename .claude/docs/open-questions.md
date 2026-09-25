# Open questions

Move each item to `decisions.md` once it's answered.

## Product
- **Name.** "Blush" is a working name. Keep the name centralized (namespace,
  binary, Composer vendor, `extra.blush.*` keys) so a rename is mechanical.
  Candidates to check for Packagist/GitHub/domain availability: *Vellum*,
  *Folio*, *Quire*, *Marginalia*, *Plinth*, *Inkwell*.
- **Repo strategy after 2.x stabilizes:** keep it one package, or split it
  (core, http, content, console…) as a monorepo?

## Tooling
- **PHPCS and 8.5 syntax:** PHPCS/PHPCompatibility may not tokenize the pipe
  operator (`|>`) or `clone()` with properties yet. Verify in M0. If they
  can't, either hold off on those features in affected files or add a
  PHP-CS-Fixer check alongside.
- **`x3p0-skills` scope:** its installer copies WordPress theme skills too.
  Is that acceptable, or does it need a config option to install only some
  skills?

## Site layout
- Should site `config/` live at the root (current plan) or under `user/`?
- Should `themes/` live at the root or under `user/themes`?

## Content
- Markdown component syntax: directives vs. shortcodes vs. custom elements.
  See `theming.md`.
- Multilingual content: in scope for 2.x or later?

## Theming
See the open questions in `theming.md`.
